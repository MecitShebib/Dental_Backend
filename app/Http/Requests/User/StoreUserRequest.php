<?php

namespace App\Http\Requests\User;

use App\Enums\UserStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUserRequest extends FormRequest
{
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
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:50'],
            'password' => ['required', 'string', 'min:6'],
            'job_title' => ['nullable', 'string', 'max:255'],
            'branch_name' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', Rule::enum(UserStatus::class)],
            'is_doctor' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string'],
            'role_ids' => ['nullable', 'array'],
            'role_ids.*' => ['integer', 'exists:roles,id'],
            'permission_ids' => ['nullable', 'array'],
            'permission_ids.*' => ['integer', 'exists:permissions,id'],
        ];
    }
}
