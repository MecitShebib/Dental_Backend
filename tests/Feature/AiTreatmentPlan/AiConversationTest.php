<?php

namespace Tests\Feature\AiTreatmentPlan;

use App\Models\AiConversationMessage;
use App\Models\Client;
use App\Models\Company;
use App\Models\Subscription;
use App\Models\User;
use App\Models\XrayImage;
use Database\Seeders\SpecialtySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AiConversationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(SpecialtySeeder::class);

        config([
            'services.openai.api_key' => 'test-key',
            'services.openai.chat_model' => 'gpt-4o-mini',
        ]);
    }

    protected function fakeChatReply(
        string $reply = 'Sure, tell me more about the symptoms.',
        array $usage = ['prompt_tokens' => 60, 'completion_tokens' => 20, 'total_tokens' => 80],
        array $options = [],
        bool $readyForPlan = false,
    ): void {
        Http::fake([
            'https://api.openai.com/v1/chat/completions' => Http::response([
                'choices' => [['message' => ['content' => $this->encodeChatReply($reply, $options, $readyForPlan)]]],
                'usage' => $usage,
            ], 200),
        ]);
    }

    protected function encodeChatReply(string $reply, array $options = [], bool $readyForPlan = false): string
    {
        return json_encode([
            'reply' => $reply,
            'options' => $options,
            'ready_for_plan' => $readyForPlan,
        ]);
    }

    protected function activeDoctor(): User
    {
        $doctor = User::factory()->create(['is_doctor' => true]);
        $doctor->company->subscriptions()->delete();
        Subscription::create([
            'company_id' => $doctor->company_id,
            'plan_name' => 'Test Plan',
            'status' => 'active',
            'starts_at' => now()->subDay()->toDateString(),
            'max_users' => 10,
        ]);

        return $doctor;
    }

    protected function makeClient(Company $company): Client
    {
        return Client::create([
            'company_id' => $company->id,
            'client_code' => 'CL-'.fake()->unique()->numberBetween(1000, 9999),
            'name' => 'Conversation Patient',
            'phone' => fake()->unique()->e164PhoneNumber(),
            'gender' => 'male',
            'status' => 'new',
        ]);
    }

    protected function makeXrayImage(Client $client, string $path): XrayImage
    {
        return XrayImage::create([
            'company_id' => $client->company_id,
            'client_id' => $client->id,
            'image_path' => $path,
            'original_filename' => basename($path),
        ]);
    }

    public function test_sending_a_message_persists_both_messages_and_returns_the_ai_reply(): void
    {
        $doctor = $this->activeDoctor();
        Sanctum::actingAs($doctor);
        $client = $this->makeClient($doctor->company);
        $this->fakeChatReply();

        $response = $this->postJson("/api/clients/{$client->id}/ai-conversation/messages", [
            'text' => 'What do you think about tooth 26?',
        ])->assertOk();

        $response->assertJsonPath('data.user_message.content', 'What do you think about tooth 26?')
            ->assertJsonPath('data.assistant_message.content', 'Sure, tell me more about the symptoms.')
            ->assertJsonPath('data.assistant_message.role', 'assistant');

        $this->assertDatabaseCount('ai_conversations', 1);
        $this->assertDatabaseCount('ai_conversation_messages', 2);
        $this->assertDatabaseCount('appointments', 0);
    }

    public function test_assistant_reply_can_carry_clickable_answer_options(): void
    {
        $doctor = $this->activeDoctor();
        Sanctum::actingAs($doctor);
        $client = $this->makeClient($doctor->company);
        $this->fakeChatReply('Which tooth is bothering the patient?', options: ['Tooth 26', 'Tooth 36', 'Not sure yet']);

        $response = $this->postJson("/api/clients/{$client->id}/ai-conversation/messages", [
            'text' => 'The patient has some pain.',
        ])->assertOk();

        $response->assertJsonPath('data.assistant_message.options', ['Tooth 26', 'Tooth 36', 'Not sure yet'])
            ->assertJsonPath('data.assistant_message.ready_for_plan', false);

        $this->assertDatabaseHas('ai_conversation_messages', [
            'role' => 'assistant',
            'ready_for_plan' => false,
        ]);
    }

    public function test_assistant_reply_signals_when_ready_to_build_a_plan(): void
    {
        $doctor = $this->activeDoctor();
        Sanctum::actingAs($doctor);
        $client = $this->makeClient($doctor->company);
        $this->fakeChatReply('Now I have enough information. Please press Create Plan.', readyForPlan: true);

        $response = $this->postJson("/api/clients/{$client->id}/ai-conversation/messages", [
            'text' => 'It is tooth 26, deep decay, sensitive to cold for two weeks.',
        ])->assertOk();

        $response->assertJsonPath('data.assistant_message.ready_for_plan', true)
            ->assertJsonPath('data.assistant_message.options', null);

        $this->assertDatabaseHas('ai_conversation_messages', [
            'role' => 'assistant',
            'ready_for_plan' => true,
        ]);
    }

    public function test_first_message_attaches_the_clients_recent_xray_images(): void
    {
        $doctor = $this->activeDoctor();
        Sanctum::actingAs($doctor);
        $client = $this->makeClient($doctor->company);
        $this->makeXrayImage($client, 'xray-images/one.jpg');
        $this->makeXrayImage($client, 'xray-images/two.jpg');
        $this->fakeChatReply();

        $this->postJson("/api/clients/{$client->id}/ai-conversation/messages", [
            'text' => 'Here is the case.',
        ])->assertOk();

        $expectedUrlOne = Storage::disk('public')->url('xray-images/one.jpg');
        $expectedUrlTwo = Storage::disk('public')->url('xray-images/two.jpg');

        $this->assertDatabaseHas('ai_conversation_messages', [
            'role' => 'user',
            'content' => 'Here is the case.',
        ]);

        $userMessage = AiConversationMessage::where('role', 'user')->firstOrFail();
        $this->assertContains($expectedUrlOne, $userMessage->image_urls);
        $this->assertContains($expectedUrlTwo, $userMessage->image_urls);

        Http::assertSent(function ($request) use ($expectedUrlOne) {
            $firstUserMessage = $request['messages'][1];

            return is_array($firstUserMessage['content'])
                && collect($firstUserMessage['content'])->contains(fn ($block) => ($block['type'] ?? null) === 'image_url' && $block['image_url']['url'] === $expectedUrlOne);
        });
    }

    public function test_second_message_does_not_reattach_fresh_images_but_history_keeps_the_first_ones(): void
    {
        $doctor = $this->activeDoctor();
        Sanctum::actingAs($doctor);
        $client = $this->makeClient($doctor->company);
        $this->makeXrayImage($client, 'xray-images/one.jpg');
        $this->fakeChatReply();

        $this->postJson("/api/clients/{$client->id}/ai-conversation/messages", ['text' => 'First message.'])->assertOk();
        $this->postJson("/api/clients/{$client->id}/ai-conversation/messages", ['text' => 'Second message.'])->assertOk();

        $expectedUrl = Storage::disk('public')->url('xray-images/one.jpg');

        // The second request is the one with 4 messages (system, first user
        // w/ image, first assistant, second user) -- the first request (2
        // messages) would index-error on [3], so only match the longer one.
        Http::assertSent(function ($request) use ($expectedUrl) {
            $messages = $request['messages'];
            if (count($messages) < 4) {
                return false;
            }

            $firstUserIsMultiModal = is_array($messages[1]['content'])
                && collect($messages[1]['content'])->contains(fn ($block) => ($block['type'] ?? null) === 'image_url' && $block['image_url']['url'] === $expectedUrl);
            $secondUserIsPlainText = $messages[3]['content'] === 'Second message.';

            return $firstUserIsMultiModal && $secondUserIsPlainText;
        });
    }

    public function test_image_attachment_is_capped_at_six_most_recent(): void
    {
        $doctor = $this->activeDoctor();
        Sanctum::actingAs($doctor);
        $client = $this->makeClient($doctor->company);
        foreach (range(1, 9) as $i) {
            $this->makeXrayImage($client, "xray-images/{$i}.jpg");
        }
        $this->fakeChatReply();

        $this->postJson("/api/clients/{$client->id}/ai-conversation/messages", ['text' => 'Case.'])->assertOk();

        $userMessage = AiConversationMessage::where('role', 'user')->firstOrFail();
        $this->assertCount(6, $userMessage->image_urls);
    }

    public function test_conversation_history_is_empty_for_a_client_with_no_messages_yet(): void
    {
        $doctor = $this->activeDoctor();
        Sanctum::actingAs($doctor);
        $client = $this->makeClient($doctor->company);

        $this->getJson("/api/clients/{$client->id}/ai-conversation")
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->assertDatabaseCount('ai_conversations', 0);
    }

    public function test_conversation_history_returns_persisted_messages_in_order(): void
    {
        $doctor = $this->activeDoctor();
        Sanctum::actingAs($doctor);
        $client = $this->makeClient($doctor->company);

        Http::fakeSequence('https://api.openai.com/v1/chat/completions*')
            ->push(['choices' => [['message' => ['content' => $this->encodeChatReply('First reply.')]]], 'usage' => ['prompt_tokens' => 60, 'completion_tokens' => 20, 'total_tokens' => 80]], 200)
            ->push(['choices' => [['message' => ['content' => $this->encodeChatReply('Second reply.')]]], 'usage' => ['prompt_tokens' => 60, 'completion_tokens' => 20, 'total_tokens' => 80]], 200);

        $this->postJson("/api/clients/{$client->id}/ai-conversation/messages", ['text' => 'Hello.'])->assertOk();
        $this->postJson("/api/clients/{$client->id}/ai-conversation/messages", ['text' => 'Follow up.'])->assertOk();

        $response = $this->getJson("/api/clients/{$client->id}/ai-conversation")->assertOk();

        $response->assertJsonCount(4, 'data')
            ->assertJsonPath('data.0.content', 'Hello.')
            ->assertJsonPath('data.1.content', 'First reply.')
            ->assertJsonPath('data.2.content', 'Follow up.')
            ->assertJsonPath('data.3.content', 'Second reply.');
    }

    public function test_generate_plan_is_grounded_in_the_prior_conversation(): void
    {
        $doctor = $this->activeDoctor();
        $schedule = $doctor->doctorSchedule()->create(['start_time' => '09:00:00', 'end_time' => '17:00:00', 'slot_minutes' => 30]);
        foreach (['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'] as $day) {
            $schedule->workingDays()->create(['weekday' => $day]);
        }
        Sanctum::actingAs($doctor);
        $client = $this->makeClient($doctor->company);

        Http::fakeSequence('https://api.openai.com/v1/chat/completions*')
            ->push(['choices' => [['message' => ['content' => $this->encodeChatReply('Understood, tooth 26 shows deep decay.')]]], 'usage' => ['prompt_tokens' => 60, 'completion_tokens' => 20, 'total_tokens' => 80]], 200)
            ->push([
                'choices' => [['message' => ['content' => json_encode([
                    'diagnosis_summary' => 'Deep decay on tooth 26.',
                    'sessions' => [[
                        'day_offset' => 0,
                        'duration_minutes' => 30,
                        'session_description' => 'Filling.',
                        'teeth' => [],
                    ]],
                ])]]],
                'usage' => ['prompt_tokens' => 90, 'completion_tokens' => 40, 'total_tokens' => 130],
            ], 200);

        $this->postJson("/api/clients/{$client->id}/ai-conversation/messages", [
            'text' => 'Patient has deep decay on tooth 26.',
        ])->assertOk();

        $this->postJson("/api/clients/{$client->id}/ai-treatment-plan/generate", [
            'text' => 'Please build the plan.',
        ])->assertOk()
            ->assertJsonPath('data.diagnosis_summary', 'Deep decay on tooth 26.');

        Http::assertSent(function ($request) {
            $contents = collect($request['messages'])->pluck('content');

            return $contents->contains('Patient has deep decay on tooth 26.')
                && $contents->contains('Understood, tooth 26 shows deep decay.')
                && $contents->contains('Please build the plan.');
        });

        // The chat turn + its reply, plus the plan trigger + its summary reply.
        $this->assertDatabaseCount('ai_conversation_messages', 4);
    }

    public function test_non_doctor_cannot_send_a_message(): void
    {
        $user = User::factory()->create(['is_doctor' => false]);
        Sanctum::actingAs($user);
        $client = $this->makeClient($user->company);

        $this->postJson("/api/clients/{$client->id}/ai-conversation/messages", [
            'text' => 'Hello.',
        ])->assertStatus(422)->assertJsonValidationErrors('doctor');
    }

    public function test_sending_a_message_records_ai_token_usage(): void
    {
        $doctor = $this->activeDoctor();
        Sanctum::actingAs($doctor);
        $client = $this->makeClient($doctor->company);
        $this->fakeChatReply();

        $this->postJson("/api/clients/{$client->id}/ai-conversation/messages", ['text' => 'Hello.'])->assertOk();

        $this->assertDatabaseHas('ai_usage_logs', [
            'company_id' => $doctor->company_id,
            'user_id' => $doctor->id,
            'client_id' => $client->id,
            'action' => 'ai_conversation_message',
            'prompt_tokens' => 60,
            'completion_tokens' => 20,
            'total_tokens' => 80,
        ]);
    }

    public function test_sending_a_message_is_blocked_when_the_company_has_reached_its_ai_token_limit(): void
    {
        $doctor = $this->activeDoctor();
        $doctor->company->currentSubscription()->first()->update(['max_ai_tokens' => 100, 'ai_tokens_used' => 100]);
        Sanctum::actingAs($doctor);
        $client = $this->makeClient($doctor->company);
        Http::fake();

        $this->postJson("/api/clients/{$client->id}/ai-conversation/messages", ['text' => 'Hello.'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('ai_tokens');

        Http::assertNothingSent();
    }
}
