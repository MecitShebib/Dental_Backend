@extends('admin.layout', ['title' => 'Landing Page'])

@php
    $localeLabels = ['en' => 'English', 'ar' => 'العربية', 'tr' => 'Türkçe'];
    $specialtyLabels = [
        'dental' => 'Dentavaria',
        'gynecology' => 'Gynevaria',
        'internal_medicine' => 'Medivaria',
        'orthopedics' => 'Orthovaria',
        'cosmetic' => 'Estevaria',
    ];
@endphp

@section('content')
    <section class="hero">
        <h2>Landing Page Content</h2>
        <p>The public site is now a hub page (just the 5 product cards) plus one full marketing page per specialty, each with its own accent color. Everything below is editable in English, Arabic, and Turkish. Leave a field blank to fall back to the default copy.</p>
        <div class="actions-row" style="margin-top: 1rem;">
            <a class="btn-link" href="{{ route('home') }}" target="_blank" rel="noopener">View hub page ↗</a>
            @foreach ($specialtySlugs as $key => $slug)
                <a class="btn-link" href="{{ route('specialty.home', $slug) }}" target="_blank" rel="noopener">View {{ $specialtyLabels[$key] }} ↗</a>
            @endforeach
        </div>
    </section>

    <style>
        .product-tabs { display: flex; flex-wrap: wrap; gap: .5rem; margin-bottom: 1.25rem; }
        .product-tab {
            padding: .6rem 1.25rem;
            border-radius: 10px;
            border: 1px solid var(--border);
            background: var(--surface-1);
            color: var(--text-muted);
            font: inherit;
            font-size: .88rem;
            font-weight: 700;
            cursor: pointer;
        }
        .product-tab.active {
            color: #ffffff;
            background: var(--product-accent, var(--accent));
            border-color: transparent;
        }
        .product-panel { display: none; }
        .product-panel.active { display: block; }

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

        <div class="product-tabs">
            <button type="button" class="product-tab active" data-product-tab="hub">Hub (product list)</button>
            @foreach ($specialties as $specialty)
                <button type="button" class="product-tab" data-product-tab="{{ $specialty }}">{{ $specialtyLabels[$specialty] }}</button>
            @endforeach
        </div>

        {{-- ── Hub page ──────────────────────────────────────────────────── --}}
        <div class="product-panel active" data-product-panel="hub">
            <div class="lang-tabs">
                @foreach ($locales as $locale)
                    <button type="button" class="lang-tab {{ $loop->first ? 'active' : '' }}" data-lang-tab="{{ $locale }}">{{ $localeLabels[$locale] }}</button>
                @endforeach
            </div>

            @foreach ($locales as $locale)
                @php($loc = $hub[$locale])
                <div class="lang-panel {{ $loop->first ? 'active' : '' }}" data-lang-panel="{{ $locale }}">
                    <section class="panel">
                        <h3>Intro</h3>
                        <label class="field-label">Eyebrow badge</label>
                        <input name="content[hub][{{ $locale }}][hero][eyebrow]" value="{{ $loc['hero']['eyebrow'] }}">
                        <label class="field-label">Headline</label>
                        <input name="content[hub][{{ $locale }}][hero][headline]" value="{{ $loc['hero']['headline'] }}">
                        <label class="field-label">Subtext</label>
                        <textarea name="content[hub][{{ $locale }}][hero][subtext]">{{ $loc['hero']['subtext'] }}</textarea>
                    </section>

                    <section class="panel">
                        <h3>Product Cards</h3>
                        <p class="muted" style="margin-top: -.5rem; margin-bottom: 1rem;">Order and accent color are fixed per specialty; name, tagline, and description are editable.</p>
                        @foreach ($loc['products'] as $i => $product)
                            <div style="margin-bottom: 1rem; padding-bottom: 1rem; {{ $loop->last ? '' : 'border-bottom: 1px solid var(--border);' }}">
                                <div class="grid-2">
                                    <div>
                                        <label class="field-label">Product {{ $i + 1 }} — brand name</label>
                                        <input name="content[hub][{{ $locale }}][products][{{ $i }}][name]" value="{{ $product['name'] }}">
                                    </div>
                                    <div>
                                        <label class="field-label">Product {{ $i + 1 }} — tagline</label>
                                        <input name="content[hub][{{ $locale }}][products][{{ $i }}][tagline]" value="{{ $product['tagline'] }}">
                                    </div>
                                </div>
                                <label class="field-label">Description</label>
                                <input name="content[hub][{{ $locale }}][products][{{ $i }}][body]" value="{{ $product['body'] }}">
                                <input type="hidden" name="content[hub][{{ $locale }}][products][{{ $i }}][key]" value="{{ $product['key'] }}">
                            </div>
                        @endforeach
                    </section>

                    <section class="panel">
                        <h3>Footer</h3>
                        <label class="field-label">Tagline</label>
                        <input name="content[hub][{{ $locale }}][footer][tagline]" value="{{ $loc['footer']['tagline'] }}">
                        <div class="grid-2">
                            <div>
                                <label class="field-label">Contact email</label>
                                <input name="content[hub][{{ $locale }}][footer][contact_email]" value="{{ $loc['footer']['contact_email'] }}">
                            </div>
                            <div>
                                <label class="field-label">Copyright name</label>
                                <input name="content[hub][{{ $locale }}][footer][copyright_name]" value="{{ $loc['footer']['copyright_name'] }}">
                            </div>
                        </div>
                    </section>
                </div>
            @endforeach
        </div>

        {{-- ── One full panel per specialty ─────────────────────────────── --}}
        @foreach ($specialties as $specialty)
            <div class="product-panel" data-product-panel="{{ $specialty }}">
                <div class="lang-tabs">
                    @foreach ($locales as $locale)
                        <button type="button" class="lang-tab {{ $loop->first ? 'active' : '' }}" data-lang-tab="{{ $locale }}">{{ $localeLabels[$locale] }}</button>
                    @endforeach
                </div>

                @foreach ($locales as $locale)
                    @php($loc = $specialtiesContent[$specialty][$locale])
                    <div class="lang-panel {{ $loop->first ? 'active' : '' }}" data-lang-panel="{{ $locale }}">

                        <section class="panel">
                            <h3>Hero</h3>
                            <label class="field-label">Eyebrow badge</label>
                            <input name="content[{{ $specialty }}][{{ $locale }}][hero][eyebrow]" value="{{ $loc['hero']['eyebrow'] }}">
                            <label class="field-label">Headline</label>
                            <input name="content[{{ $specialty }}][{{ $locale }}][hero][headline]" value="{{ $loc['hero']['headline'] }}">
                            <label class="field-label">Subheadline</label>
                            <textarea name="content[{{ $specialty }}][{{ $locale }}][hero][subheadline]">{{ $loc['hero']['subheadline'] }}</textarea>
                            <div class="grid-2">
                                <div>
                                    <label class="field-label">Primary CTA label</label>
                                    <input name="content[{{ $specialty }}][{{ $locale }}][hero][primary_cta_label]" value="{{ $loc['hero']['primary_cta_label'] }}">
                                </div>
                                <div>
                                    <label class="field-label">Secondary CTA label</label>
                                    <input name="content[{{ $specialty }}][{{ $locale }}][hero][secondary_cta_label]" value="{{ $loc['hero']['secondary_cta_label'] }}">
                                </div>
                            </div>
                        </section>

                        <section class="panel">
                            <h3>Features</h3>
                            <p class="muted" style="margin-top: -.5rem; margin-bottom: 1rem;">Icons are fixed per slot; only title and description are editable.</p>
                            @foreach ($loc['features'] as $i => $feature)
                                <div class="grid-2" style="margin-bottom: 1rem; padding-bottom: 1rem; {{ $loop->last ? '' : 'border-bottom: 1px solid var(--border);' }}">
                                    <div>
                                        <label class="field-label">Feature {{ $i + 1 }} — title</label>
                                        <input name="content[{{ $specialty }}][{{ $locale }}][features][{{ $i }}][title]" value="{{ $feature['title'] }}">
                                    </div>
                                    <div>
                                        <label class="field-label">Feature {{ $i + 1 }} — description</label>
                                        <input name="content[{{ $specialty }}][{{ $locale }}][features][{{ $i }}][body]" value="{{ $feature['body'] }}">
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
                                        <input name="content[{{ $specialty }}][{{ $locale }}][how_it_works][{{ $i }}][title]" value="{{ $step['title'] }}">
                                    </div>
                                    <div>
                                        <label class="field-label">Step {{ $i + 1 }} — description</label>
                                        <input name="content[{{ $specialty }}][{{ $locale }}][how_it_works][{{ $i }}][body]" value="{{ $step['body'] }}">
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
                                            <input name="content[{{ $specialty }}][{{ $locale }}][pricing][{{ $i }}][name]" value="{{ $tier['name'] }}">
                                        </div>
                                        <div>
                                            <label class="field-label">Plan {{ $i + 1 }} — description</label>
                                            <input name="content[{{ $specialty }}][{{ $locale }}][pricing][{{ $i }}][description]" value="{{ $tier['description'] }}">
                                        </div>
                                    </div>
                                    <div class="grid-2">
                                        <div>
                                            <label class="field-label">Monthly price</label>
                                            <input name="content[{{ $specialty }}][{{ $locale }}][pricing][{{ $i }}][price_monthly]" value="{{ $tier['price_monthly'] }}">
                                        </div>
                                        <div>
                                            <label class="field-label">Yearly price</label>
                                            <input name="content[{{ $specialty }}][{{ $locale }}][pricing][{{ $i }}][price_yearly]" value="{{ $tier['price_yearly'] }}">
                                        </div>
                                    </div>
                                    <div class="grid-2">
                                        <div>
                                            <label class="field-label">Button label</label>
                                            <input name="content[{{ $specialty }}][{{ $locale }}][pricing][{{ $i }}][cta_label]" value="{{ $tier['cta_label'] }}">
                                        </div>
                                        <div>
                                            <label class="field-label">Highlight this plan?</label>
                                            <select name="content[{{ $specialty }}][{{ $locale }}][pricing][{{ $i }}][highlighted]">
                                                <option value="0" @selected(! $tier['highlighted'])>No</option>
                                                <option value="1" @selected($tier['highlighted'])>Yes — "Most popular"</option>
                                            </select>
                                        </div>
                                    </div>
                                    <label class="field-label">Feature list (one per line)</label>
                                    <textarea name="content[{{ $specialty }}][{{ $locale }}][pricing][{{ $i }}][features]">{{ $tier['features'] }}</textarea>
                                </div>
                            @endforeach
                        </section>

                        <section class="panel">
                            <h3>Benefits</h3>
                            @foreach ($loc['benefits'] as $i => $benefit)
                                <div class="grid-2" style="margin-bottom: 1rem; padding-bottom: 1rem; {{ $loop->last ? '' : 'border-bottom: 1px solid var(--border);' }}">
                                    <div>
                                        <label class="field-label">Benefit {{ $i + 1 }} — title</label>
                                        <input name="content[{{ $specialty }}][{{ $locale }}][benefits][{{ $i }}][title]" value="{{ $benefit['title'] }}">
                                    </div>
                                    <div>
                                        <label class="field-label">Benefit {{ $i + 1 }} — description</label>
                                        <input name="content[{{ $specialty }}][{{ $locale }}][benefits][{{ $i }}][body]" value="{{ $benefit['body'] }}">
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
                                            <input name="content[{{ $specialty }}][{{ $locale }}][testimonials][{{ $i }}][name]" value="{{ $testimonial['name'] }}">
                                        </div>
                                        <div>
                                            <label class="field-label">Role / practice</label>
                                            <input name="content[{{ $specialty }}][{{ $locale }}][testimonials][{{ $i }}][role]" value="{{ $testimonial['role'] }}">
                                        </div>
                                    </div>
                                    <label class="field-label">Avatar initials (2 letters)</label>
                                    <input name="content[{{ $specialty }}][{{ $locale }}][testimonials][{{ $i }}][initials]" value="{{ $testimonial['initials'] }}" style="max-width: 100px;">
                                    <label class="field-label">Quote</label>
                                    <textarea name="content[{{ $specialty }}][{{ $locale }}][testimonials][{{ $i }}][quote]">{{ $testimonial['quote'] }}</textarea>
                                </div>
                            @endforeach
                        </section>

                        <section class="panel">
                            <h3>FAQ</h3>
                            @foreach ($loc['faq'] as $i => $item)
                                <div style="margin-bottom: 1rem; padding-bottom: 1rem; {{ $loop->last ? '' : 'border-bottom: 1px solid var(--border);' }}">
                                    <label class="field-label">Question {{ $i + 1 }}</label>
                                    <input name="content[{{ $specialty }}][{{ $locale }}][faq][{{ $i }}][question]" value="{{ $item['question'] }}">
                                    <label class="field-label">Answer</label>
                                    <textarea name="content[{{ $specialty }}][{{ $locale }}][faq][{{ $i }}][answer]">{{ $item['answer'] }}</textarea>
                                </div>
                            @endforeach
                        </section>

                        <section class="panel">
                            <h3>Contact Section</h3>
                            <p class="muted" style="margin-top: -.5rem; margin-bottom: 1rem;">Submitted messages appear under Admin → Inquiries.</p>
                            <label class="field-label">Eyebrow</label>
                            <input name="content[{{ $specialty }}][{{ $locale }}][contact][eyebrow]" value="{{ $loc['contact']['eyebrow'] }}">
                            <label class="field-label">Headline</label>
                            <input name="content[{{ $specialty }}][{{ $locale }}][contact][headline]" value="{{ $loc['contact']['headline'] }}">
                            <label class="field-label">Subtext</label>
                            <textarea name="content[{{ $specialty }}][{{ $locale }}][contact][subtext]">{{ $loc['contact']['subtext'] }}</textarea>
                            <div class="grid-2">
                                <div>
                                    <label class="field-label">Name field label</label>
                                    <input name="content[{{ $specialty }}][{{ $locale }}][contact][name_label]" value="{{ $loc['contact']['name_label'] }}">
                                </div>
                                <div>
                                    <label class="field-label">Email field label</label>
                                    <input name="content[{{ $specialty }}][{{ $locale }}][contact][email_label]" value="{{ $loc['contact']['email_label'] }}">
                                </div>
                            </div>
                            <div class="grid-2">
                                <div>
                                    <label class="field-label">Message field label</label>
                                    <input name="content[{{ $specialty }}][{{ $locale }}][contact][message_label]" value="{{ $loc['contact']['message_label'] }}">
                                </div>
                                <div>
                                    <label class="field-label">Submit button label</label>
                                    <input name="content[{{ $specialty }}][{{ $locale }}][contact][submit_label]" value="{{ $loc['contact']['submit_label'] }}">
                                </div>
                            </div>
                            <label class="field-label">Success message</label>
                            <input name="content[{{ $specialty }}][{{ $locale }}][contact][success_message]" value="{{ $loc['contact']['success_message'] }}">
                        </section>

                        <section class="panel">
                            <h3>Get a Quote Section</h3>
                            <p class="muted" style="margin-top: -.5rem; margin-bottom: 1rem;">Submitted requests appear under Admin → Inquiries.</p>
                            <label class="field-label">Eyebrow</label>
                            <input name="content[{{ $specialty }}][{{ $locale }}][quote][eyebrow]" value="{{ $loc['quote']['eyebrow'] }}">
                            <label class="field-label">Headline</label>
                            <input name="content[{{ $specialty }}][{{ $locale }}][quote][headline]" value="{{ $loc['quote']['headline'] }}">
                            <label class="field-label">Subtext</label>
                            <textarea name="content[{{ $specialty }}][{{ $locale }}][quote][subtext]">{{ $loc['quote']['subtext'] }}</textarea>
                            <div class="grid-2">
                                <div>
                                    <label class="field-label">Name field label</label>
                                    <input name="content[{{ $specialty }}][{{ $locale }}][quote][name_label]" value="{{ $loc['quote']['name_label'] }}">
                                </div>
                                <div>
                                    <label class="field-label">Email field label</label>
                                    <input name="content[{{ $specialty }}][{{ $locale }}][quote][email_label]" value="{{ $loc['quote']['email_label'] }}">
                                </div>
                            </div>
                            <div class="grid-2">
                                <div>
                                    <label class="field-label">Phone field label</label>
                                    <input name="content[{{ $specialty }}][{{ $locale }}][quote][phone_label]" value="{{ $loc['quote']['phone_label'] }}">
                                </div>
                                <div>
                                    <label class="field-label">Clinic / company field label</label>
                                    <input name="content[{{ $specialty }}][{{ $locale }}][quote][company_label]" value="{{ $loc['quote']['company_label'] }}">
                                </div>
                            </div>
                            <div class="grid-2">
                                <div>
                                    <label class="field-label">Message field label</label>
                                    <input name="content[{{ $specialty }}][{{ $locale }}][quote][message_label]" value="{{ $loc['quote']['message_label'] }}">
                                </div>
                                <div>
                                    <label class="field-label">Submit button label</label>
                                    <input name="content[{{ $specialty }}][{{ $locale }}][quote][submit_label]" value="{{ $loc['quote']['submit_label'] }}">
                                </div>
                            </div>
                            <label class="field-label">Success message</label>
                            <input name="content[{{ $specialty }}][{{ $locale }}][quote][success_message]" value="{{ $loc['quote']['success_message'] }}">
                        </section>

                        <section class="panel">
                            <h3>Final CTA</h3>
                            <label class="field-label">Headline</label>
                            <input name="content[{{ $specialty }}][{{ $locale }}][final_cta][headline]" value="{{ $loc['final_cta']['headline'] }}">
                            <label class="field-label">Subtext</label>
                            <textarea name="content[{{ $specialty }}][{{ $locale }}][final_cta][subtext]">{{ $loc['final_cta']['subtext'] }}</textarea>
                            <div class="grid-2">
                                <div>
                                    <label class="field-label">Button label</label>
                                    <input name="content[{{ $specialty }}][{{ $locale }}][final_cta][button_label]" value="{{ $loc['final_cta']['button_label'] }}">
                                </div>
                                <div>
                                    <label class="field-label">Button email (mailto)</label>
                                    <input name="content[{{ $specialty }}][{{ $locale }}][final_cta][button_email]" value="{{ $loc['final_cta']['button_email'] }}">
                                </div>
                            </div>
                            <label class="field-label">Fine-print note</label>
                            <input name="content[{{ $specialty }}][{{ $locale }}][final_cta][note]" value="{{ $loc['final_cta']['note'] }}">
                        </section>

                        <section class="panel">
                            <h3>Footer</h3>
                            <label class="field-label">Tagline</label>
                            <input name="content[{{ $specialty }}][{{ $locale }}][footer][tagline]" value="{{ $loc['footer']['tagline'] }}">
                            <div class="grid-2">
                                <div>
                                    <label class="field-label">Contact email</label>
                                    <input name="content[{{ $specialty }}][{{ $locale }}][footer][contact_email]" value="{{ $loc['footer']['contact_email'] }}">
                                </div>
                                <div>
                                    <label class="field-label">Copyright name</label>
                                    <input name="content[{{ $specialty }}][{{ $locale }}][footer][copyright_name]" value="{{ $loc['footer']['copyright_name'] }}">
                                </div>
                            </div>
                        </section>
                    </div>
                @endforeach
            </div>
        @endforeach

        <div class="actions-row" style="margin-top: .5rem;">
            <button class="btn" type="submit">Save all changes</button>
        </div>
    </form>

    <script>
        // Product tabs: show one product panel at a time. Each panel owns its
        // own independent set of language tabs/panels (wired up below), so
        // switching products never disturbs another product's selected language.
        document.querySelectorAll('[data-product-tab]').forEach(function (tab) {
            tab.addEventListener('click', function () {
                var product = tab.dataset.productTab;
                document.querySelectorAll('[data-product-tab]').forEach(function (t) { t.classList.toggle('active', t === tab); });
                document.querySelectorAll('[data-product-panel]').forEach(function (panel) {
                    panel.classList.toggle('active', panel.dataset.productPanel === product);
                });
            });
        });

        // Language tabs, scoped to whichever product panel they live in.
        document.querySelectorAll('[data-product-panel]').forEach(function (productPanel) {
            productPanel.querySelectorAll('[data-lang-tab]').forEach(function (tab) {
                tab.addEventListener('click', function () {
                    var locale = tab.dataset.langTab;
                    productPanel.querySelectorAll('[data-lang-tab]').forEach(function (t) { t.classList.toggle('active', t === tab); });
                    productPanel.querySelectorAll('[data-lang-panel]').forEach(function (panel) {
                        panel.classList.toggle('active', panel.dataset.langPanel === locale);
                    });
                });
            });
        });
    </script>
@endsection
