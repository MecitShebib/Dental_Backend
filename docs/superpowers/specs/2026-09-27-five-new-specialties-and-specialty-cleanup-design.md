# 5 yeni uzmanlık + 9 uzmanlık sadeleştirme ve özel hasta alanları — Tasarım

Tarih: 2026-09-27. Karar sahibi: kullanıcı (her uzmanlık için tek tek onaylandı).

**Kapsam dışı (asla dokunulmaz):** `dental` (Dentavaria) ve `nutrition` (Dietavaria) — kodu, sayfaları, testleri, landing içeriği.

**"9 uzmanlık"** = gynecology, internal_medicine, orthopedics, cosmetic + 5 yeni (pediatrics, physiotherapy, hematology, general_surgery, general_practice).

**Mimari kararı:** mevcut stil korunur — kopyala-yapıştır. Her yeni uzmanlık Orthovaria'nın dosyalarından kopyalanır (backend 4 controller + route dosyası + Module + CarePlanService; frontend 5 sayfa + theme.js). Ortak/jenerik soyutlama YAPILMAZ.

---

## Aşama 1 — 5 yeni uzmanlık

| key | Marka | TR | EN | AR | Accent | Slug |
|---|---|---|---|---|---|---|
| `pediatrics` | Pediavaria | Çocuk Sağlığı ve Hastalıkları | Pediatrics | طب الأطفال | `#3a86ff` | pediavaria |
| `physiotherapy` | Physiovaria | Fizyoterapi | Physiotherapy | العلاج الفيزيائي | `#5c8a1f` | physiovaria |
| `hematology` | Hemavaria | Hematoloji | Hematology | أمراض الدم | `#b3261e` | hemavaria |
| `general_surgery` | Surgivaria | Genel Cerrahi | General Surgery | الجراحة العامة | `#37474f` | surgivaria |
| `general_practice` | Genervaria | Pratisyen Hekimlik | General Practice | الطب العام | `#8a6d1f` | genervaria |

### Backend (her biri için)
- `Specialty::{CONST}` sabiti; `SpecialtySeeder` satırı (sort_order 7–11, is_active=true). Canlıya ayrı migration gerekmez: `public/migrate.php` her deploy'da `DatabaseSeeder` → `SpecialtySeeder` (idempotent) çalıştırıyor.
- Katalog kodları şirket içinde global benzersiz (`updateOrCreate(['company_id','code'])`) — yeni kodlar `ped_`/`phy_`/`hem_`/`gs_`/`gp_` önekli. Katalog, admin abonelik oluştururken registry üzerinden otomatik yüklenir (`Admin\SubscriptionController`).
- `app/Specialties/{Name}/{Name}Module.php` — `isBuilt()=true`, 4 kalemlik katalog (TL); `SpecialtyModuleRegistry` constructor'ına eklenir.
- `app/Specialties/{Name}/{Plan}CarePlanService.php` — `MilestoneCarePlanService` üzerinden:
  - **Pediavaria – Çocuk İzlem Planı** (anchor = 1. ay izlem vizitinin tarihi; doğum tarihine bağlamak büyük çocuklarda geçmiş tarihli randevu üretirdi): 0 (1. ay izlem), 30 (2. ay aşı), 90 (4. ay aşı), 150 (6. ay aşı), 240 (9. ay izlem), 330 (12. ay aşı), 510 (18. ay aşı), 690 (24. ay büyüme değerlendirmesi).
  - **Physiovaria – Fizyoterapi Seans Planı**: 0 değerlendirme, 2,4,7,9,11,14,16,18,21 seans, 28 son değerlendirme.
  - **Hemavaria – Kan Sayımı Takip Planı**: 0 muayene, 14, 28, 56, 84. gün CBC kontrol, 90 değerlendirme.
  - **Surgivaria – Perioperatif Plan** (anchor = pre-op değerlendirme tarihi): 0 pre-op değerlendirme, 7 ameliyat, 14 pansuman/dikiş alımı, 37 post-op kontrol.
  - **Genervaria – Genel Takip Planı**: 0 muayene, 14 kontrol, 90 kontrol.
- Plan confirm endpoint'i: `RehabCarePlanController` kopyası + FormRequest; `routes/api.php`'de mevcut `rehab-care-plan` satırının yanına.
- `app/Http/Controllers/Api/{Name}/` — Client, Appointment, Dashboard, AiConversation (Orthopedics kopyası, sabit değişir); `routes/api/{key}.php` + `routes/api.php` require.
- AI asistan: `app/Services/SpecialtyAiProfiles.php`'e her yeni key için profil (sistem prompt'u, katalog kodları) eklenir; duplicate key hatası tekrarlanmamalı (bkz. 2026-08-18 Prenatal olayı).
- `LandingPageContent`: `SPECIALTIES`, `SPECIALTY_SLUGS`, `SPECIALTY_ACCENTS`, hub listesi (3 dil), her uzmanlık için `{key}EnDefaults/ArDefaults/TrDefaults` tam sayfa içeriği. Admin landing CMS editörü ve `/{slug}` rotası bu sabitlerden beslendiği için otomatik çalışmalı; doğrulanır.
- `ApiDocumentation` (per-specialty api-docs) yeni gruplar.
- Admin panel: abonelik formu specialties tablosundan okur — doğrulanır; hardcoded listeler varsa güncellenir.
- `public/proposal-{slug}.html` ve `public/pitch-{slug}.html` × 5 (Orthovaria kopyası, içerik uzmanlığa göre); `proposal-doctovaria.html`/`pitch-doctovaria.html`'e 5 yeni ürün eklenir.
- Testler: `tests/Feature/Orthopedics` kopyası her yeni uzmanlık için; `SpecialtyTest`/`SpecialtyLandingPageTest` yeni key'leri kapsar.

### Frontend (Dental_FrontEnd/app/frontend/src)
- `specialties/{key}/pages/*` × 5 sayfa + `theme.js` (accent yukarıdaki tablo).
- `App.jsx` route'ları, `lib/api.js` (`api.{key}` + `confirm{Plan}Plan`), `AppStateApiContext.jsx` (`canAccess{Name}`, AI api map), `Sidebar.jsx` (`SPECIALTY_ACCENTS`, `SPECIALTY_LOGOS`), `LauncherPage.jsx` logo map, en/ar/tr çeviri anahtarları.
- Logo: `public/brand/doctovaria.png` → `pediavaria.png`, `physiovaria.png`, `hemavaria.png`, `surgivaria.png`, `genervaria.png` kopyası (gerçek logo gelince dosya değişimi yeter). Dental_FrontEnd kaynağına eklenir (public/app yenilemesi orphan dosyaları siler).
- Build + `dist/` → `Dental_Backend/public/app/`.

---

## Aşama 2 — 9 uzmanlıkta silme / yeniden adlandırma

Mekanizma: `AppStateApiContext.jsx`'teki `NAV_IDS_HIDDEN_BY_SPECIALTY` (sidebar gizleme) + yeni `NAV_LABEL_OVERRIDES_BY_SPECIALTY` (etiket değişimi); hasta detayındaki sekme ve hasta listesi satır işlemleri ilgili uzmanlığın kendi sayfa dosyasında çıkarılır.

Not: hasta listesindeki "lab" satır işlemi = Tahlil Sonuçları (kalır). "Dental Lab" = sidebar `lab` öğesi (protez laboratuvarı).

**Envanter tüm uzmanlıklarda kalır.**

| Uzmanlık | Gizlenen sidebar | Röntgen (`xray-images` + sekme + satır işlemi) | CBCT (`dicom-studies`) |
|---|---|---|---|
| gynecology | lab, dicom-studies, xray-images | **Silinir → yerine Ultrason sekmesi** (Aşama 3) | silinir |
| internal_medicine | lab, dicom-studies | kalır, etiket "Görüntüleme" | silinir |
| orthopedics | lab | kalır | kalır, etiket "MR/BT Görüntüleri" |
| cosmetic | lab, dicom-studies, xray-images | **Silinir → yerine Öncesi/Sonrası foto** (Aşama 3) | silinir |
| pediatrics | lab, dicom-studies | kalır, "Görüntüleme" | silinir |
| physiotherapy | lab, dicom-studies | kalır, "Görüntüleme" | silinir |
| hematology | lab, dicom-studies, xray-images | silinir | silinir |
| general_surgery | lab | kalır, "Görüntüleme" | kalır, "MR/BT Görüntüleri" |
| general_practice | lab, dicom-studies | kalır, "Görüntüleme" | silinir |

---

## Aşama 3 — Uzmanlığa özel hasta alanları

Desen: Dietavaria'nın `nutrition_client_profiles` + `nutrition_body_metrics` yapısı kopyalanır.
- Hasta başına 1 satır: `{key}_client_profiles` (client_id unique, created_by/updated_by), `GET/PUT /api/{key}/clients/{client}/profile`, `AuthorizesOwnDoctorRecords` ile sahiplik kontrolü.
- Tekrarlayan kayıtlar: ayrı tablo + `apiResource` (index/store/update/destroy), yine sahiplik kontrollü.
- Frontend: Client Edit sayfasında profil formu; Client Details'te yeni sekme(ler). Sağlık verisi olduğu için dosya yüklemeleri `private` disk + mevcut signed/file endpoint deseni (KVKK).

### gynecology — Gynevaria
- Profil: `last_menstrual_period` (date) → tahmini doğum tarihi hesaplanır (Naegele, sadece gösterim), `gravida`, `para`, `abortus` (int), `blood_type` (A/B/AB/0), `rh` (+/-), `cycle_length_days`, `cycle_regularity` (regular/irregular), `contraception_method`, `last_pap_smear_date`, `menopause_status` (pre/peri/post), `previous_delivery_types` (json: normal/cesarean sayıları).
- `gynecology_ultrasound_exams`: exam_date, gestational_week, gestational_day, bpd_mm, hc_mm, ac_mm, fl_mm, efw_grams, fetal_heart_rate, placenta_location, amniotic_fluid (normal/oligo/poly), notes, image (private, opsiyonel). Sekme: "Ultrason".

### internal_medicine — Medivaria
- Profil: `chronic_conditions` (json çoklu seçim: diabetes_t1, diabetes_t2, hypertension, copd, asthma, heart_failure, coronary_artery_disease, hypothyroidism, hyperthyroidism, chronic_kidney_disease, liver_disease, other), `current_medications` (text), `allergies` (text), `blood_type`, `rh`, `smoking` (never/former/current), `alcohol` (none/occasional/regular), `height_cm`, `family_history` (text).
- `internal_medicine_vitals`: measured_at, systolic, diastolic, pulse, temperature_c, spo2, blood_glucose, weight_kg (BKİ height'tan hesaplanır). Sekme: "Vital Bulgular" + basit trend listesi.

### orthopedics — Orthovaria
- Profil: `affected_region` (enum: shoulder, elbow, wrist_hand, hip, knee, ankle_foot, cervical_spine, thoracic_spine, lumbar_spine, other), `side` (right/left/bilateral), `complaint_onset_date`, `injury_mechanism` (text), `previous_surgeries_implants` (text), `cast_splint_status` (none/cast/splint/brace), `occupation_sport` (text), `dominant_hand` (right/left/ambidextrous).
- `orthopedics_assessments`: assessed_at, pain_vas (0-10), rom_measurements (json: [{joint, movement, degrees}]), notes. Sekme: "Değerlendirmeler".

### cosmetic — Estevaria
- Profil: `fitzpatrick_skin_type` (I–VI), `aesthetic_goals` (text), `areas_of_interest` (json), `previous_procedures` (text), `allergies` (text), `keloid_tendency` (bool), `skincare_products` (text), `isotretinoin_use` (none/current/past_6_months), `pregnancy_breastfeeding` (bool).
- `cosmetic_procedure_logs`: performed_at, procedure_type, product_name, lot_number, amount, unit (units/ml/session), treatment_area, notes, before_photo, after_photo (private). Sekme: "İşlem Kayıtları / Öncesi-Sonrası".

### pediatrics — Pediavaria
- Profil (doğum tarihi mevcut `clients.date_of_birth` alanından okunur, tekrar tutulmaz): `gestational_age_weeks_at_birth`, `birth_weight_g`, `birth_length_cm`, `birth_head_circumference_cm`, `guardian_name`, `guardian_phone`, `guardian_relation`, `feeding_type` (breast/formula/mixed/solid), `blood_type`, `rh`, `allergies`, `chronic_conditions` (text), `developmental_milestones` (json: [{milestone, achieved_at_months}]).
- `pediatrics_growth_measurements`: measured_at, weight_kg, height_cm, head_circumference_cm; BKİ ve ölçüm anındaki yaş frontend'de hesaplanır. (Revize 2026-09-27: WHO persentili bu sürümde YOK — resmi WHO LMS tabloları elde değil, ezberden referans veri yazmak klinik olarak güvensiz; resmi CSV'ler eklendiğinde yapılabilir.) Sekme: "Büyüme" (tablo + grafik).
- `pediatrics_vaccinations`: vaccine (TR takvimi enum: hep_b, bcg, dabt_ipa_hib, kpa, opa, kkk, su_cicegi, hep_a, td, other), dose_number, administered_at, lot_number, notes. Sekme: "Aşı Kartı".

### physiotherapy — Physiovaria
- Profil: `diagnosis` (text), `referring_physician` (text), `affected_region` (orthopedics enum ile aynı), `side`, `prescribed_session_count` (int), `home_exercise_program` (text).
- `physiotherapy_sessions`: session_date, session_number, pain_vas, rom_measurements (json), muscle_strength (json: [{muscle, mmt 0-5}]), modalities (json çoklu: tens, therapeutic_ultrasound, hot_pack, cold_pack, laser, shortwave, manual_therapy, exercise, dry_needling, kinesio_taping, traction), notes. Sekme: "Seanslar" (x / prescribed sayacı).

### hematology — Hemavaria
- Profil: `blood_type`, `rh`, `primary_diagnosis` (enum: iron_deficiency_anemia, b12_folate_deficiency, thalassemia, sickle_cell, hemophilia, von_willebrand, itp, leukemia, lymphoma, myeloma, mds, polycythemia, other), `diagnosis_notes`, `anticoagulant` (none/warfarin/doac/heparin/other), `inr_target_min`, `inr_target_max`, `splenectomy` (bool), `chemo_protocol` (text), `chemo_cycles_planned`, `chemo_cycles_completed`, `bleeding_history` (text).
- `hematology_blood_counts`: measured_at, hb, hct, wbc, plt, mcv, ferritin, inr, notes. Sekme: "Kan Sayımı" (tablo + trend; INR hedef dışıysa vurgulu).
- `hematology_transfusions`: transfused_at, product (prbc/ffp/platelet/cryo/whole_blood), units, reaction (bool), reaction_notes. Sekme: "Transfüzyonlar".

### general_surgery — Surgivaria
- Profil: `indication` (text), `planned_operation` (text), `asa_score` (I–VI), `previous_surgeries` (text), `anticoagulant_use` (text), `allergies`, `blood_type`, `rh`, `preop_checklist` (json: cbc, coagulation, biochemistry, ecg, chest_xray, anesthesia_consult, informed_consent, fasting — her biri bool).
- `general_surgery_operations`: operated_at, operation_name, duration_minutes, anesthesia_type (general/spinal/epidural/local/sedation), surgeon_notes, complications (text).
- `general_surgery_followups`: followup_date, operation_id (nullable FK), wound_status (clean/serous/infected/dehiscence), drain_removed_at, sutures_removed_at, complications, notes. Sekme: "Ameliyatlar" (operasyon + altında takipler).

### general_practice — Genervaria
- Profil: `chronic_conditions` (Medivaria listesi), `current_medications`, `allergies`, `blood_type`, `rh`, `smoking`, `alcohol`, `height_cm`, `adult_vaccination_status` (json: tetanus, influenza, pneumococcal, hepatitis_b, covid19 — tarih).
- `general_practice_vitals`: Medivaria vitals ile aynı kolonlar. Sekme: "Vital Bulgular".
- `general_practice_referrals`: referred_at, target_specialty, target_institution, reason, status (pending/completed). Sekme: "Sevkler".

---

## Uygulama sırası ve doğrulama
1. Aşama 1 (uzmanlık başına tek tek, her birinin testi yeşil olunca sıradaki).
2. Aşama 2 (tek seferde, frontend).
3. Aşama 3 (uzmanlık başına: migration → model/request/resource/controller/route → feature test → frontend → build).
- Her aşama sonunda `composer run test` + frontend `npm run build` + `dist/` → `public/app/`.
- Canlı DB'ye migration uygulanması (`migrate.php` akışı) ve push kullanıcı onayı ile yapılır.
