# Aşama 3 — 9 Uzmanlığa Özel Hasta Alanları Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: superpowers:executing-plans. Steps use checkbox (`- [ ]`) syntax.

**Goal:** Spec § Aşama 3'teki profil alanlarını ve tekrarlayan klinik kayıtları (vital bulgular, büyüme ölçümleri, aşılar, kan sayımı, transfüzyon, ultrason, estetik işlem kaydı, ameliyat/takip, sevk, seans, değerlendirme) 9 uzmanlığa eklemek.

**Architecture:**
- **Backend — uzmanlık başına somut dosyalar (kopyala-yapıştır mimarisi korunur).** Tüm alan tanımları tek bir şema dosyasında (`scratchpad/phase3_schema.py`) tutulur; üretici (`scratchpad/gen_phase3.py`) her uzmanlık için ayrı migration, model, FormRequest, Resource, controller, route satırları ve feature test yazar. Desen birebir Dietavaria'nın `nutrition_client_profiles` / `nutrition_body_metrics` yapısıdır: `firstOrCreate` profil (GET/PUT `/{key}/clients/{client}/profile`), kayıtlar için `GET/POST /{key}/clients/{client}/{slug}` + `PUT/DELETE /{key}/{slug}/{record:uuid}`, her uç `AuthorizesOwnDoctorRecords::assertActingDoctorOwnsClient` ile korunur, modeller `Auditable + BelongsToCompanyViaClient + HasUuid`. Dosyalar (ultrason görüntüsü, öncesi/sonrası foto) `local` (private) diske yazılır ve `signed` route grubundan (`routes/api.php`) 60 dk'lık imzalı URL ile servis edilir (XrayImage/NutritionBodyMetric ile aynı).
- **KVKK:** `ClientErasureService::anonymize()` yeni profil/kayıt satırlarını ve dosyalarını da siler (FK'ler `cascadeOnDelete`, ama Client soft-delete edildiği için açıkça silinir).
- **Frontend — mevcut paylaşılan panel emsaline uyulur** (`ClientLabResultsPanel`, `ClientPrescriptionsPanel` gibi): `components/SpecialtyProfilePanel.jsx` (şemadan okuma + düzenleme formu), `components/SpecialtyRecordsPanel.jsx` (liste + ekle/düzenle/sil modalı + dosya + opsiyonel trend grafiği + satır vurgusu + özet satırı), `components/MetricTrendChart.jsx` (inline SVG). Her uzmanlığın kendi `specialties/{key}/clinicalSchema.js` dosyası alanları ve 3 dilli etiketleri taşır (etiketler şemada `{en,tr,ar}` olarak durur — 9 uzmanlık × ~40 alanı ortak çeviri JSON'una yığmamak için). `api.js`'e `buildSpecialtyClinicalApi(prefix)` eklenir ve 9 namespace'e `clinical` olarak bağlanır. Her ClientDetails sayfasında profil paneli "Hasta Bilgileri" sekmesinin altına, kayıt tipleri yeni sekmeler olarak eklenir.

**Kapsam kararı (spec'ten sapma):** Pediatride WHO persentil hesabı yapılmaz. Resmi WHO LMS tabloları çevrimdışı elde değil ve referans veriyi ezberden yazmak klinik olarak güvensiz. Büyüme sekmesi ham ölçümleri, ölçüm anındaki yaşı ve BKİ'yi tablo + trend grafiği olarak gösterir. Resmi WHO CSV'leri eklendiğinde persentil sonradan eklenebilir.

---

### Task 1: Şema + üretici
- [ ] `phase3_schema.py`: spec § Aşama 3'teki her alan (tip, 3 dilli etiket, seçenekler, sınırlar) ve her kayıt tipi (tablo, slug, tarih alanı, dosya alanları, grafik alanları).
- [ ] `gen_phase3.py`: şemadan backend dosyalarını + frontend `clinicalSchema.js` dosyalarını üretir; var olan dosyanın üstüne yazmaz.

### Task 2: Backend üretimi + bağlama
- [ ] Üreticiyi çalıştır; `Client` modeline ilişkiler, route dosyalarına satırlar, `routes/api.php` signed gruba dosya route'ları, `ClientErasureService`'e silme bloğu eklenir.
- [ ] `php artisan test tests/Feature/{Pascal}/ClinicalRecordsTest.php` (her uzmanlık): profil boş başlar / güncellenir / geçersiz seçenek 422; her kayıt tipi için ekle-listele-güncelle-sil; aynı uzmanlıktan başka hekim 422; başka şirket 404; dosya yükleme → imzalı URL çalışır.
- [ ] Erasure testi: anonimleştirme yeni kayıtları siler.

### Task 3: Frontend
- [ ] Paylaşılan 3 bileşen + `buildSpecialtyClinicalApi`.
- [ ] 9 ClientDetails sayfasına profil paneli + kayıt sekmeleri (üretici ekler, `activePanel` blokları dahil).
- [ ] Uzmanlığa özel hesaplamalar şemada fonksiyon olarak: Gyn tahmini doğum tarihi (SAT + 280 gün), vital/büyüme BKİ, pediatri ölçüm yaşı, hematoloji INR hedef dışı vurgusu, fizyoterapi "x / reçete edilen seans" özeti.
- [ ] Lint (yeni hata türü yok), build, `public/app`'e kopya.

### Task 4: Doğrulama
- [ ] Tam test suite, `pint --dirty`.
- [ ] Playwright (geçici SQLite): her uzmanlık için bir hekim, profil kaydet + her kayıt tipinden bir kayıt ekle; ekran görüntüleri; konsol/API hatası yok.
- [ ] Memory güncelle, kullanıcıya rapor.
