@extends('admin.layout', ['title' => 'Landing Page'])

@php
    $localeLabels = ['en' => 'English', 'ar' => 'العربية', 'tr' => 'Türkçe'];
@endphp

@section('content')
    <section class="hero">
        <h2>Landing Page Content</h2>
        <p>Everything shown on the public marketing page is edited here — hero copy, features, pricing, testimonials, FAQ, contact/quote forms, and more. Every field is editable in English, Arabic, and Turkish. Leave a field blank to fall back to the default copy.</p>
        <div class="actions-row" style="margin-top: 1rem;">
            <a class="btn-link" href="{{ route('home') }}" target="_blank" rel="noopener">View live page (EN) ↗</a>
            <a class="btn-link" href="{{ route('home', 'ar') }}" target="_blank" rel="noopener">View live page (AR) ↗</a>
            <a class="btn-link" href="{{ route('home', 'tr') }}" target="_blank" rel="noopener">View live page (TR) ↗</a>
        </div>
    </section>

    <style>
        .lang-tabs { display: flex; gap: .5rem; margin-bottom: 1.25rem; }
        .lang-tab {
            padding: .55rem 1.1rem;
            border-radius: 999px;
            border: 1px solid var(--border);
            background: var(--surface-1);
            color: var(--text-muted);
            font: inherit;
            font-size: .85rem;
            font-weight: 700;
            cursor: pointer;
        }
        .lang-tab.active {
            color: #04140f;
            background: linear-gradient(135deg, var(--accent), var(--accent-2));
            border-color: transparent;
        }
        .lang-panel { display: none; }
        .lang-panel.active { display: block; }
    </style>

    <form method="POST" action="{{ route('admin.landing-page.update') }}">
        @csrf
        @method('PUT')

        <div class="actions-row" style="margin-bottom: 1.25rem;">
            <button class="btn" type="submit">Save all changes</button>
        </div>

        <div class="lang-tabs">
            @foreach ($locales as $locale)
                <button type="button" class="lang-tab {{ $loop->first ? 'active' : '' }}" data-lang-tab="{{ $locale }}">{{ $localeLabels[$locale] }}</button>
            @endforeach
        </div>

        @foreach ($locales as $locale)
            @php($loc = $content[$locale])
            <div class="lang-panel {{ $loop->first ? 'active' : '' }}" data-lang-panel="{{ $locale }}">

                <section class="panel">
                    <h3>Hero</h3>
                    <label class="field-label">Eyebrow badge</label>
                    <input name="content[{{ $locale }}][hero][eyebrow]" value="{{ $loc['hero']['eyebrow'] }}">
                    <label class="field-label">Headline</label>
                    <input name="content[{{ $locale }}][hero][headline]" value="{{ $loc['hero']['headline'] }}">
                    <label class="field-label">Subheadline</label>
                    <textarea name="content[{{ $locale }}][hero][subheadline]">{{ $loc['hero']['subheadline'] }}</textarea>
                    <div class="grid-2">
                        <div>
                            <label class="field-label">Primary CTA label</label>
                            <input name="content[{{ $locale }}][hero][primary_cta_label]" value="{{ $loc['hero']['primary_cta_label'] }}">
                        </div>
                        <div>
                            <label class="field-label">Secondary CTA label</label>
                            <input name="content[{{ $locale }}][hero][secondary_cta_label]" value="{{ $loc['hero']['secondary_cta_label'] }}">
                        </div>
                    </div>
                </section>

                <section class="panel">
                    <h3>Trusted By</h3>
                    <label class="field-label">Eyebrow text</label>
                    <input name="content[{{ $locale }}][trusted_by][eyebrow]" value="{{ $loc['trusted_by']['eyebrow'] }}">
                    <label class="field-label">Company names (one per line)</label>
                    <textarea name="content[{{ $locale }}][trusted_by][names]" style="min-height: 130px;">{{ $loc['trusted_by']['names'] }}</textarea>
                </section>

                <section class="panel">
                    <h3>About</h3>
                    <label class="field-label">Eyebrow</label>
                    <input name="content[{{ $locale }}][about][eyebrow]" value="{{ $loc['about']['eyebrow'] }}">
                    <label class="field-label">Headline</label>
                    <input name="content[{{ $locale }}][about][headline]" value="{{ $loc['about']['headline'] }}">
                    <label class="field-label">Body paragraphs (leave a blank line between paragraphs)</label>
                    <textarea name="content[{{ $locale }}][about][paragraphs]" style="min-height: 150px;">{{ $loc['about']['paragraphs'] }}</textarea>
                    <label class="field-label">Pull quote</label>
                    <textarea name="content[{{ $locale }}][about][pull_quote]">{{ $loc['about']['pull_quote'] }}</textarea>
                </section>

                <section class="panel">
                    <h3>Features</h3>
                    <p class="muted" style="margin-top: -.5rem; margin-bottom: 1rem;">Icons are fixed per slot; only title and description are editable.</p>
                    @foreach ($loc['features'] as $i => $feature)
                        <div class="grid-2" style="margin-bottom: 1rem; padding-bottom: 1rem; {{ $loop->last ? '' : 'border-bottom: 1px solid var(--border);' }}">
                            <div>
                                <label class="field-label">Feature {{ $i + 1 }} — title</label>
                                <input name="content[{{ $locale }}][features][{{ $i }}][title]" value="{{ $feature['title'] }}">
                            </div>
                            <div>
                                <label class="field-label">Feature {{ $i + 1 }} — description</label>
                                <input name="content[{{ $locale }}][features][{{ $i }}][body]" value="{{ $feature['body'] }}">
                            </div>
                        </div>
                    @endforeach
                </section>

                <section class="panel">
                    <h3>How It Works</h3>
                    @foreach ($loc['how_it_works'] as $i => $step)
                        <div class="grid-2" style="margin-bottom: 1rem; padding-bottom: 1rem; {{ $loop->last ? '' : 'border-bottom: 1px solid var(--border);' }}">
                            <div>
                                <label class="field-label">Step {{ $i + 1 }} — title</label>
                                <input name="content[{{ $locale }}][how_it_works][{{ $i }}][title]" value="{{ $step['title'] }}">
                            </div>
                            <div>
                                <label class="field-label">Step {{ $i + 1 }} — description</label>
                                <input name="content[{{ $locale }}][how_it_works][{{ $i }}][body]" value="{{ $step['body'] }}">
                            </div>
                        </div>
                    @endforeach
                </section>

                <section class="panel">
                    <h3>Pricing</h3>
                    @foreach ($loc['pricing'] as $i => $tier)
                        <div style="margin-bottom: 1.25rem; padding-bottom: 1.25rem; {{ $loop->last ? '' : 'border-bottom: 1px solid var(--border);' }}">
                            <div class="grid-2">
                                <div>
                                    <label class="field-label">Plan {{ $i + 1 }} — name</label>
                                    <input name="content[{{ $locale }}][pricing][{{ $i }}][name]" value="{{ $tier['name'] }}">
                                </div>
                                <div>
                                    <label class="field-label">Plan {{ $i + 1 }} — description</label>
                                    <input name="content[{{ $locale }}][pricing][{{ $i }}][description]" value="{{ $tier['description'] }}">
                                </div>
                            </div>
                            <div class="grid-2">
                                <div>
                                    <label class="field-label">Monthly price</label>
                                    <input name="content[{{ $locale }}][pricing][{{ $i }}][price_monthly]" value="{{ $tier['price_monthly'] }}">
                                </div>
                                <div>
                                    <label class="field-label">Yearly price</label>
                                    <input name="content[{{ $locale }}][pricing][{{ $i }}][price_yearly]" value="{{ $tier['price_yearly'] }}">
                                </div>
                            </div>
                            <div class="grid-2">
                                <div>
                                    <label class="field-label">Button label</label>
                                    <input name="content[{{ $locale }}][pricing][{{ $i }}][cta_label]" value="{{ $tier['cta_label'] }}">
                                </div>
                                <div>
                                    <label class="field-label">Highlight this plan?</label>
                                    <select name="content[{{ $locale }}][pricing][{{ $i }}][highlighted]">
                                        <option value="0" @selected(! $tier['highlighted'])>No</option>
                                        <option value="1" @selected($tier['highlighted'])>Yes — "Most popular"</option>
                                    </select>
                                </div>
                            </div>
                            <label class="field-label">Feature list (one per line)</label>
                            <textarea name="content[{{ $locale }}][pricing][{{ $i }}][features]">{{ $tier['features'] }}</textarea>
                        </div>
                    @endforeach
                </section>

                <section class="panel">
                    <h3>Benefits</h3>
                    @foreach ($loc['benefits'] as $i => $benefit)
                        <div class="grid-2" style="margin-bottom: 1rem; padding-bottom: 1rem; {{ $loop->last ? '' : 'border-bottom: 1px solid var(--border);' }}">
                            <div>
                                <label class="field-label">Benefit {{ $i + 1 }} — title</label>
                                <input name="content[{{ $locale }}][benefits][{{ $i }}][title]" value="{{ $benefit['title'] }}">
                            </div>
                            <div>
                                <label class="field-label">Benefit {{ $i + 1 }} — description</label>
                                <input name="content[{{ $locale }}][benefits][{{ $i }}][body]" value="{{ $benefit['body'] }}">
                            </div>
                        </div>
                    @endforeach
                </section>

                <section class="panel">
                    <h3>Testimonials</h3>
                    @foreach ($loc['testimonials'] as $i => $testimonial)
                        <div style="margin-bottom: 1.25rem; padding-bottom: 1.25rem; {{ $loop->last ? '' : 'border-bottom: 1px solid var(--border);' }}">
                            <div class="grid-2">
                                <div>
                                    <label class="field-label">Name</label>
                                    <input name="content[{{ $locale }}][testimonials][{{ $i }}][name]" value="{{ $testimonial['name'] }}">
                                </div>
                                <div>
                                    <label class="field-label">Role / clinic</label>
                                    <input name="content[{{ $locale }}][testimonials][{{ $i }}][role]" value="{{ $testimonial['role'] }}">
                                </div>
                            </div>
                            <label class="field-label">Avatar initials (2 letters)</label>
                            <input name="content[{{ $locale }}][testimonials][{{ $i }}][initials]" value="{{ $testimonial['initials'] }}" style="max-width: 100px;">
                            <label class="field-label">Quote</label>
                            <textarea name="content[{{ $locale }}][testimonials][{{ $i }}][quote]">{{ $testimonial['quote'] }}</textarea>
                        </div>
                    @endforeach
                </section>

                <section class="panel">
                    <h3>FAQ</h3>
                    @foreach ($loc['faq'] as $i => $item)
                        <div style="margin-bottom: 1rem; padding-bottom: 1rem; {{ $loop->last ? '' : 'border-bottom: 1px solid var(--border);' }}">
                            <label class="field-label">Question {{ $i + 1 }}</label>
                            <input name="content[{{ $locale }}][faq][{{ $i }}][question]" value="{{ $item['question'] }}">
                            <label class="field-label">Answer</label>
                            <textarea name="content[{{ $locale }}][faq][{{ $i }}][answer]">{{ $item['answer'] }}</textarea>
                        </div>
                    @endforeach
                </section>

                <section class="panel">
                    <h3>Contact Section</h3>
                    <p class="muted" style="margin-top: -.5rem; margin-bottom: 1rem;">Submitted messages appear under Admin → Inquiries.</p>
                    <label class="field-label">Eyebrow</label>
                    <input name="content[{{ $locale }}][contact][eyebrow]" value="{{ $loc['contact']['eyebrow'] }}">
                    <label class="field-label">Headline</label>
                    <input name="content[{{ $locale }}][contact][headline]" value="{{ $loc['contact']['headline'] }}">
                    <label class="field-label">Subtext</label>
                    <textarea name="content[{{ $locale }}][contact][subtext]">{{ $loc['contact']['subtext'] }}</textarea>
                    <div class="grid-2">
                        <div>
                            <label class="field-label">Name field label</label>
                            <input name="content[{{ $locale }}][contact][name_label]" value="{{ $loc['contact']['name_label'] }}">
                        </div>
                        <div>
                            <label class="field-label">Email field label</label>
                            <input name="content[{{ $locale }}][contact][email_label]" value="{{ $loc['contact']['email_label'] }}">
                        </div>
                    </div>
                    <div class="grid-2">
                        <div>
                            <label class="field-label">Message field label</label>
                            <input name="content[{{ $locale }}][contact][message_label]" value="{{ $loc['contact']['message_label'] }}">
                        </div>
                        <div>
                            <label class="field-label">Submit button label</label>
                            <input name="content[{{ $locale }}][contact][submit_label]" value="{{ $loc['contact']['submit_label'] }}">
                        </div>
                    </div>
                    <label class="field-label">Success message</label>
                    <input name="content[{{ $locale }}][contact][success_message]" value="{{ $loc['contact']['success_message'] }}">
                </section>

                <section class="panel">
                    <h3>Get a Quote Section</h3>
                    <p class="muted" style="margin-top: -.5rem; margin-bottom: 1rem;">Submitted requests appear under Admin → Inquiries.</p>
                    <label class="field-label">Eyebrow</label>
                    <input name="content[{{ $locale }}][quote][eyebrow]" value="{{ $loc['quote']['eyebrow'] }}">
                    <label class="field-label">Headline</label>
                    <input name="content[{{ $locale }}][quote][headline]" value="{{ $loc['quote']['headline'] }}">
                    <label class="field-label">Subtext</label>
                    <textarea name="content[{{ $locale }}][quote][subtext]">{{ $loc['quote']['subtext'] }}</textarea>
                    <div class="grid-2">
                        <div>
                            <label class="field-label">Name field label</label>
                            <input name="content[{{ $locale }}][quote][name_label]" value="{{ $loc['quote']['name_label'] }}">
                        </div>
                        <div>
                            <label class="field-label">Email field label</label>
                            <input name="content[{{ $locale }}][quote][email_label]" value="{{ $loc['quote']['email_label'] }}">
                        </div>
                    </div>
                    <div class="grid-2">
                        <div>
                            <label class="field-label">Phone field label</label>
                            <input name="content[{{ $locale }}][quote][phone_label]" value="{{ $loc['quote']['phone_label'] }}">
                        </div>
                        <div>
                            <label class="field-label">Clinic / company field label</label>
                            <input name="content[{{ $locale }}][quote][company_label]" value="{{ $loc['quote']['company_label'] }}">
                        </div>
                    </div>
                    <div class="grid-2">
                        <div>
                            <label class="field-label">Message field label</label>
                            <input name="content[{{ $locale }}][quote][message_label]" value="{{ $loc['quote']['message_label'] }}">
                        </div>
                        <div>
                            <label class="field-label">Submit button label</label>
                            <input name="content[{{ $locale }}][quote][submit_label]" value="{{ $loc['quote']['submit_label'] }}">
                        </div>
                    </div>
                    <label class="field-label">Success message</label>
                    <input name="content[{{ $locale }}][quote][success_message]" value="{{ $loc['quote']['success_message'] }}">
                </section>

                <section class="panel">
                    <h3>Final CTA</h3>
                    <label class="field-label">Headline</label>
                    <input name="content[{{ $locale }}][final_cta][headline]" value="{{ $loc['final_cta']['headline'] }}">
                    <label class="field-label">Subtext</label>
                    <textarea name="content[{{ $locale }}][final_cta][subtext]">{{ $loc['final_cta']['subtext'] }}</textarea>
                    <div class="grid-2">
                        <div>
                            <label class="field-label">Button label</label>
                            <input name="content[{{ $locale }}][final_cta][button_label]" value="{{ $loc['final_cta']['button_label'] }}">
                        </div>
                        <div>
                            <label class="field-label">Button email (mailto)</label>
                            <input name="content[{{ $locale }}][final_cta][button_email]" value="{{ $loc['final_cta']['button_email'] }}">
                        </div>
                    </div>
                    <label class="field-label">Fine-print note</label>
                    <input name="content[{{ $locale }}][final_cta][note]" value="{{ $loc['final_cta']['note'] }}">
                </section>

                <section class="panel">
                    <h3>Footer</h3>
                    <label class="field-label">Tagline</label>
                    <input name="content[{{ $locale }}][footer][tagline]" value="{{ $loc['footer']['tagline'] }}">
                    <div class="grid-2">
                        <div>
                            <label class="field-label">Contact email</label>
                            <input name="content[{{ $locale }}][footer][contact_email]" value="{{ $loc['footer']['contact_email'] }}">
                        </div>
                        <div>
                            <label class="field-label">Copyright name</label>
                            <input name="content[{{ $locale }}][footer][copyright_name]" value="{{ $loc['footer']['copyright_name'] }}">
                        </div>
                    </div>
                </section>
            </div>
        @endforeach

        <div class="actions-row" style="margin-top: .5rem;">
            <button class="btn" type="submit">Save all changes</button>
        </div>
    </form>

    <script>
        document.querySelectorAll('[data-lang-tab]').forEach(function (tab) {
            tab.addEventListener('click', function () {
                var locale = tab.dataset.langTab;
                document.querySelectorAll('[data-lang-tab]').forEach(function (t) { t.classList.toggle('active', t === tab); });
                document.querySelectorAll('[data-lang-panel]').forEach(function (panel) {
                    panel.classList.toggle('active', panel.dataset.langPanel === locale);
                });
            });
        });
    </script>
@endsection
