<?php

namespace Tests\Unit\Services;

use App\Services\OpenAiClient;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class OpenAiClientTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.openai.api_key' => 'test-key',
            'services.openai.chat_model' => 'gpt-4o-mini',
            'services.openai.whisper_model' => 'whisper-1',
        ]);
    }

    public function test_chat_completion_json_returns_decoded_content(): void
    {
        Http::fake([
            'https://api.openai.com/v1/chat/completions' => Http::response([
                'choices' => [
                    ['message' => ['content' => json_encode(['diagnosis_summary' => 'ok', 'sessions' => []])]],
                ],
            ], 200),
        ]);

        $result = (new OpenAiClient)->chatCompletionJson('system prompt', 'user prompt', [
            'name' => 'x', 'strict' => true, 'schema' => [],
        ]);

        $this->assertSame('ok', $result['diagnosis_summary']);
        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.openai.com/v1/chat/completions'
                && $request['model'] === 'gpt-4o-mini'
                && $request['response_format']['type'] === 'json_schema';
        });
    }

    public function test_chat_completion_json_throws_when_request_fails(): void
    {
        Http::fake([
            'https://api.openai.com/v1/chat/completions' => Http::response(['error' => 'bad'], 500),
        ]);

        $this->expectException(ValidationException::class);

        (new OpenAiClient)->chatCompletionJson('system prompt', 'user prompt', [
            'name' => 'x', 'strict' => true, 'schema' => [],
        ]);
    }

    public function test_transcribe_returns_text(): void
    {
        Http::fake([
            'https://api.openai.com/v1/audio/transcriptions' => Http::response(['text' => 'tooth 13 has pulp necrosis'], 200),
        ]);

        $audio = UploadedFile::fake()->create('note.mp3', 10, 'audio/mpeg');

        $text = (new OpenAiClient)->transcribe($audio);

        $this->assertSame('tooth 13 has pulp necrosis', $text);
    }
}
