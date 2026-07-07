<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class OpenAiClient
{
    public function chatCompletionJson(string $systemPrompt, string $userPrompt, array $jsonSchema): array
    {
        $apiKey = (string) config('services.openai.api_key');

        if ($apiKey === '') {
            throw ValidationException::withMessages([
                'description' => ['OpenAI API key is not configured.'],
            ]);
        }

        $response = Http::withToken($apiKey)
            ->timeout(60)
            ->post('https://api.openai.com/v1/chat/completions', [
                'model' => (string) config('services.openai.chat_model', 'gpt-4o-mini'),
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => $userPrompt],
                ],
                'response_format' => [
                    'type' => 'json_schema',
                    'json_schema' => $jsonSchema,
                ],
            ]);

        if (! $response->successful()) {
            Log::error('OpenAI chat completion request failed.', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            throw ValidationException::withMessages([
                'description' => ['The AI service could not generate a treatment plan. Please try again.'],
            ]);
        }

        $content = $response->json('choices.0.message.content');
        $decoded = json_decode((string) $content, true);

        if (! is_array($decoded)) {
            throw ValidationException::withMessages([
                'description' => ['The AI service returned an unreadable response.'],
            ]);
        }

        return [
            'content' => $decoded,
            'usage' => [
                'prompt_tokens' => (int) $response->json('usage.prompt_tokens', 0),
                'completion_tokens' => (int) $response->json('usage.completion_tokens', 0),
                'total_tokens' => (int) $response->json('usage.total_tokens', 0),
            ],
        ];
    }

    public function transcribe(UploadedFile $audio): string
    {
        $apiKey = (string) config('services.openai.api_key');

        if ($apiKey === '') {
            throw ValidationException::withMessages([
                'audio' => ['OpenAI API key is not configured.'],
            ]);
        }

        $response = Http::withToken($apiKey)
            ->timeout(60)
            ->attach('file', fopen($audio->getRealPath(), 'r'), $audio->getClientOriginalName())
            ->post('https://api.openai.com/v1/audio/transcriptions', [
                'model' => (string) config('services.openai.whisper_model', 'whisper-1'),
            ]);

        if (! $response->successful()) {
            Log::error('OpenAI transcription request failed.', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            throw ValidationException::withMessages([
                'audio' => ['The AI service could not transcribe the recording. Please try again.'],
            ]);
        }

        return (string) $response->json('text');
    }
}
