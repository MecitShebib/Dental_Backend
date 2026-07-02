@extends('admin.layout', ['title' => 'Companies'])

@section('content')
    <section class="hero">
        <h2>Project Admin Panel</h2>
        <p>This panel is for the main project admin only. From here you can open any company, inspect its users and subscriptions, then create, update, stop, activate, or delete records.</p>
    </section>

    <div class="toolbar">
        <form method="GET" action="{{ route('admin.companies.index') }}">
            <input name="q" placeholder="Search company" value="{{ $filters['q'] ?? '' }}">
            <select name="status">
                <option value="">All statuses</option>
                <option value="active" @selected(($filters['status'] ?? '') === 'active')>active</option>
                <option value="inactive" @selected(($filters['status'] ?? '') === 'inactive')>inactive</option>
            </select>
            <button class="btn-soft" type="submit">Filter</button>
            <a class="btn-muted" href="{{ route('admin.companies.index') }}">Reset</a>
        </form>
        <div class="actions-row">
            <div class="muted">Total companies: {{ $companies->count() }}</div>
            <button class="btn" type="button" data-open-modal="create-company-modal">Create Company</button>
        </div>
    </div>

    <section class="panel">
        <table>
            <thead>
                <tr>
                    <th>Company</th>
                    <th>Status</th>
                    <th>Users</th>
                    <th>Current Subscription</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($companies as $company)
                    <tr>
                        <td>
                            <strong>{{ $company->name }}</strong><br>
                            <small>{{ $company->code }}</small><br>
                            <small>{{ $company->email }}</small>
                        </td>
                        <td><span class="status">{{ $company->status }}</span></td>
                        <td>{{ $company->users->count() }} total / {{ $company->users->where('status', 'active')->count() }} active</td>
                        <td>
                            @if ($company->currentSubscription)
                                {{ $company->currentSubscription->plan_name }}<br>
                                <span class="muted">{{ $company->currentSubscription->active_users }}/{{ $company->currentSubscription->max_users }} active users</span>
                            @else
                                <span class="muted">No active subscription</span>
                            @endif
                        </td>
                        <td>
                            <div class="actions-row table-actions">
                                <a class="btn-link" href="{{ route('admin.companies.show', $company) }}">Open Company</a>
                                <button class="btn-muted" type="button" data-open-modal="toggle-company-{{ $company->id }}">
                                    Make {{ $company->status === 'active' ? 'Inactive' : 'Active' }}
                                </button>
                                <button class="btn btn-soft" type="button" data-open-modal="update-company-{{ $company->id }}">Update</button>
                                <button class="btn btn-danger" type="button" data-open-modal="delete-company-{{ $company->id }}">Delete</button>
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </section>
@endsection

@push('modals')
    <dialog id="create-company-modal" class="modal">
        <div class="modal-card">
            <div class="modal-head">
                <h3>Create Company</h3>
                <button class="close-btn" type="button" data-close-modal>&times;</button>
            </div>
            <form method="POST" action="{{ route('admin.companies.store') }}">
                @csrf
                <input name="name" placeholder="Company name" required>
                <input name="code" placeholder="Company code" required>
                <input name="email" type="email" placeholder="Email">
                <input name="phone" placeholder="Phone">
                <textarea name="address" placeholder="Address"></textarea>
                <select name="status">
                    <option value="active">active</option>
                    <option value="inactive">inactive</option>
                </select>
                <textarea name="notes" placeholder="Notes"></textarea>
                <button class="btn" type="submit">Create Company</button>
            </form>
        </div>
    </dialog>

    @foreach ($companies as $company)
        <dialog id="toggle-company-{{ $company->id }}" class="modal">
            <div class="modal-card">
                <div class="modal-head">
                    <h3>Change Company Status</h3>
                    <button class="close-btn" type="button" data-close-modal>&times;</button>
                </div>
                <p>Change <strong>{{ $company->name }}</strong> to <strong>{{ $company->status === 'active' ? 'inactive' : 'active' }}</strong>?</p>
                <form method="POST" action="{{ route('admin.companies.toggle-status', $company) }}">
                    @csrf
                    @method('PATCH')
                    <button class="btn" type="submit">Confirm</button>
                </form>
            </div>
        </dialog>

        <dialog id="update-company-{{ $company->id }}" class="modal">
            <div class="modal-card">
                <div class="modal-head">
                    <h3>Update {{ $company->name }}</h3>
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

        <dialog id="delete-company-{{ $company->id }}" class="modal">
            <div class="modal-card">
                <div class="modal-head">
                    <h3>Delete Company</h3>
                    <button class="close-btn" type="button" data-close-modal>&times;</button>
                </div>
                <p>Delete <strong>{{ $company->name }}</strong>? This removes the company record.</p>
                <form method="POST" action="{{ route('admin.companies.destroy', $company) }}">
                    @csrf
                    @method('DELETE')
                    <button class="btn btn-danger" type="submit">Delete Company</button>
                </form>
            </div>
        </dialog>
    @endforeach
@endpush
