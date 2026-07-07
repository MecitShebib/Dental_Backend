<?php

namespace App\Services;

use App\Models\Client;
use App\Models\Company;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AiTokenUsageService
{
    public function assertCanUseAiTokens(Company $company): void
    {
        $subscription = $company->currentSubscription()->first();

        if (! $subscription) {
            throw ValidationException::withMessages([
                'ai_tokens' => ['This company does not have an active subscription.'],
            ]);
        }

        if ($subscription->max_ai_tokens !== null && $subscription->ai_tokens_used >= $subscription->max_ai_tokens) {
            throw ValidationException::withMessages([
                'ai_tokens' => ['The AI token usage limit for this subscription has been reached. Please raise the limit or upgrade the subscription.'],
            ]);
        }
    }

    public function recordUsage(
        Company $company,
        User $user,
        ?Client $client,
        string $action,
        string $model,
        int $promptTokens,
        int $completionTokens
    ): void {
        DB::transaction(function () use ($company, $user, $client, $action, $model, $promptTokens, $completionTokens) {
            $subscription = $company->currentSubscription()->first();

            $company->aiUsageLogs()->create([
                'subscription_id' => $subscription?->id,
                'user_id' => $user->id,
                'client_id' => $client?->id,
                'action' => $action,
                'model' => $model,
                'prompt_tokens' => $promptTokens,
                'completion_tokens' => $completionTokens,
                'total_tokens' => $promptTokens + $completionTokens,
            ]);

            $subscription?->increment('ai_tokens_used', $promptTokens + $completionTokens);
        });
    }
}
