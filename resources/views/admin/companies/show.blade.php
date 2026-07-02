@extends('admin.layout', ['title' => $company->name])

@section('content')
    <section class="hero">
        <h2>{{ $company->name }}</h2>
        <p>Project admin view for this company. From here you can manage the company details, its subscriptions, and all users assigned to it.</p>
        <div class="actions-row">
            <a class="btn-link" href="{{ route('admin.companies.index') }}">Back to Companies</a>
            <button class="btn btn-soft" type="button" data-open-modal="company-update-modal">Update Company</button>
            <button class="btn" type="button" data-open-modal="create-user-modal">Create User</button>
            <button class="btn" type="button" data-open-modal="create-subscription-modal">Create Subscription</button>
        </div>
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
                    <span class="muted">{{ $company->currentSubscription->active_users }}/{{ $company->currentSubscription->max_users }}</span>
                @else
                    No active subscription
                @endif
            </div>
        </div>
    </section>

    <section class="panel">
        <div class="toolbar">
            <h3>Company Users</h3>
            <button class="btn" type="button" data-open-modal="create-user-modal">Create User</button>
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
                        <td><span class="status">{{ $user->status->value ?? $user->status }}</span></td>
                        <td>{{ $user->roles->pluck('name')->join(', ') ?: 'No role' }}</td>
                        <td>{{ $user->is_doctor ? 'Yes' : 'No' }}</td>
                        <td>
                            <div class="actions-row table-actions">
                                <button class="btn-muted" type="button" data-open-modal="toggle-user-{{ $user->id }}">
                                    Make {{ ($user->status->value ?? $user->status) === 'active' ? 'Inactive' : 'Active' }}
                                </button>
                                <button class="btn btn-soft" type="button" data-open-modal="update-user-{{ $user->id }}">Update</button>
                                <button class="btn btn-danger" type="button" data-open-modal="delete-user-{{ $user->id }}">Delete</button>
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
            <button class="btn" type="button" data-open-modal="create-subscription-modal">Create Subscription</button>
        </div>
        <table>
            <thead>
                <tr>
                    <th>Plan</th>
                    <th>Status</th>
                    <th>Period</th>
                    <th>Users Limit</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($subscriptions as $subscription)
                    <tr>
                        <td>{{ $subscription->plan_name }}</td>
                        <td><span class="status">{{ $subscription->status->value ?? $subscription->status }}</span></td>
                        <td>
                            {{ $subscription->starts_at?->format('Y-m-d') }}<br>
                            <small>{{ $subscription->ends_at?->format('Y-m-d') ?? 'Open end' }}</small>
                        </td>
                        <td>{{ $subscription->active_users }}/{{ $subscription->max_users }}</td>
                        <td>
                            <div class="actions-row table-actions">
                                <button class="btn-muted" type="button" data-open-modal="toggle-subscription-{{ $subscription->id }}">
                                    Make {{ ($subscription->status->value ?? $subscription->status) === 'active' ? 'Inactive' : 'Active' }}
                                </button>
                                <button class="btn btn-soft" type="button" data-open-modal="update-subscription-{{ $subscription->id }}">Update</button>
                                <button class="btn btn-danger" type="button" data-open-modal="delete-subscription-{{ $subscription->id }}">Delete</button>
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
                <input name="name" value="{{ $company->name }}" required>
                <input name="code" value="{{ $company->code }}" required>
                <input name="email" type="email" value="{{ $company->email }}">
                <input name="phone" value="{{ $company->phone }}">
                <textarea name="address">{{ $company->address }}</textarea>
                <select name="status">
                    <option value="active" @selected($company->status === 'active')>active</option>
                    <option value="inactive" @selected($company->status === 'inactive')>inactive</option>
                </select>
                <textarea name="notes">{{ $company->notes }}</textarea>
                <button class="btn" type="submit">Update Company</button>
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
                <input type="hidden" name="company_id" value="{{ $company->id }}">
                <input name="name" placeholder="Name" required>
                <input name="email" type="email" placeholder="Email" required>
                <input name="phone" placeholder="Phone">
                <input name="password" type="password" placeholder="Password" required>
                <input name="job_title" placeholder="Job title">
                <input name="branch_name" placeholder="Branch name">
                <select name="status">
                    <option value="active">active</option>
                    <option value="inactive">inactive</option>
                    <option value="suspended">suspended</option>
                </select>
                <select name="is_doctor">
                    <option value="0">Not Doctor</option>
                    <option value="1">Doctor</option>
                </select>
                <select name="role_ids[]">
                    @foreach ($roles as $role)
                        <option value="{{ $role->id }}">{{ $role->name }}</option>
                    @endforeach
                </select>
                <textarea name="notes" placeholder="Notes"></textarea>
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
                <input type="hidden" name="company_id" value="{{ $company->id }}">
                <input name="plan_name" placeholder="Plan name" required>
                <select name="status" required>
                    <option value="active">active</option>
                    <option value="inactive">inactive</option>
                </select>
                <input type="date" name="starts_at" required>
                <input type="date" name="ends_at">
                <input type="number" min="1" name="max_users" placeholder="Max users" required>
                <input type="number" step="0.01" min="0" name="price" placeholder="Price">
                <textarea name="notes" placeholder="Notes"></textarea>
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

        <dialog id="update-user-{{ $user->id }}" class="modal">
            <div class="modal-card">
                <div class="modal-head">
                    <h3>Update User</h3>
                    <button class="close-btn" type="button" data-close-modal>&times;</button>
                </div>
                <form method="POST" action="{{ route('admin.users.update', $user) }}">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="company_id" value="{{ $company->id }}">
                    <input name="name" value="{{ $user->name }}" required>
                    <input name="email" type="email" value="{{ $user->email }}" required>
                    <input name="phone" value="{{ $user->phone }}">
                    <input name="password" type="password" placeholder="Leave blank to keep current password">
                    <input name="job_title" value="{{ $user->job_title }}">
                    <input name="branch_name" value="{{ $user->branch_name }}">
                    <select name="status">
                        <option value="active" @selected(($user->status->value ?? $user->status) === 'active')>active</option>
                        <option value="inactive" @selected(($user->status->value ?? $user->status) === 'inactive')>inactive</option>
                        <option value="suspended" @selected(($user->status->value ?? $user->status) === 'suspended')>suspended</option>
                    </select>
                    <select name="is_doctor">
                        <option value="0" @selected(! $user->is_doctor)>Not Doctor</option>
                        <option value="1" @selected($user->is_doctor)>Doctor</option>
                    </select>
                    <select name="role_ids[]">
                        @foreach ($roles as $role)
                            <option value="{{ $role->id }}" @selected($user->roles->contains('id', $role->id))>{{ $role->name }}</option>
                        @endforeach
                    </select>
                    <textarea name="notes">{{ $user->notes }}</textarea>
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
                <p>Delete <strong>{{ $user->name }}</strong> from this company?</p>
                <form method="POST" action="{{ route('admin.users.destroy', $user) }}">
                    @csrf
                    @method('DELETE')
                    <button class="btn btn-danger" type="submit">Delete User</button>
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

        <dialog id="update-subscription-{{ $subscription->id }}" class="modal">
            <div class="modal-card">
                <div class="modal-head">
                    <h3>Update Subscription</h3>
                    <button class="close-btn" type="button" data-close-modal>&times;</button>
                </div>
                <form method="POST" action="{{ route('admin.subscriptions.update', $subscription) }}">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="company_id" value="{{ $company->id }}">
                    <input name="plan_name" value="{{ $subscription->plan_name }}" required>
                    <select name="status" required>
                        <option value="active" @selected(($subscription->status->value ?? $subscription->status) === 'active')>active</option>
                        <option value="inactive" @selected(($subscription->status->value ?? $subscription->status) === 'inactive')>inactive</option>
                    </select>
                    <input type="date" name="starts_at" value="{{ $subscription->starts_at?->format('Y-m-d') }}" required>
                    <input type="date" name="ends_at" value="{{ $subscription->ends_at?->format('Y-m-d') }}">
                    <input type="number" min="1" name="max_users" value="{{ $subscription->max_users }}" required>
                    <input type="number" step="0.01" min="0" name="price" value="{{ $subscription->price }}">
                    <textarea name="notes">{{ $subscription->notes }}</textarea>
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
                <p>Delete <strong>{{ $subscription->plan_name }}</strong> from this company?</p>
                <form method="POST" action="{{ route('admin.subscriptions.destroy', $subscription) }}">
                    @csrf
                    @method('DELETE')
                    <button class="btn btn-danger" type="submit">Delete Subscription</button>
                </form>
            </div>
        </dialog>
    @endforeach
@endpush
