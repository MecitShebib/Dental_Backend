<?php

namespace App\Http\Requests\User;

use App\Enums\UserStatus;
use App\Http\Requests\Concerns\ScopesErrorsToModal;
use App\Rules\ValidPhone;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class StoreUserRequest extends FormRequest
{
    // Only affects the redirect-back-with-errors path Laravel already takes
    // for a non-JSON request (the admin panel's plain HTML forms) -- the
    // mobile API's own JSON 422 response (Api\UserController, which also
    // uses this same request class) never runs failedValidation()'s
    // redirect branch at all, so it's unaffected either way.
    use ScopesErrorsToModal;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Nullable, not required: the mobile API's store() (Api\UserController)
            // never sends this -- it auto-fills company_id from the authenticated
            // user's own company and ignores whatever's here. The admin panel's
            // store() (Admin\UserController) always sends it and needs the key to
            // exist in validated() so accessing it doesn't throw; "nullable" (vs.
            // "sometimes") guarantees that regardless of which caller omits it.
            'company_id' => ['nullable', 'integer', 'exists:companies,id'],
            // Required from the admin panel (it always submits company_id
            // and has a real branch picker, see Admin\CompanyController::show()
            // and the Create/Update User modals) -- still nullable for the
            // mobile API's own self-service "Add User" (Settings > Users),
            // which has no such requirement today. Scoped against the
            // *target* company_id when one was submitted (the admin case),
            // falling back to the acting user's own company (the API case,
            // where company_id is never sent) -- was previously always
            // scoped to the acting user's company, which for a project admin
            // (company_id null) meant no branch_id could ever pass here.
            'branch_id' => [
                $this->routeIs('admin.*') ? 'required' : 'nullable',
                'integer',
                Rule::exists('branches', 'id')->where(
                    fn ($query) => $query->where('company_id', $this->input('company_id') ?: $this->user()?->company_id)
                ),
            ],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            // Global uniqueness (not scoped to company_id), same as email
            // above -- phone is what the OTP login flow looks a user up by
            // before it knows which company they belong to, so two users
            // sharing one phone number (even across different companies)
            // makes login ambiguous/broken for both of them.
            'phone' => ['nullable', 'string', 'max:50', new ValidPhone, 'unique:users,phone'],
            'password' => ['required', 'string', Password::min(8)->mixedCase()->numbers()->symbols()],
            'branch_name' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', Rule::enum(UserStatus::class)],
            'is_doctor' => ['nullable', 'boolean'],
            'specialty_id' => ['nullable', 'integer', 'exists:specialties,id', 'required_if:is_doctor,true'],
            // Only meaningful for a doctor (the AI assistant is never shown
            // to anyone else) -- no required_if, just defaults true on
            // create (see Api\UserController/Admin\UserController::store())
            // so a non-doctor's row still gets a sensible value.
            'ai_enabled' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string'],
            'role_ids' => ['nullable', 'array'],
            'role_ids.*' => ['integer', 'exists:roles,id'],
            'permission_ids' => ['nullable', 'array'],
            'permission_ids.*' => ['integer', 'exists:permissions,id'],
        ];
    }
}
