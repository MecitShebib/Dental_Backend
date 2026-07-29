<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LandingPageInquiry;
use Illuminate\Http\Request;

class LandingPageInquiryController extends Controller
{
    public function index(Request $request)
    {
        $inquiries = LandingPageInquiry::query()
            ->when($request->filled('type'), fn ($query) => $query->where('type', $request->string('type')))
            ->when($request->query('status') === 'unread', fn ($query) => $query->whereNull('read_at'))
            ->when($request->query('status') === 'read', fn ($query) => $query->whereNotNull('read_at'))
            ->latest()
            ->get();

        return view('admin.inquiries.index', [
            'inquiries' => $inquiries,
            'filters' => $request->only(['type', 'status']),
            'unreadCount' => LandingPageInquiry::query()->whereNull('read_at')->count(),
        ]);
    }

    public function markRead(LandingPageInquiry $inquiry)
    {
        if (! $inquiry->read_at) {
            $inquiry->update(['read_at' => now()]);
        }

        return back()->with('status', 'Marked as read.');
    }

    public function destroy(LandingPageInquiry $inquiry)
    {
        $inquiry->delete();

        return back()->with('status', 'Inquiry deleted.');
    }
}
