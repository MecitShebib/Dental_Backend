<?php

namespace App\Http\Requests\Subscription;

use App\Enums\SubscriptionStatus;
use App\Http\Requests\Concerns\ScopesErrorsToModal;
use App\Models\Subscription;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSubscriptionRequest extends FormRequest
{
    use ScopesErrorsToModal;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * The admin form sends `features_submitted` next to its checkboxes, so an
     * all-unticked form still means "no optional features" (an empty list)
     * rather than "field not sent" (which leaves the column untouched).
     */
    protected function prepareForValidation(): void
    {
        if ($this->boolean('features_submitted')) {
            $this->merge(['features' => array_values((array) $this->input('features', []))]);
        }
    }

    public function rules(): array
    {
        return [
            'company_id' => ['required', 'integer', 'exists:companies,id'],
            // Creating a subscription can enroll a company into several
            // specialties at once (same plan details, one row per
            // specialty) -- editing an existing row stays single-specialty,
            // see UpdateSubscriptionRequest.
            'specialty_ids' => ['required', 'array', 'min:1'],
            'specialty_ids.*' => ['integer', 'exists:specialties,id'],
            'plan_name' => ['required', 'string', 'max:255'],
            'status' => ['required', Rule::enum(SubscriptionStatus::class)],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            // Seat caps per type -- the only user limits (max_users was removed 2026-09-28).
            'max_doctors' => ['required', 'integer', 'min:0'],
            'max_assistants' => ['required', 'integer', 'min:0'],
            // Optional features (Subscription::FEATURES) -- see prepareForValidation().
            'features' => ['sometimes', 'array'],
            'features.*' => [Rule::in(Subscription::FEATURES)],
            'active_users' => ['nullable', 'integer', 'min:0'],
            'max_branches' => ['required', 'integer', 'min:1'],
            'max_ai_tokens' => ['nullable', 'integer', 'min:0'],
            'price' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
