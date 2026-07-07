<?php

namespace App\Http\Requests\Subscription;

use App\Enums\SubscriptionStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSubscriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'company_id' => ['required', 'integer', 'exists:companies,id'],
            'plan_name' => ['required', 'string', 'max:255'],
            'status' => ['required', Rule::enum(SubscriptionStatus::class)],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'max_users' => ['required', 'integer', 'min:1'],
            'active_users' => ['nullable', 'integer', 'min:0'],
            'max_ai_tokens' => ['nullable', 'integer', 'min:0'],
            'price' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
