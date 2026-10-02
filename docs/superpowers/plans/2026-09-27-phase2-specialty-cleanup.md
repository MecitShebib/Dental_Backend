# Aşama 2 — 9 Uzmanlıkta Silme / Yeniden Adlandırma Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: superpowers:executing-plans. Steps use checkbox (`- [ ]`) syntax.

**Goal:** Spec'teki Aşama 2 tablosunu uygulamak: dental'dan miras kalan gereksiz menü/sekme/işlemleri 9 uzmanlıktan kaldırmak, kalanları uzmanlığa uygun adlandırmak.

**Architecture:** Yalnızca frontend (Dental_FrontEnd/app/frontend). Sidebar için mevcut `NAV_IDS_HIDDEN_BY_SPECIALTY` genişletilir ve yanına `NAV_LABEL_KEYS_BY_SPECIALTY` (id → labelKey) eklenir; hasta detayı sekmeleri ve hasta listesi satır işlemleri her uzmanlığın kendi sayfa dosyasında değişir (kopyala-yapıştır mimarisi). Dental ve nutrition dosyalarına dokunulmaz (`NAV_IDS_HIDDEN_BY_SPECIALTY.nutrition` satırı olduğu gibi kalır).

**Spec:** `docs/superpowers/specs/2026-09-27-five-new-specialties-and-specialty-cleanup-design.md` § Aşama 2.

---

### Task 1: Sidebar gizleme + etiketler

**Files:** Modify `src/context/AppStateApiContext.jsx`

- [ ] `NAV_IDS_HIDDEN_BY_SPECIALTY`'ye ekle:

```js
  gynecology: ["lab", "dicom-studies", "xray-images"],
  internal_medicine: ["lab", "dicom-studies"],
  orthopedics: ["lab"],
  cosmetic: ["lab", "dicom-studies", "xray-images"],
  pediatrics: ["lab", "dicom-studies"],
  physiotherapy: ["lab", "dicom-studies"],
  hematology: ["lab", "dicom-studies", "xray-images"],
  general_surgery: ["lab"],
  general_practice: ["lab", "dicom-studies"],
```

- [ ] Yeni sabit:

```js
// Per-specialty sidebar label overrides (NAV_ITEMS id -> translation key) for
// items a specialty keeps but under a name that fits it -- e.g. the shared
// X-ray gallery reads as generic "Imaging" outside dental/orthopedics.
const NAV_LABEL_KEYS_BY_SPECIALTY = {
  internal_medicine: { "xray-images": "imagingNav" },
  orthopedics: { "dicom-studies": "mriCtImages" },
  pediatrics: { "xray-images": "imagingNav" },
  physiotherapy: { "xray-images": "imagingNav" },
  general_surgery: { "xray-images": "imagingNav", "dicom-studies": "mriCtImages" },
  general_practice: { "xray-images": "imagingNav" },
};
```

- [ ] `localizedNavItems` içinde `t(item.labelKey)` / `t(child.labelKey)` çağrılarını `t(labelKeyFor(entry))` yap; `const labelOverrides = NAV_LABEL_KEYS_BY_SPECIALTY[activeSpecialtyKey] || {}; const labelKeyFor = (entry) => labelOverrides[entry.id] || entry.labelKey;`

### Task 2: Hasta detayı ve hasta listesi

**Files:** `src/specialties/{gynecology,cosmetic,hematology}/pages/*{ClientDetails,Patients}Page.jsx` — Röntgen tamamen kaldırılır: `{ id: "xray", label: t("tabXray") }` satırı, `activePanel === "xray"` bloğu, `XrayImagePicker` import'u; hasta listesinde `openModalForPatient("xray", …)` butonu, `PatientXrayModal` render'ı ve import'u.
**Files:** `src/specialties/{internal_medicine,pediatrics,physiotherapy,general_surgery,general_practice}/pages/*` — sekme etiketi `t("tabXray")` → `t("tabImaging")`, satır işlemi `t("xrayImages")` → `t("imagingNav")`. Orthopedics'te Röntgen adı kalır.

- [ ] Değişiklik sonrası: `grep -n "xray" src/specialties/{gynecology,cosmetic,hematology}/pages/*` → boş.

### Task 3: Çeviriler

**Files:** `../translations/{en,tr,ar}.json` — `imagingNav` (Imaging / Görüntüleme / التصوير الطبي), `tabImaging` (Imaging / Görüntüleme / التصوير), `mriCtImages` (MRI/CT Images / MR/BT Görüntüleri / صور الرنين والطبقي).

### Task 4: Doğrulama

- [ ] `npx eslint` değişen dosyalarda yeni hata yok (baseline: ClientDetails sayfalarındaki 2 miras hata).
- [ ] `npm run build`, `dist` → `Dental_Backend/public/app` (orphan kontrolü ile).
- [ ] Playwright: pediatri hekimiyle sidebar'da Lab/CBCT yok, "Görüntüleme" var; hematology/gynecology hasta detayında Röntgen sekmesi yok.
