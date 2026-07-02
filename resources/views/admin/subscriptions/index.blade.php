@extends('admin.layout', ['title' => 'Subscriptions'])

@section('content')
    <section class="hero">
        <h2>Subscriptions Management</h2>
        <p>Create, stop, edit, or delete subscriptions linked to each user.</p>
    </section>

    <div class="grid-2">
        <section class="panel">
            <h3>Create Subscription</h3>
            <form method="POST" action="{{ route('admin.subscriptions.store') }}">
                @csrf
                <select name="company_id" required>
                    <option value="">Select company</option>
                    @foreach ($companies as $company)
                        <option value="{{ $company->id }}">{{ $company->name }}</option>
                    @endforeach
                </select>
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
        </section>

        <section class="panel">
            <h3>Subscriptions List</h3>
            <table>
                <thead>
                    <tr>
                        <th>Company</th>
                        <th>Plan</th>
                        <th>Period</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($subscriptions as $subscription)
                        <tr>
                            <td>
                                <strong>{{ $subscription->company->name }}</strong><br>
                                <small>{{ $subscription->company->code }}</small>
                            </td>
                            <td>{{ $subscription->plan_name }}</td>
                            <td>
                                {{ $subscription->starts_at?->format('Y-m-d') }}<br>
                                <small>{{ $subscription->ends_at?->format('Y-m-d') ?? 'Open end' }}</small><br>
                                <small>{{ $subscription->active_users }}/{{ $subscription->max_users }} active users</small>
                            </td>
                            <td><span class="status">{{ $subscription->status->value ?? $subscription->status }}</span></td>
                            <td>
                                <form method="POST" action="{{ route('admin.subscriptions.update', $subscription) }}">
                                    @csrf
                                    @method('PUT')
                                    <select name="company_id" required>
                                        @foreach ($companies as $company)
                                            <option value="{{ $company->id }}" @selected($subscription->company_id === $company->id)>{{ $company->name }}</option>
                                        @endforeach
                                    </select>
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
                                    <button class="btn btn-soft inline" type="submit">Update</button>
                                </form>
                                <form method="POST" action="{{ route('admin.subscriptions.destroy', $subscription) }}" class="inline" onsubmit="return confirm('Delete this subscription?')">
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
