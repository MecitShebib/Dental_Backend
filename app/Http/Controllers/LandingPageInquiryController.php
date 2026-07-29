<?php

namespace App\Http\Controllers;

use App\Enums\InquiryType;
use App\Models\LandingPageInquiry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LandingPageInquiryController extends Controller
{
    public function storeContact(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'message' => 'required|string|max:2000',
            'locale' => 'nullable|string|in:en,ar,tr',
        ]);

        LandingPageInquiry::create([
            'type' => InquiryType::Contact,
            'locale' => $validated['locale'] ?? 'en',
            'name' => $validated['name'],
            'email' => $validated['email'],
            'message' => $validated['message'],
        ]);

        return redirect()->back()->with('inquiry_success', 'contact');
    }

    public function storeQuote(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'phone' => 'nullable|string|max:50',
            'company' => 'nullable|string|max:255',
            'message' => 'nullable|string|max:2000',
            'locale' => 'nullable|string|in:en,ar,tr',
        ]);

        LandingPageInquiry::create([
            'type' => InquiryType::Quote,
            'locale' => $validated['locale'] ?? 'en',
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'company' => $validated['company'] ?? null,
            'message' => $validated['message'] ?? null,
        ]);

        return redirect()->back()->with('inquiry_success', 'quote');
    }
}
