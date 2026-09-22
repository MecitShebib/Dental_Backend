<?php

namespace App\Jobs;

use App\Models\ClientConsent;
use App\Models\ConsentTemplate;
use App\Models\XrayImage;
use App\Services\AiTokenUsageService;
use App\Services\AiTreatmentPlanService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

class AnalyzeXrayImageJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public function __construct(public XrayImage $xrayImage) {}

    public function handle(AiTreatmentPlanService $plans, AiTokenUsageService $aiTokenUsage): void
    {
        $xrayImage = $this->xrayImage->fresh(['client', 'uploader']);

        // The image may have been unlinked again (or its uploader account
        // removed) between dispatch and this job actually running -- nothing
        // to analyze or attribute the usage to in that case.
        if (! $xrayImage || ! $xrayImage->client_id || ! $xrayImage->uploader) {
            return;
        }

        $company = $xrayImage->client->company;

        if (! $this->hasSignedExplicitConsent($xrayImage->client_id)) {
            if (! config('services.kvkk.ai_consent_required', true)) {
                // TEMPORARY bypass, see config/services.php's 'kvkk' block.
                Log::warning('X-ray AI odontogram analysis run without a signed KVKK consent (gate temporarily disabled).', [
                    'xray_image_id' => $xrayImage->id,
                    'client_id' => $xrayImage->client_id,
                ]);
            } else {
                Log::info('Skipped X-ray AI odontogram analysis: KVKK açık rıza not signed for this patient.', [
                    'xray_image_id' => $xrayImage->id,
                ]);

                return;
            }
        }

        try {
            $aiTokenUsage->assertCanUseAiTokens($company);
        } catch (ValidationException) {
            Log::info('Skipped X-ray AI odontogram analysis: AI token cap reached.', [
                'xray_image_id' => $xrayImage->id,
            ]);

            return;
        }

        // Sent as an inline base64 data URI, not a Storage::url() -- the
        // file lives on the private disk (see XrayImageController::file())
        // and OpenAI, fetching from the public internet, could never reach
        // a signed app URL that requires our own session/route context
        // anyway. This also means no plain link to the X-ray is ever
        // generated or logged as part of the AI call.
        $binary = Storage::disk('local')->get($xrayImage->image_path);
        $mimeType = Storage::disk('local')->mimeType($xrayImage->image_path) ?: 'image/jpeg';
        $imageUrl = 'data:'.$mimeType.';base64,'.base64_encode($binary);

        try {
            $result = $plans->analyzeXrayImage($imageUrl);
        } catch (Throwable $e) {
            Log::error('X-ray AI odontogram analysis failed.', [
                'xray_image_id' => $xrayImage->id,
                'error' => $e->getMessage(),
            ]);

            return;
        }

        $aiTokenUsage->recordUsage(
            $company,
            $xrayImage->uploader,
            $xrayImage->client,
            'xray_odontogram_analysis',
            (string) config('services.openai.chat_model', 'gpt-4o-mini'),
            (int) $result['usage']['prompt_tokens'],
            (int) $result['usage']['completion_tokens'],
        );

        $xrayImage->update([
            'ai_odontogram_status' => $result['odontogram_status'],
            'ai_analyzed_at' => now(),
        ]);
    }

    /**
     * Same gate as RequiresKvkkConsent (route middleware) enforces on the
     * chat-based AI endpoints -- duplicated rather than shared, since this
     * runs from a queued job with no HTTP request/route to attach
     * middleware to.
     */
    protected function hasSignedExplicitConsent(?int $clientId): bool
    {
        if (! $clientId) {
            return false;
        }

        return ClientConsent::query()
            ->where('client_id', $clientId)
            ->whereHas('template', fn ($query) => $query->where('kind', ConsentTemplate::KIND_KVKK_EXPLICIT_CONSENT))
            ->exists();
    }
}
