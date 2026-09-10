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
                        <td>
                            <span class="status">{{ $company->status }}</span>
                            @if ($company->trashed())
                                <span class="status status-danger">deleted</span>
                            @endif
                        </td>
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
                                @if ($company->trashed())
                                    <button class="btn btn-soft" type="button" data-open-modal="restore-company-{{ $company->id }}">Restore</button>
                                @else
                                    <button class="btn-muted" type="button" data-open-modal="toggle-company-{{ $company->id }}">
                                        Make {{ $company->status === 'active' ? 'Inactive' : 'Active' }}
                                    </button>
                                    <button class="btn btn-soft" type="button" data-open-modal="update-company-{{ $company->id }}">Update</button>
                                    <button class="btn btn-danger" type="button" data-open-modal="delete-company-{{ $company->id }}">Delete</button>
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
    <dialog id="create-company-modal" class="modal">
        <div class="modal-card">
            <div class="modal-head">
                <h3>Create Company</h3>
                <button class="close-btn" type="button" data-close-modal>&times;</button>
            </div>
            <form method="POST" action="{{ route('admin.companies.store') }}">
                @csrf
                <input type="hidden" name="_modal_id" value="create-company-modal">
                <input name="name" placeholder="Company name" value="{{ old('name') }}" required>
                @error('name', 'create-company-modal') <span class="field-error">{{ $message }}</span> @enderror
                <input name="code" placeholder="Company code" value="{{ old('code') }}" required>
                @error('code', 'create-company-modal') <span class="field-error">{{ $message }}</span> @enderror
                <input name="email" type="email" placeholder="Email" value="{{ old('email') }}">
                @error('email', 'create-company-modal') <span class="field-error">{{ $message }}</span> @enderror
                <input name="phone" placeholder="Phone" value="{{ old('phone') }}">
                @error('phone', 'create-company-modal') <span class="field-error">{{ $message }}</span> @enderror
                <textarea name="address" placeholder="Address">{{ old('address') }}</textarea>
                @error('address', 'create-company-modal') <span class="field-error">{{ $message }}</span> @enderror
                <select name="status">
                    <option value="active" @selected(old('status', 'active') === 'active')>active</option>
                    <option value="inactive" @selected(old('status') === 'inactive')>inactive</option>
                </select>
                @error('status', 'create-company-modal') <span class="field-error">{{ $message }}</span> @enderror
                <textarea name="notes" placeholder="Notes">{{ old('notes') }}</textarea>
                @error('notes', 'create-company-modal') <span class="field-error">{{ $message }}</span> @enderror
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

        @php
            // This modal (and its form fields/errors) repeats once per row in
            // the loop above, all sharing the same field names -- old()
            // and $errors are both global, not scoped per row, so without
            // this guard a failed update for one company would leak its
            // typed-in values (and error bag, see ScopesErrorsToModal) into
            // every *other* company's identically-named modal on the same
            // page too. Only the one row whose own _modal_id comes back from
            // old() is the one that actually just failed.
            $companyModalId = 'update-company-'.$company->id;
            $companyReopened = old('_modal_id') === $companyModalId;
        @endphp
        <dialog id="{{ $companyModalId }}" class="modal">
            <div class="modal-card">
                <div class="modal-head">
                    <h3>Update {{ $company->name }}</h3>
                    <button class="close-btn" type="button" data-close-modal>&times;</button>
                </div>
                <form method="POST" action="{{ route('admin.companies.update', $company) }}">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="_modal_id" value="{{ $companyModalId }}">
                    <input name="name" value="{{ $companyReopened ? old('name') : $company->name }}" required>
                    @error('name', $companyModalId) <span class="field-error">{{ $message }}</span> @enderror
                    <input name="code" value="{{ $companyReopened ? old('code') : $company->code }}" required>
                    @error('code', $companyModalId) <span class="field-error">{{ $message }}</span> @enderror
                    <input name="email" type="email" value="{{ $companyReopened ? old('email') : $company->email }}">
                    @error('email', $companyModalId) <span class="field-error">{{ $message }}</span> @enderror
                    <input name="phone" value="{{ $companyReopened ? old('phone') : $company->phone }}">
                    @error('phone', $companyModalId) <span class="field-error">{{ $message }}</span> @enderror
                    <textarea name="address">{{ $companyReopened ? old('address') : $company->address }}</textarea>
                    @error('address', $companyModalId) <span class="field-error">{{ $message }}</span> @enderror
                    <select name="status">
                        <option value="active" @selected(($companyReopened ? old('status') : $company->status) === 'active')>active</option>
                        <option value="inactive" @selected(($companyReopened ? old('status') : $company->status) === 'inactive')>inactive</option>
                    </select>
                    @error('status', $companyModalId) <span class="field-error">{{ $message }}</span> @enderror
                    <textarea name="notes">{{ $companyReopened ? old('notes') : $company->notes }}</textarea>
                    @error('notes', $companyModalId) <span class="field-error">{{ $message }}</span> @enderror
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
                <p>Delete <strong>{{ $company->name }}</strong>? Its users and subscriptions are deleted along with it. Nothing is removed permanently -- the company stays listed here (marked Deleted) and can be restored later.</p>
                <form method="POST" action="{{ route('admin.companies.destroy', $company) }}">
                    @csrf
                    @method('DELETE')
                    <button class="btn btn-danger" type="submit">Delete Company</button>
                </form>
            </div>
        </dialog>

        <dialog id="restore-company-{{ $company->id }}" class="modal">
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
    @endforeach
@endpush
