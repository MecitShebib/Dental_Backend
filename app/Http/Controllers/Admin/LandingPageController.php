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
            'specialties' => LandingPageContent::SPECIALTIES,
            'specialtySlugs' => LandingPageContent::SPECIALTY_SLUGS,
            'hub' => LandingPageContent::hubAll(),
            'specialtiesContent' => LandingPageContent::allSpecialtiesAll(),
        ]);
    }

    public function update(Request $request)
    {
        $rules = $this->hubRules();
        foreach (LandingPageContent::SPECIALTIES as $specialty) {
            $rules = array_merge($rules, $this->specialtyRules($specialty));
        }

        $validated = $request->validate($rules);
        $content = $validated['content'] ?? [];

        foreach (LandingPageContent::SPECIALTIES as $specialty) {
            foreach (LandingPageContent::LOCALES as $locale) {
                foreach ($content[$specialty][$locale]['pricing'] ?? [] as $index => $row) {
                    $content[$specialty][$locale]['pricing'][$index]['highlighted'] = (bool) ($row['highlighted'] ?? false);
                }
            }
        }

        $row = LandingPageContent::query()->first() ?? new LandingPageContent;
        $row->content = $content;
        $row->save();

        return redirect()->route('admin.landing-page.edit')->with('status', 'Landing page content updated successfully.');
    }

    /**
     * The hub page (product list) -- a much smaller schema than a specialty
     * page: an intro, the 5 product cards, and a footer.
     */
    protected function hubRules(): array
    {
        $rules = [];

        foreach (LandingPageContent::LOCALES as $locale) {
            $prefix = "content.hub.{$locale}";
            $rules = array_merge($rules, [
                "{$prefix}.hero.eyebrow" => 'nullable|string|max:255',
                "{$prefix}.hero.headline" => 'nullable|string|max:255',
                "{$prefix}.hero.subtext" => 'nullable|string|max:500',

                "{$prefix}.products" => 'nullable|array',
                "{$prefix}.products.*.name" => 'nullable|string|max:100',
                "{$prefix}.products.*.tagline" => 'nullable|string|max:255',
                "{$prefix}.products.*.body" => 'nullable|string|max:500',

                "{$prefix}.footer.tagline" => 'nullable|string|max:255',
                "{$prefix}.footer.contact_email" => 'nullable|string|max:255',
                "{$prefix}.footer.copyright_name" => 'nullable|string|max:100',
            ]);
        }

        return $rules;
    }

    /**
     * One specialty's full marketing page (hero through quote form) -- the
     * same schema shape, repeated for every specialty and locale.
     */
    protected function specialtyRules(string $specialty): array
    {
        $rules = [];

        foreach (LandingPageContent::LOCALES as $locale) {
            $prefix = "content.{$specialty}.{$locale}";
            $rules = array_merge($rules, [
                "{$prefix}.hero.eyebrow" => 'nullable|string|max:255',
                "{$prefix}.hero.headline" => 'nullable|string|max:255',
                "{$prefix}.hero.subheadline" => 'nullable|string|max:1000',
                "{$prefix}.hero.primary_cta_label" => 'nullable|string|max:100',
                "{$prefix}.hero.secondary_cta_label" => 'nullable|string|max:100',

                "{$prefix}.features" => 'nullable|array',
                "{$prefix}.features.*.title" => 'nullable|string|max:255',
                "{$prefix}.features.*.body" => 'nullable|string|max:500',

                "{$prefix}.how_it_works" => 'nullable|array',
                "{$prefix}.how_it_works.*.title" => 'nullable|string|max:255',
                "{$prefix}.how_it_works.*.body" => 'nullable|string|max:500',

                "{$prefix}.pricing" => 'nullable|array',
                "{$prefix}.pricing.*.name" => 'nullable|string|max:100',
                "{$prefix}.pricing.*.description" => 'nullable|string|max:255',
                "{$prefix}.pricing.*.price_monthly" => 'nullable|string|max:50',
                "{$prefix}.pricing.*.price_yearly" => 'nullable|string|max:50',
                "{$prefix}.pricing.*.cta_label" => 'nullable|string|max:100',
                "{$prefix}.pricing.*.highlighted" => 'nullable|boolean',
                "{$prefix}.pricing.*.features" => 'nullable|string|max:1500',

                "{$prefix}.benefits" => 'nullable|array',
                "{$prefix}.benefits.*.title" => 'nullable|string|max:255',
                "{$prefix}.benefits.*.body" => 'nullable|string|max:500',

                "{$prefix}.testimonials" => 'nullable|array',
                "{$prefix}.testimonials.*.initials" => 'nullable|string|max:4',
                "{$prefix}.testimonials.*.name" => 'nullable|string|max:255',
                "{$prefix}.testimonials.*.role" => 'nullable|string|max:255',
                "{$prefix}.testimonials.*.quote" => 'nullable|string|max:500',

                "{$prefix}.faq" => 'nullable|array',
                "{$prefix}.faq.*.question" => 'nullable|string|max:255',
                "{$prefix}.faq.*.answer" => 'nullable|string|max:1000',

                "{$prefix}.final_cta.headline" => 'nullable|string|max:255',
                "{$prefix}.final_cta.subtext" => 'nullable|string|max:500',
                "{$prefix}.final_cta.button_label" => 'nullable|string|max:100',
                "{$prefix}.final_cta.button_email" => 'nullable|string|max:255',
                "{$prefix}.final_cta.note" => 'nullable|string|max:255',

                "{$prefix}.footer.tagline" => 'nullable|string|max:255',
                "{$prefix}.footer.contact_email" => 'nullable|string|max:255',
                "{$prefix}.footer.copyright_name" => 'nullable|string|max:100',

                "{$prefix}.contact.eyebrow" => 'nullable|string|max:255',
                "{$prefix}.contact.headline" => 'nullable|string|max:255',
                "{$prefix}.contact.subtext" => 'nullable|string|max:500',
                "{$prefix}.contact.name_label" => 'nullable|string|max:100',
                "{$prefix}.contact.email_label" => 'nullable|string|max:100',
                "{$prefix}.contact.message_label" => 'nullable|string|max:100',
                "{$prefix}.contact.submit_label" => 'nullable|string|max:100',
                "{$prefix}.contact.success_message" => 'nullable|string|max:500',

                "{$prefix}.quote.eyebrow" => 'nullable|string|max:255',
                "{$prefix}.quote.headline" => 'nullable|string|max:255',
                "{$prefix}.quote.subtext" => 'nullable|string|max:500',
                "{$prefix}.quote.name_label" => 'nullable|string|max:100',
                "{$prefix}.quote.email_label" => 'nullable|string|max:100',
                "{$prefix}.quote.phone_label" => 'nullable|string|max:100',
                "{$prefix}.quote.company_label" => 'nullable|string|max:100',
                "{$prefix}.quote.message_label" => 'nullable|string|max:100',
                "{$prefix}.quote.submit_label" => 'nullable|string|max:100',
                "{$prefix}.quote.success_message" => 'nullable|string|max:500',
            ]);
        }

        return $rules;
    }
}
