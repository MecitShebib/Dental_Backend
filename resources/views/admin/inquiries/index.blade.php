@extends('admin.layout', ['title' => 'Inquiries'])

@section('content')
    <section class="hero">
        <h2>Landing Page Inquiries</h2>
        <p>Contact messages and quote requests submitted from the public landing page. {{ $unreadCount }} unread.</p>
    </section>

    <div class="toolbar">
        <form method="GET" action="{{ route('admin.inquiries.index') }}">
            <select name="type">
                <option value="">All types</option>
                <option value="contact" @selected(($filters['type'] ?? '') === 'contact')>Contact</option>
                <option value="quote" @selected(($filters['type'] ?? '') === 'quote')>Quote request</option>
            </select>
            <select name="status">
                <option value="">All</option>
                <option value="unread" @selected(($filters['status'] ?? '') === 'unread')>Unread</option>
                <option value="read" @selected(($filters['status'] ?? '') === 'read')>Read</option>
            </select>
            <button class="btn-soft" type="submit">Filter</button>
            <a class="btn-muted" href="{{ route('admin.inquiries.index') }}">Reset</a>
        </form>
        <div class="actions-row">
            <div class="muted">Total: {{ $inquiries->count() }}</div>
        </div>
    </div>

    <section class="panel">
        <table>
            <thead>
                <tr>
                    <th>Type</th>
                    <th>From</th>
                    <th>Details</th>
                    <th>Locale</th>
                    <th>Received</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($inquiries as $inquiry)
                    <tr>
                        <td><span class="status">{{ $inquiry->type->value === 'quote' ? 'Quote request' : 'Contact' }}</span></td>
                        <td>
                            <strong>{{ $inquiry->name }}</strong><br>
                            <small>{{ $inquiry->email }}</small>
                            @if ($inquiry->phone)
                                <br><small>{{ $inquiry->phone }}</small>
                            @endif
                        </td>
                        <td>
                            @if ($inquiry->company)
                                <strong>{{ $inquiry->company }}</strong><br>
                            @endif
                            <small>{{ \Illuminate\Support\Str::limit($inquiry->message, 140) }}</small>
                        </td>
                        <td>{{ strtoupper($inquiry->locale) }}</td>
                        <td><small>{{ $inquiry->created_at->format('Y-m-d H:i') }}</small></td>
                        <td>
                            @if ($inquiry->read_at)
                                <span class="status">Read</span>
                            @else
                                <span class="status" style="color:#047857; border-color: rgba(16,185,129,.35); background: rgba(16,185,129,.1);">Unread</span>
                            @endif
                        </td>
                        <td>
                            <div class="actions-row table-actions">
                                @unless ($inquiry->read_at)
                                    <form method="POST" action="{{ route('admin.inquiries.read', $inquiry) }}">
                                        @csrf
                                        @method('PATCH')
                                        <button class="btn-soft" type="submit">Mark read</button>
                                    </form>
                                @endunless
                                <form method="POST" action="{{ route('admin.inquiries.destroy', $inquiry) }}" onsubmit="return confirm('Delete this inquiry?');">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn-danger" type="submit">Delete</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7"><span class="muted">No inquiries yet.</span></td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </section>
@endsection
