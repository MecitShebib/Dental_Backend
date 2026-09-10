# Dentavaria — 30-Second Vertical Ad — Shot List

**Files in this folder:**
- `dentavaria_ad_30s.mp4` — the final render. 1080×1920, 30fps, H.264, 30.000s exact, silent AAC audio track (no music/SFX embedded — see notes below). Ready to upload to Reels/TikTok/Shorts as-is.
- `dentavaria_ad_source.html` — the source it was rendered from. Open it in any browser to preview live, or edit copy/timing and re-render (see "Re-rendering" below).

**Everything in the video is real** — captured live from `doctovaria.com.tr/dentavaria` and the logged-in app (Dashboard, Patients list, Client financial summary, 3D Odontogram before/after, Appointments/visits table, AI Treatment Assistant, and the real "Doctor Dentavaria" logo). Nothing is a fabricated mockup, feature, or statistic.

---

## 0:00 – 0:03 — HOOK

| | |
|---|---|
| **Visual** | Device-frame shot of the **Dashboard** — stat row (Total Appointments, Completed, Total Income, Cancelled/No-show) + the donut chart and income chart. Slow zoom-in, biased toward the income chart (upper-right). |
| **On-screen text** | "Live product, not a mockup" (small pill) → **"Running a dental clinic shouldn't feel this complicated."** (last two words in accent blue) |
| **Audio cue** | Music starts on frame 1 — a rising synth/piano note or riser hit landing as the headline lands. |

## 0:03 – 0:07 — PROBLEM

| | |
|---|---|
| **Visual** | Dark background, five tumbling pill-chips: **"Excel sheets," "Sticky notes," "WhatsApp reminders," "Missed follow-ups," "Paper folders."** Staggered entrance. |
| **On-screen text** | **"Still juggling your clinic on spreadsheets and sticky notes?"** |
| **Audio cue** | 5 short, quiet "tick" SFX synced to each chip's entrance. Music stays sparse/tense here. |

## 0:07 – 0:15 — SOLUTION

Three real screens in one continuous device frame, connected by a simulated cursor:

| Time | Screen | Cursor action |
|---|---|---|
| 0:07.0–0:09.8 | **Patients list** | Cursor moves toward the "Yasmin Al-Ahmad" row |
| 0:09.55 | — | **Click** ripple on the row's "View" icon |
| 0:09.8–0:12.4 | **Client financial summary** (Services Total / Payments Total / Remaining Balance) | Cursor moves toward the tab bar |
| 0:12.3 | — | **Click** ripple on a tab |
| 0:12.4–0:15.0 | **Dashboard** (chart-side crop) | — |
| **On-screen text** | **"Meet Dentavaria."** then **"The clinical operating system for modern dental practices."** *(verbatim tagline from the real Dentavaria landing page)* |
| **Audio cue** | Music opens up / energetic section begins. Two soft UI-click SFX at 0:09.55 and 0:12.3. |

## 0:15 – 0:22 — KEY FEATURES (real feature names, lifted from Dentavaria's own feature grid)

| Time | Caption | Screen |
|---|---|---|
| 0:15.0–0:16.75 | **AI Treatment Plan Assistant** | AI chat modal on a patient record |
| 0:16.75–0:18.5 | **Digital Treatment Records** | 3D Odontogram — Before/After Treatment Comparison |
| 0:18.5–0:20.25 | **Smart Scheduling** | Appointments/visit-history table |
| 0:20.25–0:22.0 | **Financial Clarity** | Dashboard income chart, tight crop |
| **Audio cue** | A short "whoosh"/filter-sweep on each of the 4 cuts — subtle and consistent. |

## 0:22 – 0:27 — VALUE

| | |
|---|---|
| **Visual** | The real Appointments/Visits table again, calmer crop, slow zoom. |
| **On-screen text** | **"Less time on admin. More time with patients."** *(near-verbatim from the real landing page's own subheading)* |
| **Audio cue** | Music simplifies/settles — the "exhale" before the CTA. |

## 0:27 – 0:30 — CTA

| | |
|---|---|
| **Visual** | Navy background. Real **"Doctor Dentavaria" logo** on a white card with a soft blue glow. |
| **On-screen text** | Tagline → Button: **"Discover Dentavaria"** → URL: **doctovaria.com.tr/dentavaria** |
| **Audio cue** | Music resolves as the logo lands; optional soft brand "chime" under the reveal. |

---

## Notes

- **No music or SFX are embedded** in the mp4 — I have no licensed audio assets to attach, so the video currently has a silent (but present) audio track. The cues above are placement guidance for whoever adds a soundtrack in Premiere/CapCut/etc.
- **Two demo-data labels are technically visible but small and off-focus:** the Appointments table (Solution and Value beats) shows "Seeded demo appointment" / "Seeded demo visit" in its Notes column — real seeded test data, not fabricated for the ad, but worth swapping for a real (or better-anonymized) record before this ships publicly if you want zero chance of a viewer noticing on a freeze-frame.
- Every feature name and the tagline are copied verbatim from Dentavaria's own live marketing site so nothing in this ad claims a capability that isn't real.
- The authenticated screens were captured using the seeded demo dental doctor account (Dr. Layan) — no real patient data was created, edited, or deleted; every session was read-only navigation.

## Re-rendering

`dentavaria_ad_source.html` exposes a deterministic `window.__renderFrame(t)` function (t in seconds, 0–30) that paints the exact frame for that instant — no live clock involved. That's what made a frame-accurate export possible. To change copy/timing and re-render to video:

1. Edit the `data-start`/`data-end`/text inside the HTML.
2. Render 900 PNG frames (30fps × 30s) by calling `__renderFrame(i/30)` for `i` in `0..899` via a headless-Chrome script and screenshotting each at 1080×1920.
3. Stitch with ffmpeg:
   ```
   ffmpeg -framerate 30 -i frame_%04d.png -f lavfi -i anullsrc=r=44100:cl=stereo -shortest \
     -c:v libx264 -pix_fmt yuv420p -crf 18 -c:a aac -b:a 128k -movflags +faststart out.mp4
   ```
