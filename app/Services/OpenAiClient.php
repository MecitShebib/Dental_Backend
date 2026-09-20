<?php

namespace App\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class OpenAiClient
{
    /**
     * $messages is already in the API's own shape: [{role, content}], where
     * `content` may be a plain string or an array of content blocks
     * (text/image_url) for a multi-modal turn.
     */
    public function chatCompletionJson(array $messages, array $jsonSchema): array
    {
        $response = $this->request($messages, [
            'type' => 'json_schema',
            'json_schema' => $jsonSchema,
        ]);

        $content = $response->json('choices.0.message.content');
        $decoded = json_decode((string) $content, true);

        if (! is_array($decoded)) {
            throw ValidationException::withMessages([
                'description' => ['The AI service returned an unreadable response.'],
            ]);
        }

        return [
            'content' => $decoded,
            'usage' => $this->usageFrom($response),
        ];
    }

    /**
     * A vision-turn content block for an uploaded report file -- an actual
     * image goes in as image_url (the original path), while a PDF goes in
     * as a file content block (filename + base64 file_data): the Chat
     * Completions API accepts PDFs directly for vision-capable models like
     * gpt-4o/gpt-4o-mini, rendering each page as an image internally, so no
     * server-side PDF-to-image rasterization is needed (this host has
     * neither Imagick nor Ghostscript available).
     */
    public function buildVisionContentBlock(UploadedFile $file): array
    {
        $mimeType = $file->getMimeType() ?: 'image/jpeg';
        $dataUri = 'data:'.$mimeType.';base64,'.base64_encode(file_get_contents($file->getRealPath()));

        if ($mimeType === 'application/pdf') {
            return [
                'type' => 'file',
                'file' => [
                    'filename' => $file->getClientOriginalName() ?: 'report.pdf',
                    'file_data' => $dataUri,
                ],
            ];
        }

        return ['type' => 'image_url', 'image_url' => ['url' => $dataUri]];
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

    protected function request(array $messages, ?array $responseFormat = null): Response
    {
        $apiKey = (string) config('services.openai.api_key');

        if ($apiKey === '') {
            throw ValidationException::withMessages([
                'description' => ['OpenAI API key is not configured.'],
            ]);
        }

        $payload = [
            'model' => (string) config('services.openai.chat_model', 'gpt-4o-mini'),
            'messages' => $messages,
        ];

        if ($responseFormat) {
            $payload['response_format'] = $responseFormat;
        }

        $response = Http::withToken($apiKey)
            ->timeout(60)
            ->post('https://api.openai.com/v1/chat/completions', $payload);

        if (! $response->successful()) {
            Log::error('OpenAI chat completion request failed.', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            throw ValidationException::withMessages([
                'description' => ['The AI service could not respond. Please try again.'],
            ]);
        }

        return $response;
    }

    protected function usageFrom(Response $response): array
    {
        return [
            'prompt_tokens' => (int) $response->json('usage.prompt_tokens', 0),
            'completion_tokens' => (int) $response->json('usage.completion_tokens', 0),
            'total_tokens' => (int) $response->json('usage.total_tokens', 0),
        ];
    }
}
