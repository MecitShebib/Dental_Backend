<?php

namespace App\Http\Requests\Subscription;

use App\Enums\SubscriptionStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSubscriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'company_id' => ['required', 'integer', 'exists:companies,id'],
            // The subscription being edited keeps its own specialty no
            // matter which ids are submitted here; any id other than its
            // current specialty creates a new sibling row with the same
            // plan details -- see SubscriptionController::update().
            'specialty_ids' => ['required', 'array', 'min:1'],
            'specialty_ids.*' => ['integer', 'exists:specialties,id'],
            'plan_name' => ['required', 'string', 'max:255'],
            'status' => ['required', Rule::enum(SubscriptionStatus::class)],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'max_users' => ['required', 'integer', 'min:1'],
            'active_users' => ['nullable', 'integer', 'min:0'],
            'max_branches' => ['required', 'integer', 'min:1'],
            'max_ai_tokens' => ['nullable', 'integer', 'min:0'],
            'price' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
