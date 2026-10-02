@extends('admin.layout', ['title' => 'System Status'])

@php
    $schedulerOk = $schedulerLastRun && $schedulerLastRun->gt(now()->subMinutes(3));
    $heartbeatProcessed = $heartbeat && $pendingHeartbeat && ($heartbeat['dispatched_at'] ?? null) === $pendingHeartbeat;
    $heartbeatWaiting = $pendingHeartbeat && ! $heartbeatProcessed;
@endphp

@section('content')
    <section class="hero">
        <h2>System Status</h2>
        <p>Checks that the hosting cron runs <code>php artisan schedule:run</code> every minute and that background jobs (reminders, recalls, surveys, AI X-ray analysis, CRM) are being processed.</p>
    </section>

    <section class="cards">
        <div class="card">
            <strong>Scheduler (cron)</strong>
            <div style="color: {{ $schedulerOk ? '#15803d' : '#b91c1c' }};">{{ $schedulerOk ? 'Running' : 'Not running' }}</div>
            <small class="muted">Last run: {{ $schedulerLastRun ? $schedulerLastRun->diffForHumans().' ('.$schedulerLastRun->toDateTimeString().' UTC)' : 'never' }}</small>
        </div>
        <div class="card">
            <strong>Waiting jobs</strong>
            <div>{{ $pendingJobs }}</div>
            <small class="muted">{{ $oldestPendingJob ? 'Oldest added '.$oldestPendingJob->diffForHumans() : 'Queue is empty' }}</small>
        </div>
        <div class="card">
            <strong>Failed jobs</strong>
            <div style="color: {{ $failedJobs ? '#b91c1c' : 'inherit' }};">{{ $failedJobs }}</div>
        </div>
        <div class="card">
            <strong>Test job</strong>
            @if ($heartbeatProcessed)
                <div style="color: #15803d;">Processed ✓</div>
                <small class="muted">Sent {{ \Illuminate\Support\Carbon::parse($heartbeat['dispatched_at'])->toDateTimeString() }}, processed {{ \Illuminate\Support\Carbon::parse($heartbeat['processed_at'])->toDateTimeString() }} UTC</small>
            @elseif ($heartbeatWaiting)
                <div style="color: #b45309;">Waiting…</div>
                <small class="muted">Sent {{ \Illuminate\Support\Carbon::parse($pendingHeartbeat)->diffForHumans() }} — refresh in a minute.</small>
            @else
                <div>—</div>
                <small class="muted">Not sent yet.</small>
            @endif
        </div>
    </section>

    <section class="panel">
        <h3>Send a test job</h3>
        <p class="muted">Adds one harmless job to the queue. If the cron and the queue work, it is processed within about a minute and the card above turns to “Processed ✓”.</p>
        <form method="POST" action="{{ route('admin.system.test-job') }}">
            @csrf
            <button class="btn" type="submit">Send test job</button>
        </form>
    </section>

    @if ($recentFailures->isNotEmpty())
        <section class="panel">
            <h3>Latest failed jobs</h3>
            @foreach ($recentFailures as $failure)
                <details style="margin-bottom: .75rem;">
                    <summary>{{ $failure->failed_at }} — {{ \Illuminate\Support\Str::limit(strtok($failure->exception, "\n"), 160) }}</summary>
                    <pre style="white-space: pre-wrap; font-size: 12px;">{{ \Illuminate\Support\Str::limit($failure->exception, 3000) }}</pre>
                </details>
            @endforeach
        </section>
    @endif
@endsection
