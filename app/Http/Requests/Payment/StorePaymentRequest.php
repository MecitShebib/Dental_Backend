<?php

namespace App\Http\Requests\Payment;

use App\Enums\PaymentMethod;
use App\Models\Client;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

class StorePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'visit_id' => ['nullable', 'integer', $this->visitBelongsToClientRule()],
            'payment_date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'payment_method' => ['required', Rule::enum(PaymentMethod::class)],
            'notes' => ['nullable', 'string'],
        ];
    }

    /**
     * A bare `exists:visits,id` let a payment be attached to any visit in the
     * database -- including another patient's, or another company's, since
     * the rule runs raw SQL that Visit's company scope never sees. The
     * payment is always created under the route-bound client, so the visit
     * has to belong to that same client (which transitively scopes it to the
     * right company too).
     */
    protected function visitBelongsToClientRule(): Exists|string
    {
        $client = $this->route('client');

        if (! $client instanceof Client) {
            return 'exists:visits,id';
        }

        return Rule::exists('visits', 'id')
            ->where('client_id', $client->id)
            ->whereNull('deleted_at');
    }
}
