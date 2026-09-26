@extends('admin.layout', ['title' => $company->name])

@section('content')
    <section class="hero">
        <h2>{{ $company->name }} @if ($company->trashed())<span class="status status-danger">deleted</span>@endif</h2>
        <p>Project admin view for this company. From here you can manage the company details, its subscriptions, and all users assigned to it.</p>
        @if ($company->trashed())
            <div class="actions-row">
                <a class="btn-link" href="{{ route('admin.companies.index') }}">Back to Companies</a>
                <button class="btn btn-soft" type="button" data-open-modal="company-restore-modal">Restore Company</button>
            </div>
        @else
            <div class="actions-row">
                <a class="btn-link" href="{{ route('admin.companies.index') }}">Back to Companies</a>
                <button class="btn btn-soft" type="button" data-open-modal="company-update-modal">Update Company</button>
                <button class="btn" type="button" data-open-modal="create-user-modal">Create User</button>
                <button class="btn" type="button" data-open-modal="create-subscription-modal">Create Subscription</button>
            </div>
        @endif
    </section>

    <section class="cards">
        <div class="card">
            <strong>Status</strong>
            <div>{{ $company->status }}</div>
        </div>
        <div class="card">
            <strong>Total Users</strong>
            <div>{{ $company->users->count() }}</div>
        </div>
        <div class="card">
            <strong>Active Users</strong>
            <div>{{ $company->users->where('status', 'active')->count() }}</div>
        </div>
        <div class="card">
            <strong>Current Subscription</strong>
            <div>
                @if ($company->currentSubscription)
                    {{ $company->currentSubscription->plan_name }}<br>
                    <span class="muted">{{ $company->currentSubscription->active_users }}/{{ $company->currentSubscription->max_users }} users</span><br>
                    <span class="muted">{{ $company->branches()->count() }}/{{ $company->currentSubscription->max_branches }} branches</span><br>
                    <span class="muted">{{ $company->currentSubscription->ai_tokens_used }}/{{ $company->currentSubscription->max_ai_tokens ?? '∞' }} AI tokens</span>
                @else
                    No active subscription
                @endif
            </div>
        </div>
    </section>

    <section class="panel">
        <div class="toolbar">
            <h3>Company Users</h3>
            @unless ($company->trashed())
                <button class="btn" type="button" data-open-modal="create-user-modal">Create User</button>
            @endunless
        </div>
        <table>
            <thead>
                <tr>
                    <th>User</th>
                    <th>Status</th>
                    <th>Role</th>
                    <th>Doctor</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($users as $user)
                    <tr>
                        <td>
                            <strong>{{ $user->name }}</strong><br>
                            <small>{{ $user->email }}</small>
                        </td>
                        <td>
                            <span class="status">{{ $user->status->value ?? $user->status }}</span>
                            @if ($user->trashed())
                                <span class="status status-danger">deleted</span>
                            @endif
                        </td>
                        <td>{{ $user->roles->pluck('name')->join(', ') ?: 'No role' }}</td>
                        <td>
                            {{ $user->is_doctor ? 'Yes' : 'No' }}
                            @if ($user->is_doctor)
                                <br><small>AI: {{ $user->ai_enabled ? 'Yes' : 'No' }}</small>
                            @endif
                        </td>
                        <td>
                            <div class="actions-row table-actions">
                                @if ($user->trashed())
                                    <button class="btn btn-soft" type="button" data-open-modal="restore-user-{{ $user->id }}">Restore</button>
                                @else
                                    <button class="btn-muted" type="button" data-open-modal="toggle-user-{{ $user->id }}">
                                        Make {{ ($user->status->value ?? $user->status) === 'active' ? 'Inactive' : 'Active' }}
                                    </button>
                                    <button class="btn btn-soft" type="button" data-open-modal="update-user-{{ $user->id }}">Update</button>
                                    <button class="btn btn-danger" type="button" data-open-modal="delete-user-{{ $user->id }}">Delete</button>
                                @endif
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </section>

    <section class="panel">
        <div class="toolbar">
            <h3>Company Subscriptions</h3>
            @unless ($company->trashed())
                <button class="btn" type="button" data-open-modal="create-subscription-modal">Create Subscription</button>
            @endunless
        </div>
        <table>
            <thead>
                <tr>
                    <th>Specialty</th>
                    <th>Plan</th>
                    <th>Status</th>
                    <th>Period</th>
                    <th>Users Limit</th>
                    <th>AI Tokens</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($subscriptions as $subscription)
                    <tr>
                        <td>{{ $subscription->specialty->brand_name ?? '—' }}</td>
                        <td>{{ $subscription->plan_name }}</td>
                        <td>
                            <span class="status">{{ $subscription->status->value ?? $subscription->status }}</span>
                            @if ($subscription->trashed())
                                <span class="status status-danger">deleted</span>
                            @endif
                        </td>
                        <td>
                            {{ $subscription->starts_at?->format('Y-m-d') }}<br>
                            <small>{{ $subscription->ends_at?->format('Y-m-d') ?? 'Open end' }}</small>
                        </td>
                        <td>{{ $subscription->active_users }}/{{ $subscription->max_users }}</td>
                        <td>{{ $subscription->ai_tokens_used }}/{{ $subscription->max_ai_tokens ?? '∞' }}</td>
                        <td>
                            <div class="actions-row table-actions">
                                @if ($subscription->trashed())
                                    <button class="btn btn-soft" type="button" data-open-modal="restore-subscription-{{ $subscription->id }}">Restore</button>
                                @else
                                    <button class="btn-muted" type="button" data-open-modal="toggle-subscription-{{ $subscription->id }}">
                                        Make {{ ($subscription->status->value ?? $subscription->status) === 'active' ? 'Inactive' : 'Active' }}
                                    </button>
                                    <button class="btn btn-soft" type="button" data-open-modal="update-subscription-{{ $subscription->id }}">Update</button>
                                    <button class="btn btn-danger" type="button" data-open-modal="delete-subscription-{{ $subscription->id }}">Delete</button>
                                @endif
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </section>
@endsection

@push('modals')
    <dialog id="company-update-modal" class="modal">
        <div class="modal-card">
            <div class="modal-head">
                <h3>Update Company</h3>
                <button class="close-btn" type="button" data-close-modal>&times;</button>
            </div>
            <form method="POST" action="{{ route('admin.companies.update', $company) }}">
                @csrf
                @method('PUT')
                <input type="hidden" name="_modal_id" value="company-update-modal">
                <input name="name" value="{{ old('name', $company->name) }}" required>
                @error('name', 'company-update-modal') <span class="field-error">{{ $message }}</span> @enderror
                <input name="code" value="{{ old('code', $company->code) }}" required>
                @error('code', 'company-update-modal') <span class="field-error">{{ $message }}</span> @enderror
                <input name="email" type="email" value="{{ old('email', $company->email) }}">
                @error('email', 'company-update-modal') <span class="field-error">{{ $message }}</span> @enderror
                <input name="phone" value="{{ old('phone', $company->phone) }}">
                @error('phone', 'company-update-modal') <span class="field-error">{{ $message }}</span> @enderror
                <textarea name="address">{{ old('address', $company->address) }}</textarea>
                @error('address', 'company-update-modal') <span class="field-error">{{ $message }}</span> @enderror
                <select name="status">
                    <option value="active" @selected(old('status', $company->status) === 'active')>active</option>
                    <option value="inactive" @selected(old('status', $company->status) === 'inactive')>inactive</option>
                </select>
                @error('status', 'company-update-modal') <span class="field-error">{{ $message }}</span> @enderror
                <textarea name="notes">{{ old('notes', $company->notes) }}</textarea>
                @error('notes', 'company-update-modal') <span class="field-error">{{ $message }}</span> @enderror
                <button class="btn" type="submit">Update Company</button>
            </form>
        </div>
    </dialog>

    <dialog id="company-restore-modal" class="modal">
        <div class="modal-card">
            <div class="modal-head">
                <h3>Restore Company</h3>
                <button class="close-btn" type="button" data-close-modal>&times;</button>
            </div>
            <p>Restore <strong>{{ $company->name }}</strong>? Its users and subscriptions that were deleted along with it are restored too.</p>
            <form method="POST" action="{{ route('admin.companies.restore', $company) }}">
                @csrf
                @method('PATCH')
                <button class="btn" type="submit">Restore Company</button>
            </form>
        </div>
    </dialog>

    <dialog id="create-user-modal" class="modal">
        <div class="modal-card">
            <div class="modal-head">
                <h3>Create User For {{ $company->name }}</h3>
                <button class="close-btn" type="button" data-close-modal>&times;</button>
            </div>
            <form method="POST" action="{{ route('admin.users.store') }}">
                @csrf
                <input type="hidden" name="_modal_id" value="create-user-modal">
                <input type="hidden" name="company_id" value="{{ $company->id }}">
                <input name="name" placeholder="Name" value="{{ old('name') }}" required>
                @error('name', 'create-user-modal') <span class="field-error">{{ $message }}</span> @enderror
                <input name="email" type="email" placeholder="Email" value="{{ old('email') }}" required>
                @error('email', 'create-user-modal') <span class="field-error">{{ $message }}</span> @enderror
                <input name="phone" placeholder="Phone" value="{{ old('phone') }}">
                @error('phone', 'create-user-modal') <span class="field-error">{{ $message }}</span> @enderror
                <input name="password" type="password" placeholder="Password (min 8, upper+lower+number+symbol)" required>
                @error('password', 'create-user-modal') <span class="field-error">{{ $message }}</span> @enderror
                <select name="job_title">
                    <option value="" @selected(! old('job_title'))>No job title</option>
                    @foreach ($roles as $role)
                        <option value="{{ $role->name }}" @selected(old('job_title') === $role->name)>{{ $role->name }}</option>
                    @endforeach
                </select>
                <select name="branch_id" required>
                    <option value="" disabled @selected(! old('branch_id'))>Select branch…</option>
                    @foreach ($branches as $branch)
                        <option value="{{ $branch->id }}" @selected((string) old('branch_id') === (string) $branch->id)>{{ $branch->name }}</option>
                    @endforeach
                </select>
                @error('branch_id', 'create-user-modal') <span class="field-error">{{ $message }}</span> @enderror
                <select name="status">
                    <option value="active" @selected(old('status', 'active') === 'active')>active</option>
                    <option value="inactive" @selected(old('status') === 'inactive')>inactive</option>
                    <option value="suspended" @selected(old('status') === 'suspended')>suspended</option>
                </select>
                <select name="is_doctor">
                    <option value="0" @selected(old('is_doctor', '0') === '0')>Not Doctor</option>
                    <option value="1" @selected(old('is_doctor') === '1')>Doctor</option>
                </select>
                <select name="ai_enabled">
                    <option value="1" @selected(old('ai_enabled', '1') === '1')>AI Assistant: Enabled</option>
                    <option value="0" @selected(old('ai_enabled') === '0')>AI Assistant: Disabled</option>
                </select>
                <select name="specialty_id">
                    <option value="">No specialty (staff only)</option>
                    @foreach ($specialties as $specialty)
                        <option value="{{ $specialty->id }}" @selected((string) old('specialty_id') === (string) $specialty->id)>{{ $specialty->brand_name }}</option>
                    @endforeach
                </select>
                @error('specialty_id', 'create-user-modal') <span class="field-error">{{ $message }}</span> @enderror
                <select name="role_ids[]">
                    @foreach ($roles as $role)
                        <option value="{{ $role->id }}" @selected(in_array((string) $role->id, old('role_ids', []), true))>{{ $role->name }}</option>
                    @endforeach
                </select>
                <fieldset>
                    <legend>Access Permissions</legend>
                    @foreach ($permissions as $permission)
                        <label class="checkbox-option">
                            <input type="checkbox" name="permission_ids[]" value="{{ $permission->id }}" @checked(in_array((string) $permission->id, old('permission_ids', []), true))>
                            {{ $permission->name }}
                        </label>
                    @endforeach
                </fieldset>
                <textarea name="notes" placeholder="Notes">{{ old('notes') }}</textarea>
                <button class="btn" type="submit">Create User</button>
            </form>
        </div>
    </dialog>

    <dialog id="create-subscription-modal" class="modal">
        <div class="modal-card">
            <div class="modal-head">
                <h3>Create Subscription For {{ $company->name }}</h3>
                <button class="close-btn" type="button" data-close-modal>&times;</button>
            </div>
            <form method="POST" action="{{ route('admin.subscriptions.store') }}">
                @csrf
                <input type="hidden" name="_modal_id" value="create-subscription-modal">
                <input type="hidden" name="company_id" value="{{ $company->id }}">
                <fieldset>
                    <legend>Specialties (select one or more)</legend>
                    @foreach ($specialties as $specialty)
                        <label class="checkbox-option">
                            <input type="checkbox" name="specialty_ids[]" value="{{ $specialty->id }}" @checked(old('specialty_ids') ? in_array((string) $specialty->id, old('specialty_ids', []), true) : $specialty->key === 'dental')>
                            {{ $specialty->brand_name }} ({{ $specialty->name_en }}){{ $specialty->is_active ? '' : ' — not built yet' }}
                        </label>
                    @endforeach
                </fieldset>
                @error('specialty_ids', 'create-subscription-modal') <span class="field-error">{{ $message }}</span> @enderror
                <input name="plan_name" placeholder="Plan name" value="{{ old('plan_name') }}" required>
                @error('plan_name', 'create-subscription-modal') <span class="field-error">{{ $message }}</span> @enderror
                <select name="status" required>
                    <option value="active" @selected(old('status', 'active') === 'active')>active</option>
                    <option value="inactive" @selected(old('status') === 'inactive')>inactive</option>
                </select>
                <input type="date" name="starts_at" value="{{ old('starts_at') }}" required>
                @error('starts_at', 'create-subscription-modal') <span class="field-error">{{ $message }}</span> @enderror
                <input type="date" name="ends_at" value="{{ old('ends_at') }}">
                @error('ends_at', 'create-subscription-modal') <span class="field-error">{{ $message }}</span> @enderror
                <input type="number" min="1" name="max_users" placeholder="Max users" value="{{ old('max_users') }}" required>
                @error('max_users', 'create-subscription-modal') <span class="field-error">{{ $message }}</span> @enderror
                <input type="number" min="1" name="max_branches" placeholder="Max branches" value="{{ old('max_branches', 1) }}" required>
                @error('max_branches', 'create-subscription-modal') <span class="field-error">{{ $message }}</span> @enderror
                <input type="number" min="0" name="max_ai_tokens" placeholder="Max AI tokens (blank = unlimited)" value="{{ old('max_ai_tokens') }}">
                @error('max_ai_tokens', 'create-subscription-modal') <span class="field-error">{{ $message }}</span> @enderror
                <input type="number" step="0.01" min="0" name="price" placeholder="Price" value="{{ old('price') }}">
                @error('price', 'create-subscription-modal') <span class="field-error">{{ $message }}</span> @enderror
                <textarea name="notes" placeholder="Notes">{{ old('notes') }}</textarea>
                <button class="btn" type="submit">Create Subscription</button>
            </form>
        </div>
    </dialog>

    @foreach ($users as $user)
        <dialog id="toggle-user-{{ $user->id }}" class="modal">
            <div class="modal-card">
                <div class="modal-head">
                    <h3>Change User Status</h3>
                    <button class="close-btn" type="button" data-close-modal>&times;</button>
                </div>
                <p>Change <strong>{{ $user->name }}</strong> to <strong>{{ ($user->status->value ?? $user->status) === 'active' ? 'inactive' : 'active' }}</strong>?</p>
                <form method="POST" action="{{ route('admin.users.toggle-status', $user) }}">
                    @csrf
                    @method('PATCH')
                    <button class="btn" type="submit">Confirm</button>
                </form>
            </div>
        </dialog>

        {{-- Same per-row scoping as update-company-{id} on the companies
             index page -- this modal repeats once per user in the loop
             above, all with identical field names. --}}
        @php($userModalId = 'update-user-'.$user->id)
        @php($userReopened = old('_modal_id') === $userModalId)
        <dialog id="{{ $userModalId }}" class="modal">
            <div class="modal-card">
                <div class="modal-head">
                    <h3>Update User</h3>
                    <button class="close-btn" type="button" data-close-modal>&times;</button>
                </div>
                <form method="POST" action="{{ route('admin.users.update', $user) }}">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="_modal_id" value="{{ $userModalId }}">
                    <input type="hidden" name="company_id" value="{{ $company->id }}">
                    <input name="name" value="{{ $userReopened ? old('name') : $user->name }}" required>
                    @error('name', $userModalId) <span class="field-error">{{ $message }}</span> @enderror
                    <input name="email" type="email" value="{{ $userReopened ? old('email') : $user->email }}" required>
                    @error('email', $userModalId) <span class="field-error">{{ $message }}</span> @enderror
                    <input name="phone" value="{{ $userReopened ? old('phone') : $user->phone }}">
                    @error('phone', $userModalId) <span class="field-error">{{ $message }}</span> @enderror
                    <input name="password" type="password" placeholder="Leave blank to keep current, or min 8 with upper+lower+number+symbol">
                    @error('password', $userModalId) <span class="field-error">{{ $message }}</span> @enderror
                    <select name="job_title">
                        @php($currentJobTitle = $userReopened ? old('job_title') : $user->job_title)
                        <option value="" @selected(! $currentJobTitle)>No job title</option>
                        @foreach ($roles as $role)
                            <option value="{{ $role->name }}" @selected($currentJobTitle === $role->name)>{{ $role->name }}</option>
                        @endforeach
                    </select>
                    <select name="branch_id" required>
                        @php($currentBranchId = $userReopened ? old('branch_id') : $user->branch_id)
                        <option value="" disabled @selected(! $currentBranchId)>Select branch…</option>
                        @foreach ($branches as $branch)
                            <option value="{{ $branch->id }}" @selected((string) $currentBranchId === (string) $branch->id)>{{ $branch->name }}</option>
                        @endforeach
                    </select>
                    @error('branch_id', $userModalId) <span class="field-error">{{ $message }}</span> @enderror
                    <select name="status">
                        @php($currentStatus = $userReopened ? old('status') : ($user->status->value ?? $user->status))
                        <option value="active" @selected($currentStatus === 'active')>active</option>
                        <option value="inactive" @selected($currentStatus === 'inactive')>inactive</option>
                        <option value="suspended" @selected($currentStatus === 'suspended')>suspended</option>
                    </select>
                    <select name="is_doctor">
                        @php($currentIsDoctor = $userReopened ? old('is_doctor') === '1' : (bool) $user->is_doctor)
                        <option value="0" @selected(! $currentIsDoctor)>Not Doctor</option>
                        <option value="1" @selected($currentIsDoctor)>Doctor</option>
                    </select>
                    <select name="ai_enabled">
                        @php($currentAiEnabled = $userReopened ? old('ai_enabled', '1') === '1' : (bool) $user->ai_enabled)
                        <option value="1" @selected($currentAiEnabled)>AI Assistant: Enabled</option>
                        <option value="0" @selected(! $currentAiEnabled)>AI Assistant: Disabled</option>
                    </select>
                    <select name="specialty_id">
                        @php($currentSpecialtyId = $userReopened ? old('specialty_id') : $user->specialty_id)
                        <option value="">No specialty (staff only)</option>
                        @foreach ($specialties as $specialty)
                            <option value="{{ $specialty->id }}" @selected((string) $currentSpecialtyId === (string) $specialty->id)>{{ $specialty->brand_name }}</option>
                        @endforeach
                    </select>
                    @error('specialty_id', $userModalId) <span class="field-error">{{ $message }}</span> @enderror
                    <select name="role_ids[]">
                        @php($currentRoleIds = $userReopened ? old('role_ids', []) : $user->roles->pluck('id')->map(fn ($id) => (string) $id)->all())
                        @foreach ($roles as $role)
                            <option value="{{ $role->id }}" @selected(in_array((string) $role->id, $currentRoleIds, true))>{{ $role->name }}</option>
                        @endforeach
                    </select>
                    <fieldset>
                        <legend>Access Permissions</legend>
                        @php($currentPermissionIds = $userReopened ? old('permission_ids', []) : $user->permissions->pluck('id')->map(fn ($id) => (string) $id)->all())
                        @foreach ($permissions as $permission)
                            <label class="checkbox-option">
                                <input type="checkbox" name="permission_ids[]" value="{{ $permission->id }}" @checked(in_array((string) $permission->id, $currentPermissionIds, true))>
                                {{ $permission->name }}
                            </label>
                        @endforeach
                    </fieldset>
                    <textarea name="notes">{{ $userReopened ? old('notes') : $user->notes }}</textarea>
                    <button class="btn" type="submit">Update User</button>
                </form>
            </div>
        </dialog>

        <dialog id="delete-user-{{ $user->id }}" class="modal">
            <div class="modal-card">
                <div class="modal-head">
                    <h3>Delete User</h3>
                    <button class="close-btn" type="button" data-close-modal>&times;</button>
                </div>
                <p>Delete <strong>{{ $user->name }}</strong> from this company? Not permanent -- can be restored from this page afterward.</p>
                <form method="POST" action="{{ route('admin.users.destroy', $user) }}">
                    @csrf
                    @method('DELETE')
                    <button class="btn btn-danger" type="submit">Delete User</button>
                </form>
            </div>
        </dialog>

        <dialog id="restore-user-{{ $user->id }}" class="modal">
            <div class="modal-card">
                <div class="modal-head">
                    <h3>Restore User</h3>
                    <button class="close-btn" type="button" data-close-modal>&times;</button>
                </div>
                <p>Restore <strong>{{ $user->name }}</strong>?</p>
                <form method="POST" action="{{ route('admin.users.restore', $user) }}">
                    @csrf
                    @method('PATCH')
                    <button class="btn" type="submit">Restore User</button>
                </form>
            </div>
        </dialog>
    @endforeach

    @foreach ($subscriptions as $subscription)
        <dialog id="toggle-subscription-{{ $subscription->id }}" class="modal">
            <div class="modal-card">
                <div class="modal-head">
                    <h3>Change Subscription Status</h3>
                    <button class="close-btn" type="button" data-close-modal>&times;</button>
                </div>
                <p>Change <strong>{{ $subscription->plan_name }}</strong> to <strong>{{ ($subscription->status->value ?? $subscription->status) === 'active' ? 'inactive' : 'active' }}</strong>?</p>
                <form method="POST" action="{{ route('admin.subscriptions.toggle-status', $subscription) }}">
                    @csrf
                    @method('PATCH')
                    <button class="btn" type="submit">Confirm</button>
                </form>
            </div>
        </dialog>

        {{-- Same per-row scoping as update-company-{id}/update-user-{id}
             above -- this modal repeats once per subscription in the loop
             above, all with identical field names. --}}
        @php($subscriptionModalId = 'update-subscription-'.$subscription->id)
        @php($subscriptionReopened = old('_modal_id') === $subscriptionModalId)
        <dialog id="{{ $subscriptionModalId }}" class="modal">
            <div class="modal-card">
                <div class="modal-head">
                    <h3>Update Subscription</h3>
                    <button class="close-btn" type="button" data-close-modal>&times;</button>
                </div>
                <form method="POST" action="{{ route('admin.subscriptions.update', $subscription) }}">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="_modal_id" value="{{ $subscriptionModalId }}">
                    <input type="hidden" name="company_id" value="{{ $company->id }}">
                    <fieldset>
                        <legend>Specialties (this row + any extra to also subscribe to)</legend>
                        @php($currentSpecialtyIds = $subscriptionReopened ? old('specialty_ids', []) : [(string) $subscription->specialty_id])
                        @foreach ($specialties as $specialty)
                            <label class="checkbox-option">
                                <input type="checkbox" name="specialty_ids[]" value="{{ $specialty->id }}" @checked(in_array((string) $specialty->id, $currentSpecialtyIds, true))>
                                {{ $specialty->brand_name }} ({{ $specialty->name_en }}){{ $specialty->is_active ? '' : ' — not built yet' }}
                            </label>
                        @endforeach
                    </fieldset>
                    @error('specialty_ids', $subscriptionModalId) <span class="field-error">{{ $message }}</span> @enderror
                    <input name="plan_name" value="{{ $subscriptionReopened ? old('plan_name') : $subscription->plan_name }}" required>
                    @error('plan_name', $subscriptionModalId) <span class="field-error">{{ $message }}</span> @enderror
                    <select name="status" required>
                        @php($currentSubStatus = $subscriptionReopened ? old('status') : ($subscription->status->value ?? $subscription->status))
                        <option value="active" @selected($currentSubStatus === 'active')>active</option>
                        <option value="inactive" @selected($currentSubStatus === 'inactive')>inactive</option>
                    </select>
                    <input type="date" name="starts_at" value="{{ $subscriptionReopened ? old('starts_at') : $subscription->starts_at?->format('Y-m-d') }}" required>
                    @error('starts_at', $subscriptionModalId) <span class="field-error">{{ $message }}</span> @enderror
                    <input type="date" name="ends_at" value="{{ $subscriptionReopened ? old('ends_at') : $subscription->ends_at?->format('Y-m-d') }}">
                    @error('ends_at', $subscriptionModalId) <span class="field-error">{{ $message }}</span> @enderror
                    <input type="number" min="1" name="max_users" value="{{ $subscriptionReopened ? old('max_users') : $subscription->max_users }}" required>
                    @error('max_users', $subscriptionModalId) <span class="field-error">{{ $message }}</span> @enderror
                    <input type="number" min="1" name="max_branches" value="{{ $subscriptionReopened ? old('max_branches') : $subscription->max_branches }}" placeholder="Max branches" required>
                    @error('max_branches', $subscriptionModalId) <span class="field-error">{{ $message }}</span> @enderror
                    <input type="number" min="0" name="max_ai_tokens" value="{{ $subscriptionReopened ? old('max_ai_tokens') : $subscription->max_ai_tokens }}" placeholder="Max AI tokens (blank = unlimited)">
                    @error('max_ai_tokens', $subscriptionModalId) <span class="field-error">{{ $message }}</span> @enderror
                    <input type="number" step="0.01" min="0" name="price" value="{{ $subscriptionReopened ? old('price') : $subscription->price }}">
                    @error('price', $subscriptionModalId) <span class="field-error">{{ $message }}</span> @enderror
                    <textarea name="notes">{{ $subscriptionReopened ? old('notes') : $subscription->notes }}</textarea>
                    <button class="btn" type="submit">Update Subscription</button>
                </form>
            </div>
        </dialog>

        <dialog id="delete-subscription-{{ $subscription->id }}" class="modal">
            <div class="modal-card">
                <div class="modal-head">
                    <h3>Delete Subscription</h3>
                    <button class="close-btn" type="button" data-close-modal>&times;</button>
                </div>
                <p>Delete <strong>{{ $subscription->plan_name }}</strong> from this company? Not permanent -- can be restored from this page afterward.</p>
                <form method="POST" action="{{ route('admin.subscriptions.destroy', $subscription) }}">
                    @csrf
                    @method('DELETE')
                    <button class="btn btn-danger" type="submit">Delete Subscription</button>
                </form>
            </div>
        </dialog>

        <dialog id="restore-subscription-{{ $subscription->id }}" class="modal">
            <div class="modal-card">
                <div class="modal-head">
                    <h3>Restore Subscription</h3>
                    <button class="close-btn" type="button" data-close-modal>&times;</button>
                </div>
                <p>Restore <strong>{{ $subscription->plan_name }}</strong>?</p>
                <form method="POST" action="{{ route('admin.subscriptions.restore', $subscription) }}">
                    @csrf
                    @method('PATCH')
                    <button class="btn" type="submit">Restore Subscription</button>
                </form>
            </div>
        </dialog>
    @endforeach
@endpush
