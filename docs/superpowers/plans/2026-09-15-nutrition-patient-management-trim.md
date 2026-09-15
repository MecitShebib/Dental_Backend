# Nutrition Patient-Management Trim Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Remove the dental-only sections (X-ray tab, X-Ray Images/CBCT Scans/Lab sidebar links) from the Nutrition (Dietavaria) specialty's patient-management UI, per section 2 of `docs/superpowers/specs/2026-09-15-nutrition-specialty-expansion-design.md`.

**Architecture:** Pure `Dental_FrontEnd` frontend change, two files. `NutritionClientDetailsPage.jsx` loses its "Röntgen" tab (button + panel + import). The nav-visibility gate already centralized in `AppStateApiContext.jsx`'s `localizedNavItems` (`isVisible` predicate, consumed by `Sidebar.jsx` via `useAppState().NAV_ITEMS`) gets a specialty-keyed hidden-ids map, mirroring the existing `SPECIALTY_AI_API_BY_KEY` constant's shape. No backend files change — `xray-images`, `dicom-studies`, and `/lab/cases` stay fully functional and reachable for dental; only nutrition's nav *links* to them disappear.

**Tech Stack:** React 18 (Vite), no frontend test runner configured in this repo (confirmed: no vitest/jest config or `*.test.jsx` files outside `node_modules`) — verification is `npm run lint`, `npm run build`, and a manual browser check, matching this project's existing established workflow (see CLAUDE.md: "For UI or frontend changes, start the dev server and use the feature in a browser before reporting the task as complete").

---

### Task 1: Remove the Röntgen (X-ray) tab from the nutrition Client Details page

**Files:**
- Modify: `C:\Users\MK\Desktop\Dental_FrontEnd\app\frontend\src\specialties\nutrition\pages\NutritionClientDetailsPage.jsx`

- [ ] **Step 1: Remove the now-unused `XrayImagePicker` import**

At line 13, delete this line entirely:

```jsx
import XrayImagePicker from "../../../components/XrayImagePicker";
```

- [ ] **Step 2: Remove the "xray" tab-bar entry and tidy the now-stale comment**

Find this block (around lines 193–197):

```jsx
            // Last two tabs, not right-side toolbar buttons anymore -- AI
            // Treatment Assistant and X-Ray both open inline in the page now
            // (see the render blocks below), same as dental's ClientDetailsPage.
            authUser?.isDoctor || isSystemManager ? { id: "ai", label: t("tabAi") } : null,
            { id: "xray", label: t("tabXray") },
```

Replace it with:

```jsx
            // Last tab, not a right-side toolbar button anymore -- AI
            // Treatment Assistant opens inline in the page now (see the
            // render blocks below), same as dental's ClientDetailsPage.
            authUser?.isDoctor || isSystemManager ? { id: "ai", label: t("tabAi") } : null,
```

- [ ] **Step 3: Remove the "xray" panel render block**

Find this block (around lines 456–460):

```jsx
      {activePanel === "xray" ? (
        <article className="detail-card">
          <XrayImagePicker showHeading />
        </article>
      ) : null}

```

Delete it entirely (including the blank line immediately after it, so the `NutritionCarePlanModal` block that follows doesn't end up with a double blank line before it).

- [ ] **Step 4: Verify no "xray"/"Xray" references remain in this file**

Run: `grep -in xray "C:\Users\MK\Desktop\Dental_FrontEnd\app\frontend\src\specialties\nutrition\pages\NutritionClientDetailsPage.jsx"`
Expected: no output (no matches).

- [ ] **Step 5: Commit**

```bash
cd "C:\Users\MK\Desktop\Dental_FrontEnd"
git add app/frontend/src/specialties/nutrition/pages/NutritionClientDetailsPage.jsx
git commit -m "$(cat <<'EOF'
feat: remove X-ray tab from nutrition client details page

X-ray imagery is a dental/imaging concept with no nutrition
equivalent -- part of the nutrition patient-management trim in
docs/superpowers/specs/2026-09-15-nutrition-specialty-expansion-design.md.

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>
EOF
)"
```

---

### Task 2: Hide dental-only sidebar nav items for the nutrition specialty

**Files:**
- Modify: `C:\Users\MK\Desktop\Dental_FrontEnd\app\frontend\src\context\AppStateApiContext.jsx:21-27` (new constant, next to `SPECIALTY_AI_API_BY_KEY`)
- Modify: `C:\Users\MK\Desktop\Dental_FrontEnd\app\frontend\src\context\AppStateApiContext.jsx:1144-1169` (`localizedNavItems` useMemo)

- [ ] **Step 1: Add the specialty-keyed hidden-nav-ids constant**

In `AppStateApiContext.jsx`, immediately after the existing `SPECIALTY_AI_API_BY_KEY` constant (currently lines 21–27), add:

```jsx
// NAV_ITEMS ids to hide from the sidebar for a given activeSpecialtyKey --
// items with no entry here (or no matching specialty key) are unaffected.
// Nutrition hides dental-imaging/dental-lab links that have no nutrition
// equivalent (see docs/superpowers/specs/2026-09-15-nutrition-specialty-expansion-design.md
// section 2); other specialties currently have no entries.
const NAV_IDS_HIDDEN_BY_SPECIALTY = {
  nutrition: ["xray-images", "dicom-studies", "lab"],
};
```

So the top of the file reads:

```jsx
const SPECIALTY_AI_API_BY_KEY = {
  gynecology: api.gynecology.ai,
  internal_medicine: api.internalMedicine.ai,
  orthopedics: api.orthopedics.ai,
  cosmetic: api.cosmetic.ai,
  nutrition: api.nutrition.ai,
};

// NAV_ITEMS ids to hide from the sidebar for a given activeSpecialtyKey --
// items with no entry here (or no matching specialty key) are unaffected.
// Nutrition hides dental-imaging/dental-lab links that have no nutrition
// equivalent (see docs/superpowers/specs/2026-09-15-nutrition-specialty-expansion-design.md
// section 2); other specialties currently have no entries.
const NAV_IDS_HIDDEN_BY_SPECIALTY = {
  nutrition: ["xray-images", "dicom-studies", "lab"],
};

const TOKEN_STORAGE_KEY = "dental_api_token";
```

- [ ] **Step 2: Apply the hidden-ids check inside `isVisible`, and add `activeSpecialtyKey` to the memo's dependencies**

Find the `localizedNavItems` useMemo (currently lines 1144–1169):

```jsx
  const localizedNavItems = useMemo(() => {
    // A NAV_ITEMS entry's role gate (systemManagerOnly/accountingOnly) is the
    // default requirement, but an explicit Access Permissions grant (Settings
    // > Users, e.g. "Manage users") is meant to unlock the matching section
    // for someone who doesn't hold that role -- a doctor given "Manage users"
    // should see Users even though they're not a System Manager. Without this,
    // the permission checkboxes admins can already grant per-user never
    // actually opened up anything in the nav.
    const grantedByPermission = (entry) => Boolean(entry.permissionSlug) && hasPermission(entry.permissionSlug);

    const isVisible = (entry) =>
      grantedByPermission(entry) ||
      ((!entry.systemManagerOnly || isSystemManager) && (!entry.accountingOnly || canAccessAccounting));

    return NAV_ITEMS.filter(isVisible)
      .map((item) => {
        if (!item.children) {
          return { ...item, label: t(item.labelKey) };
        }

        const children = item.children.filter(isVisible).map((child) => ({ ...child, label: t(child.labelKey) }));

        return { ...item, label: t(item.labelKey), children };
      })
      .filter((item) => !item.children || item.children.length > 0);
  }, [canAccessAccounting, hasPermission, isSystemManager, t]);
```

Replace it with:

```jsx
  const localizedNavItems = useMemo(() => {
    // A NAV_ITEMS entry's role gate (systemManagerOnly/accountingOnly) is the
    // default requirement, but an explicit Access Permissions grant (Settings
    // > Users, e.g. "Manage users") is meant to unlock the matching section
    // for someone who doesn't hold that role -- a doctor given "Manage users"
    // should see Users even though they're not a System Manager. Without this,
    // the permission checkboxes admins can already grant per-user never
    // actually opened up anything in the nav.
    const grantedByPermission = (entry) => Boolean(entry.permissionSlug) && hasPermission(entry.permissionSlug);

    // A specialty-hidden id is hidden unconditionally -- it wins over both
    // the role gate and any permission grant, since these are items with no
    // meaning at all in that specialty (see NAV_IDS_HIDDEN_BY_SPECIALTY).
    const hiddenBySpecialty = NAV_IDS_HIDDEN_BY_SPECIALTY[activeSpecialtyKey] || [];

    const isVisible = (entry) =>
      !hiddenBySpecialty.includes(entry.id) &&
      (grantedByPermission(entry) ||
        ((!entry.systemManagerOnly || isSystemManager) && (!entry.accountingOnly || canAccessAccounting)));

    return NAV_ITEMS.filter(isVisible)
      .map((item) => {
        if (!item.children) {
          return { ...item, label: t(item.labelKey) };
        }

        const children = item.children.filter(isVisible).map((child) => ({ ...child, label: t(child.labelKey) }));

        return { ...item, label: t(item.labelKey), children };
      })
      .filter((item) => !item.children || item.children.length > 0);
  }, [activeSpecialtyKey, canAccessAccounting, hasPermission, isSystemManager, t]);
```

- [ ] **Step 3: Verify the edit landed correctly**

Run: `grep -n "NAV_IDS_HIDDEN_BY_SPECIALTY\|hiddenBySpecialty" "C:\Users\MK\Desktop\Dental_FrontEnd\app\frontend\src\context\AppStateApiContext.jsx"`
Expected output (3 lines): the constant declaration, the `hiddenBySpecialty` assignment inside the memo, and its use inside `isVisible`.

- [ ] **Step 4: Commit**

```bash
cd "C:\Users\MK\Desktop\Dental_FrontEnd"
git add app/frontend/src/context/AppStateApiContext.jsx
git commit -m "$(cat <<'EOF'
feat: hide dental-only sidebar links for the nutrition specialty

X-Ray Images, CBCT Scans, and Lab (dental crown/bridge/veneer case
tracking) have no nutrition equivalent -- part of the nutrition
patient-management trim in
docs/superpowers/specs/2026-09-15-nutrition-specialty-expansion-design.md.

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>
EOF
)"
```

---

### Task 3.5: Remove the X-ray row action from the nutrition Patients list

> Added after the final integration review caught a leftover path: `NutritionPatientsPage.jsx` has its own per-row X-ray icon button (`PatientXrayModal` → `XrayImagePicker`), copy-pasted from the shared Patients-page template, that neither Task 1 (Client Details page only) nor Task 2 (sidebar only) addressed. Same removal pattern as Task 1, different file.

**Files:**
- Modify: `C:\Users\MK\Desktop\Dental_FrontEnd\app\frontend\src\specialties\nutrition\pages\NutritionPatientsPage.jsx`

- [ ] **Step 1: Remove the now-unused `PatientXrayModal` import**

At line 14, delete:

```jsx
import PatientXrayModal from "../../../components/PatientXrayModal";
```

- [ ] **Step 2: Remove the X-ray row-action button**

Find this block (around lines 242–250):

```jsx
                      <button
                        type="button"
                        className="icon-button"
                        onClick={() => openModalForPatient("xray", patient)}
                        aria-label={t("xrayImages")}
                        title={t("xrayImages")}
                      >
                        <ActionIcon kind="xray" />
                      </button>
```

Delete it entirely. (The "lab" button immediately above it, at lines 233–241, opens `PatientLabResultsModal` — the generic blood-work Lab Results feature, a *different* "lab" than the dental lab-case tracker hidden in Task 2. Leave it untouched.)

- [ ] **Step 3: Remove the `PatientXrayModal` render block**

Find this line (around line 324):

```jsx
        <PatientXrayModal open={activeModal === "xray" && Boolean(activePatient)} onClose={closeModal} />
```

Delete it entirely.

- [ ] **Step 4: Verify no "xray"/"PatientXrayModal" references remain in this file**

Run: `grep -in "xray" "C:\Users\MK\Desktop\Dental_FrontEnd\app\frontend\src\specialties\nutrition\pages\NutritionPatientsPage.jsx"`
Expected: no output.

- [ ] **Step 5: Commit**

```bash
cd "C:\Users\MK\Desktop\Dental_FrontEnd"
git add app/frontend/src/specialties/nutrition/pages/NutritionPatientsPage.jsx
git commit -m "$(cat <<'EOF'
feat: remove X-ray row action from nutrition patients list

Same rationale as the Client Details page's X-ray tab removal --
X-ray imagery has no nutrition equivalent. This row action
(PatientXrayModal) was a separate leftover path the first pass of
the nutrition patient-management trim missed, caught by the final
integration review.

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>
EOF
)"
```

---

### Task 3: Lint, build, deploy the built assets, and verify in the browser

**Files:**
- No new files. This task builds and copies the already-committed changes from Tasks 1–2.

- [ ] **Step 1: Lint the frontend**

Run:
```bash
cd "C:\Users\MK\Desktop\Dental_FrontEnd\app\frontend"
npm run lint
```
Expected: exits with no errors. If it flags an unused variable/import unrelated to `XrayImagePicker` (pre-existing lint debt), confirm it's not something Task 1/2 introduced before moving on; if it flags `XrayImagePicker` or `activeSpecialtyKey` as unused/undefined, that means a step above was missed — go back and fix it.

- [ ] **Step 2: Build the frontend**

Run:
```bash
cd "C:\Users\MK\Desktop\Dental_FrontEnd\app\frontend"
npm run build
```
Expected: build completes successfully and writes to `C:\Users\MK\Desktop\Dental_FrontEnd\app\frontend\dist\`.

- [ ] **Step 3: Deploy the built assets into the backend's public directory**

This project's established convention (confirmed via recent commit history — e.g. `4ff72f3 chore: refresh built frontend assets in public/app`) is to fully replace `Dental_Backend/public/app/` with the fresh `dist/` output, since Vite fingerprints filenames per build and a plain overlay copy would leave stale hashed files behind.

Run:
```bash
rm -rf "C:\Users\MK\Desktop\Dental_Backend\public\app"/*
cp -r "C:\Users\MK\Desktop\Dental_FrontEnd\app\frontend\dist"/* "C:\Users\MK\Desktop\Dental_Backend\public\app\"
```
Expected: no errors; `git -C "C:\Users\MK\Desktop\Dental_Backend" status` shows changes under `public/app/` (typically renamed/hashed asset files plus `index.html`).

- [ ] **Step 4: Commit the refreshed built assets in the backend repo**

```bash
cd "C:\Users\MK\Desktop\Dental_Backend"
git add public/app
git commit -m "$(cat <<'EOF'
chore: refresh built frontend assets in public/app

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>
EOF
)"
```

- [ ] **Step 5: Manual browser verification**

This UI change has no automated test coverage (none exists for this repo's frontend), so it must be confirmed working in a real browser before it's considered done, per this project's standing convention.

1. Start the backend: `cd "C:\Users\MK\Desktop\Dental_Backend" && composer run dev` (starts server + queue + logs + Vite together).
2. If the nutrition specialty has no local demo data yet, seed it: `php artisan db:seed --class=DemoDataSeeder` (local-only seeder; creates, among others, a nutrition doctor at `doctor.nutrition@clinic.com` per `database/seeders/DemoDataSeeder.php:180`).
3. Log in as that nutrition doctor through the normal OTP flow (since `ILETIMERKEZI_ENABLED` is `false` locally, the OTP code is printed to `storage/logs/laravel.log` / the console instead of sent by SMS).
4. Open any patient's Client Details page inside the Dietavaria app. Confirm:
   - The tab bar no longer shows a "Röntgen"/X-ray tab (only Hasta Verisi, Bakım Planları, Randevular, Lab Sonuçları, Reçeteler, Ödemeler, Onam, Zaman Çizelgesi, and — for a doctor/system manager — AI).
   - The left sidebar does **not** show "X-Ray Images", "CBCT Scans", or "Lab".
5. Log in as (or switch to) a dental-specialty user and confirm "X-Ray Images", "CBCT Scans", and "Lab" are still present in dental's sidebar, and dental's own Client Details page still has its X-ray-related tab — this change must not affect dental.

- [ ] **Step 6: Report the verification result to the user**

State plainly what you observed in the browser (tabs/sidebar for nutrition, unaffected dental sidebar) before considering this plan complete — do not claim success without having actually looked.
