<?php

namespace App\Http\Requests\Payment;

use App\Enums\PaymentMethod;
use App\Models\Payment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

class UpdatePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'visit_id' => ['nullable', 'integer', $this->visitBelongsToClientRule()],
            'payment_date' => ['sometimes', 'required', 'date'],
            'amount' => ['sometimes', 'required', 'numeric', 'min:0.01'],
            'payment_method' => ['sometimes', 'required', Rule::enum(PaymentMethod::class)],
            'notes' => ['nullable', 'string'],
        ];
    }

    /**
     * Same referential-integrity gap StorePaymentRequest closes: the visit a
     * payment points at must belong to the patient that payment is for, not
     * to any row that happens to exist in the visits table.
     */
    protected function visitBelongsToClientRule(): Exists|string
    {
        $payment = $this->route('payment');

        if (! $payment instanceof Payment) {
            return 'exists:visits,id';
        }

        return Rule::exists('visits', 'id')
            ->where('client_id', $payment->client_id)
            ->whereNull('deleted_at');
    }
}
