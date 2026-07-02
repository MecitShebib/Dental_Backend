@extends('admin.layout', ['title' => 'Users'])

@section('content')
    <section class="hero">
        <h2>Users Management</h2>
        <p>Create users, edit their details, and toggle them between active and inactive.</p>
    </section>

    <div class="grid-2">
        <section class="panel">
            <h3>Add User</h3>
            <form method="POST" action="{{ route('admin.users.store') }}">
                @csrf
                <input name="name" placeholder="Name" required>
                <input name="email" type="email" placeholder="Email" required>
                <select name="company_id" required>
                    <option value="">Select company</option>
                    @foreach ($companies as $company)
                        <option value="{{ $company->id }}">{{ $company->name }}</option>
                    @endforeach
                </select>
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
                <select name="role_ids[]" multiple>
                    @foreach ($roles as $role)
                        <option value="{{ $role->id }}">{{ $role->name }}</option>
                    @endforeach
                </select>
                <textarea name="notes" placeholder="Notes"></textarea>
                <button class="btn" type="submit">Create User</button>
            </form>
        </section>

        <section class="panel">
            <h3>Users List</h3>
            <table>
                <thead>
                    <tr>
                        <th>User</th>
                        <th>Status</th>
                        <th>Subscription</th>
                        <th>Role</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($users as $user)
                        <tr>
                            <td>
                                <strong>{{ $user->name }}</strong><br>
                                <small>{{ $user->email }}</small><br>
                                <small>{{ $user->company?->name }}</small>
                            </td>
                            <td><span class="status">{{ $user->status->value ?? $user->status }}</span></td>
                            <td>
                                @if ($user->company?->currentSubscription)
                                    <strong>{{ $user->company->currentSubscription->plan_name }}</strong><br>
                                    <small>{{ $user->company->currentSubscription->active_users }}/{{ $user->company->currentSubscription->max_users }}</small>
                                @else
                                    <small>No active subscription</small>
                                @endif
                            </td>
                            <td>{{ $user->roles->pluck('name')->join(', ') }}</td>
                            <td>
                                <form method="POST" action="{{ route('admin.users.update', $user) }}">
                                    @csrf
                                    @method('PUT')
                                    <input name="name" value="{{ $user->name }}" required>
                                    <input name="email" type="email" value="{{ $user->email }}" required>
                                    <select name="company_id" required>
                                        @foreach ($companies as $company)
                                            <option value="{{ $company->id }}" @selected($user->company_id === $company->id)>{{ $company->name }}</option>
                                        @endforeach
                                    </select>
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
                                    <select name="role_ids[]" multiple>
                                        @foreach ($roles as $role)
                                            <option value="{{ $role->id }}" @selected($user->roles->contains('id', $role->id))>{{ $role->name }}</option>
                                        @endforeach
                                    </select>
                                    <textarea name="notes">{{ $user->notes }}</textarea>
                                    <button class="btn btn-soft inline" type="submit">Update</button>
                                </form>
                                <form method="POST" action="{{ route('admin.users.destroy', $user) }}" class="inline" onsubmit="return confirm('Delete this user?')">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn btn-danger" type="submit">Delete</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </section>
    </div>
@endsection
