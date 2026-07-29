<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LandingPageContent;
use Illuminate\Http\Request;

class LandingPageController extends Controller
{
    public function edit()
    {
        return view('admin.landing-page.edit', [
            'locales' => LandingPageContent::LOCALES,
            'content' => LandingPageContent::currentAll(),
        ]);
    }

    public function update(Request $request)
    {
        $rules = [];

        foreach (LandingPageContent::LOCALES as $locale) {
            $rules = array_merge($rules, [
                "content.{$locale}.hero.eyebrow" => 'nullable|string|max:255',
                "content.{$locale}.hero.headline" => 'nullable|string|max:255',
                "content.{$locale}.hero.subheadline" => 'nullable|string|max:1000',
                "content.{$locale}.hero.primary_cta_label" => 'nullable|string|max:100',
                "content.{$locale}.hero.secondary_cta_label" => 'nullable|string|max:100',

                "content.{$locale}.trusted_by.eyebrow" => 'nullable|string|max:255',
                "content.{$locale}.trusted_by.names" => 'nullable|string|max:1000',

                "content.{$locale}.about.eyebrow" => 'nullable|string|max:255',
                "content.{$locale}.about.headline" => 'nullable|string|max:255',
                "content.{$locale}.about.paragraphs" => 'nullable|string|max:4000',
                "content.{$locale}.about.pull_quote" => 'nullable|string|max:1000',

                "content.{$locale}.features" => 'nullable|array',
                "content.{$locale}.features.*.title" => 'nullable|string|max:255',
                "content.{$locale}.features.*.body" => 'nullable|string|max:500',

                "content.{$locale}.how_it_works" => 'nullable|array',
                "content.{$locale}.how_it_works.*.title" => 'nullable|string|max:255',
                "content.{$locale}.how_it_works.*.body" => 'nullable|string|max:500',

                "content.{$locale}.pricing" => 'nullable|array',
                "content.{$locale}.pricing.*.name" => 'nullable|string|max:100',
                "content.{$locale}.pricing.*.description" => 'nullable|string|max:255',
                "content.{$locale}.pricing.*.price_monthly" => 'nullable|string|max:50',
                "content.{$locale}.pricing.*.price_yearly" => 'nullable|string|max:50',
                "content.{$locale}.pricing.*.cta_label" => 'nullable|string|max:100',
                "content.{$locale}.pricing.*.highlighted" => 'nullable|boolean',
                "content.{$locale}.pricing.*.features" => 'nullable|string|max:1000',

                "content.{$locale}.benefits" => 'nullable|array',
                "content.{$locale}.benefits.*.title" => 'nullable|string|max:255',
                "content.{$locale}.benefits.*.body" => 'nullable|string|max:500',

                "content.{$locale}.testimonials" => 'nullable|array',
                "content.{$locale}.testimonials.*.initials" => 'nullable|string|max:4',
                "content.{$locale}.testimonials.*.name" => 'nullable|string|max:255',
                "content.{$locale}.testimonials.*.role" => 'nullable|string|max:255',
                "content.{$locale}.testimonials.*.quote" => 'nullable|string|max:500',

                "content.{$locale}.faq" => 'nullable|array',
                "content.{$locale}.faq.*.question" => 'nullable|string|max:255',
                "content.{$locale}.faq.*.answer" => 'nullable|string|max:1000',

                "content.{$locale}.final_cta.headline" => 'nullable|string|max:255',
                "content.{$locale}.final_cta.subtext" => 'nullable|string|max:500',
                "content.{$locale}.final_cta.button_label" => 'nullable|string|max:100',
                "content.{$locale}.final_cta.button_email" => 'nullable|string|max:255',
                "content.{$locale}.final_cta.note" => 'nullable|string|max:255',

                "content.{$locale}.footer.tagline" => 'nullable|string|max:255',
                "content.{$locale}.footer.contact_email" => 'nullable|string|max:255',
                "content.{$locale}.footer.copyright_name" => 'nullable|string|max:100',

                "content.{$locale}.contact.eyebrow" => 'nullable|string|max:255',
                "content.{$locale}.contact.headline" => 'nullable|string|max:255',
                "content.{$locale}.contact.subtext" => 'nullable|string|max:500',
                "content.{$locale}.contact.name_label" => 'nullable|string|max:100',
                "content.{$locale}.contact.email_label" => 'nullable|string|max:100',
                "content.{$locale}.contact.message_label" => 'nullable|string|max:100',
                "content.{$locale}.contact.submit_label" => 'nullable|string|max:100',
                "content.{$locale}.contact.success_message" => 'nullable|string|max:500',

                "content.{$locale}.quote.eyebrow" => 'nullable|string|max:255',
                "content.{$locale}.quote.headline" => 'nullable|string|max:255',
                "content.{$locale}.quote.subtext" => 'nullable|string|max:500',
                "content.{$locale}.quote.name_label" => 'nullable|string|max:100',
                "content.{$locale}.quote.email_label" => 'nullable|string|max:100',
                "content.{$locale}.quote.phone_label" => 'nullable|string|max:100',
                "content.{$locale}.quote.company_label" => 'nullable|string|max:100',
                "content.{$locale}.quote.message_label" => 'nullable|string|max:100',
                "content.{$locale}.quote.submit_label" => 'nullable|string|max:100',
                "content.{$locale}.quote.success_message" => 'nullable|string|max:500',
            ]);
        }

        $validated = $request->validate($rules);
        $content = $validated['content'] ?? [];

        foreach (LandingPageContent::LOCALES as $locale) {
            foreach ($content[$locale]['pricing'] ?? [] as $index => $row) {
                $content[$locale]['pricing'][$index]['highlighted'] = (bool) ($row['highlighted'] ?? false);
            }
        }

        $row = LandingPageContent::query()->first() ?? new LandingPageContent;
        $row->content = $content;
        $row->save();

        return redirect()->route('admin.landing-page.edit')->with('status', 'Landing page content updated successfully.');
    }
}
