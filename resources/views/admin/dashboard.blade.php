@extends('admin.layout', ['title' => 'Dashboard'])

@section('content')
    <section class="hero">
        <h2>Clinic Control Room</h2>
        <p>Manage users and subscriptions from one place. Any inactive user or user without an active subscription cannot log in.</p>
    </section>

    <section class="cards">
        <div class="card">
            <strong>Total Users</strong>
            <div>{{ $usersCount }}</div>
        </div>
        <div class="card">
            <strong>Active Users</strong>
            <div>{{ $activeUsersCount }}</div>
        </div>
        <div class="card">
            <strong>Total Subscriptions</strong>
            <div>{{ $subscriptionsCount }}</div>
        </div>
        <div class="card">
            <strong>Active Subscriptions</strong>
            <div>{{ $activeSubscriptionsCount }}</div>
        </div>
    </section>
@endsection
