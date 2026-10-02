<?php

namespace App\Http\Requests\Subscription;

use App\Enums\SubscriptionStatus;
use App\Http\Requests\Concerns\ScopesErrorsToModal;
use App\Models\Subscription;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSubscriptionRequest extends FormRequest
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
