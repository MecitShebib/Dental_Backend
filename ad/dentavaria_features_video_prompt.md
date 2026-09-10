# Video Generation Prompt — Dentavaria Feature Walkthrough

Use this prompt as-is with a video generation tool (Sora, Runway, Pika, etc.) or hand it to a video editor / AI motion-graphics tool as a creative brief. It describes a short vertical explainer video for **Dentavaria**, the dental module of the Doctovaria clinical platform.

---

## PROMPT

Create a short, clean, and informative product-explainer video for **Dentavaria**, a clinical management platform for dental practices. Use real screenshots and UI screens of the actual product (provided separately) — do not invent or redesign the interface.

**GOAL:**
Walk a first-time viewer through what Dentavaria actually does, feature by feature, in plain and simple language. This is an explainer, not a hard-sell ad — the tone is helpful and clear, not hype-driven. By the end, the viewer should understand the product covers patient records, AI-assisted diagnosis and treatment planning, scheduling, and the business/financial side of running a dental clinic, all in one system.

**LENGTH:**
45–60 seconds (flexible: a tighter 30-second cut may group features into fewer categories; a fuller 60-second cut can give each feature its own beat).

**FORMAT:**
- Vertical 9:16, exported at 1080 × 1920 px
- Optimized for Instagram Reels, TikTok, and YouTube Shorts
- Keep essential text and UI focal points inside the center-safe area — avoid the top ~250px and bottom ~250px of the frame, since Reels/TikTok/Shorts overlay their own UI (captions, profile info, like/share buttons) in those zones
- Clean corporate/SaaS explainer style — think "product tour," not "movie trailer"

**BRAND COLORS (use exactly — these are Dentavaria's real, live brand colors):**
- Primary accent (buttons, highlights, active states): `#1f4e8c`
- Secondary accent (gradients, depth): `#163a6b`
- Sidebar / dark UI surface: `#182335` (active row state: `#22314a`)
- Dark-mode background surfaces: `#10141c` → `#171c26` → `#1d232f` → `#232a38`
- Light-mode content background: `#ffffff` / `#f4f6f9`
- Primary text on dark: `#eef1f6`; secondary/muted text on dark: `#aab4c4`
- Primary text on light: `#1a2233`
- Borders/dividers: `rgba(255,255,255,0.09)` on dark, `#dde1e6` on light
- Typography: clean system sans-serif (Segoe UI / system-ui stack) — matches the real product's own UI, no decorative or script fonts
- Logo: the real "Doctor Dentavaria" wordmark (navy-to-blue gradient wordmark with a checkmark motif built into the second "V") — use it in the intro and/or closing frame, never redrawn or reinterpreted

**VIDEO STRUCTURE:**

*0:00–0:04 — Intro*
A confident, simple opening line establishing what the product is, over a live shot of the Dashboard screen. Example line: "This is Dentavaria — everything a dental clinic needs, in one place." Show the real logo briefly.

*0:04–0:40 (approx.) — Feature walkthrough*
Move through the product's real feature areas in short, clearly-labeled beats (roughly 4–6 seconds each depending on final length). Each beat = one real screen + one short on-screen label naming the feature plainly. Group related features together so the pacing feels like a guided tour, not a rushed list:

1. **Patient Records** — "Every patient's history, chart, and documents in one record." *(Client Details page: Client Data / Diagnosis / Appointments / Payments / Consent / Timeline tabs)*
2. **3D Odontogram** — "An interactive, tooth-by-tooth 3D chart for diagnosis and treatment planning." *(3D Odontogram tab, before/after treatment comparison)*
3. **AI Treatment Assistant** — "Describe a case, and the AI builds a full treatment plan for the doctor to review." *(AI Treatment Assistant chat)*
4. **AI X-ray Analysis** — "Upload an X-ray and get automatic, tooth-by-tooth findings." *(X-ray upload/analysis view, if available)*
5. **Scheduling** — "Book and manage appointments without double-booking a doctor." *(Appointments view)*
6. **X-Ray & Image Library** — "Every X-ray and image, organized and linked to the right patient." *(X-Ray Images gallery)*
7. **Lab Tracking** — "Track outsourced lab work — crowns, bridges — from sent to delivered." *(Lab Cases view)*
8. **Inventory** — "Keep clinic supplies and stock levels under control." *(Inventory view)*
9. **Financial Clarity** — "Every charge and payment tracked automatically, with a real-time balance per patient." *(Dashboard income chart / Client financial summary cards)*
10. **Accounting & Payroll** — "A full company ledger, expenses, and payroll — including each doctor's commission — handled automatically." *(Accounting/Reports view, if available)*
11. **Secure Access** — "OTP-verified sign-in for every device — no shared passwords." *(Login/OTP screen)*

Use only the features above that you have real screenshots for — do not fabricate a screen for one you don't have. If time is tight, keep at minimum: Patient Records, 3D Odontogram, AI Treatment Assistant, Scheduling, and Financial Clarity — these five are the most visually distinct and easiest for a new viewer to grasp quickly.

*0:40–0:48 — Wrap-up line*
One simple summary sentence over a calm shot of the Dashboard, e.g., "Patient records, AI-assisted planning, scheduling, and billing — all in one clinical platform." No hard sales language.

*0:48–0:55 (final ~5–8s) — Closing*
Real Dentavaria logo, tagline: "The clinical operating system for modern dental practices." Small CTA: "Discover Dentavaria" + URL text `doctovaria.com.tr/dentavaria`.

**VISUAL STYLE:**
- Every screen shown must be the real Dentavaria interface — real sidebar, real data, real layout. No fictional dashboards, no generic stock SaaS mockups.
- Frame each screen inside a simple browser-style device card (thin top bar with 3 neutral dots + the real URL, e.g. `doctovaria.com.tr/app/dental/dashboard`) so it reads as "a real product," not an abstract graphic.
- Background around the device card: a dark navy gradient (using the surface colors above), not pure black and not a generic purple/blue gradient — keep it restrained and clinical, not flashy.
- Smooth Ken Burns zoom/pan on each screenshot to keep it feeling alive, biased toward whatever part of the screen is being labeled (e.g., zoom toward the chart when talking about financials, zoom toward the tooth model for the odontogram).

**MOTION:**
- Gentle continuous zoom-in on each screen (subtle, not dizzying)
- Simple crossfade transitions between feature beats (0.4–0.5s)
- Optional: a simulated cursor click on a nav item or tab right before cutting to the next feature, to imply "the user is actually navigating," not just watching a slideshow
- No spinning logos, no glitch effects, no lens flares — this is a clinical/professional product, keep motion calm and confident

**ON-SCREEN TEXT:**
- Short, plain-language labels only (3–6 words) — name the feature the way a person would describe it, not internal jargon
- Consistent placement per beat (e.g., always bottom-third, inside the safe zone)
- Use the real feature names from the list above, not invented marketing terms

**AUDIO:**
- Light, modern background music — calm and professional, not high-energy hype-ad music (this is an explainer, not a trailer)
- Optional soft "tick" or "whoosh" sound on each transition between features, quiet and consistent
- No voiceover required, but if one is used: clear, calm, plain-language narration reading roughly the same short lines as the on-screen labels

**IMPORTANT CONSTRAINTS:**
- Do not invent features, integrations, statistics, or customers that aren't listed above.
- Do not redesign or reimagine the product's UI — use it exactly as it exists.
- Only show a feature if a real screenshot/recording of it is available; skip anything you'd have to fake.
- Keep the tone informative and simple throughout — this video's job is to help someone understand the product quickly, not to persuade them with urgency or hype language.

---

## Reference assets already available in this project

- `ad/dentavaria_ad_30s.mp4` — a previously produced 30-second *persuasion-style* ad (hook → problem → solution → features → CTA) built from real Dentavaria screenshots. Different goal from this prompt (that one sells; this one explains), but it reuses the same real screens and can be mined for footage/timing ideas.
- `ad/dentavaria_ad_source.html` — the deterministic, frame-accurate HTML source that ad was rendered from (exposes a `window.__renderFrame(t)` function used to export it to video frame-by-frame via headless Chrome + ffmpeg). The same technique and screen captures can be reused/extended to actually produce the video this prompt describes, if generating it locally rather than through an AI video tool.
