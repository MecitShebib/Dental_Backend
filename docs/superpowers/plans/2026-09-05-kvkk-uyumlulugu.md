# KVKK Uyumluluğu — Uygulama Planı

> **Not:** Bu, klasik bir "test yaz → kod yaz" mühendislik planı değil; hukuki/organizasyonel kararlar ile teknik değişikliklerin karıştığı bir uyumluluk yol haritasıdır. Kod gerektiren her görevde tam dosya yolu ve somut yaklaşım verilmiştir. Hukuki nitelikli görevler (VERBİS, sözleşmeler, DPO ataması) kod içermez — bunlar kullanıcının (veya bir KVKK danışmanı/hukukçunun) kararı ve aksiyonudur, ben sadece hangi karar gerektiğini ve mevcut koddaki karşılığını somutlaştırırım.
>
> **Uygulayıcılar için:** Kod içeren fazlar için superpowers:subagent-driven-development veya superpowers:executing-plans kullanılabilir. Hukuki fazlar için bu belge bir kontrol listesi olarak kullanılmalıdır.

**Amaç:** Dentavaria/Doctovaria platformunu (bu repo + `Dental_FrontEnd`) 6698 sayılı Kişisel Verilerin Korunması Kanunu'na (KVKK) ve ilgili ikincil mevzuata (Aydınlatma Yükümlülüğünün Yerine Getirilmesinde Uyulacak Usul ve Esaslar Hakkında Tebliğ, Veri Sorumluları Sicili Hakkında Yönetmelik, Kişisel Verilerin Silinmesi/Yok Edilmesi/Anonim Hale Getirilmesi Hakkında Yönetmelik, Veri Güvenliği Rehberi) uygun hale getirmek.

**Mimari çerçeve (mevcut koddan doğrulandı):** Platform, çok kiracılı (multi-tenant) bir B2B SaaS'tır. Her klinik (`Company`) kendi hasta verisinin **veri sorumlusu**dur; platform (bu repo'yu işleten taraf) klinikler adına **veri işleyen**dir (bkz. `app/Support/LegalContent.php:29`, zaten bu ayrımı doğru kurmuş). Platform ayrıca kendi web sitesi ziyaretçileri/personeli için kendi adına **veri sorumlusu**dur. Plan bu iki rolü ayrı ayrı ele alır.

## Uygulama Durumu (2026-09-05, aynı gün içinde uygulandı)

**Kod tarafı tamamlandı (563 test yeşil, `./vendor/bin/pint` temiz):**
- Faz 0 (0.1 private disk + imzalı URL, 0.2 session/backup şifreleme notu, 0.3 AuditLog) — tam
- Faz 2 (2.1 hukuki sebep bölümü + Faz 5.3 aktarım mekanizması, 2.2 KVKK Aydınlatma/Açık Rıza şablonları) — tam
- Faz 3 (3.1 veri dışa aktarma, 3.2 anonimleştirme/erasure) — tam
- Faz 4 (OTP purge + lapsed-company anonimleştirme; AI konuşma saklama süresi kolonu kapsam dışı bırakıldı) — kısmi/yeterli
- Faz 5.4 (RequiresKvkkConsent middleware, dental + 4 uzmanlık dalı + X-ray job) — tam
- Faz 6.2 (DetectAnomalousAccess) — tam
- Faz 7.1 (ToS'a veri işleyen maddeleri) — tam
- **Faz 5.2 (SMS sağlayıcısı) — tam ve kapandı:** Infobip (Hırvatistan) tamamen kaldırıldı, yerine İleti Merkezi (Türkiye) geçirildi (`app/Services/IletiMerkeziSmsService.php`, `.env.example`, `config/services.php`). OTP/randevu hatırlatma SMS'leri artık KVKK m.9 (yurt dışı aktarım) değil, m.8 (yurt içi aktarım) kapsamında — `LegalContent.php` ve veri envanteri buna göre güncellendi. Kalan tek adım: İleti Merkezi ile yazılı bir veri işleyen sözleşmesi imzalamak (m.8 gereği, m.9'a göre çok daha hafif bir yük).
- Belgeler: `docs/kvkk-veri-envanteri.md`, `docs/kvkk-saklama-ve-imha-politikasi.md`, `docs/kvkk-veri-ihlali-mudahale-prosedueru.md`, `docs/kvkk-egitim-checklist.md`

**Yapılamadı — kullanıcının/bir hukukçunun aksiyonu gerekiyor:**
- Faz 1.2-1.4 (VERBİS kaydı kararı, irtibat kişisi ataması)
- Faz 5.1 (OpenAI sözleşmesinin gerçek hukuki teyidi — hâlâ en yüksek risk, ABD'ye özel nitelikli sağlık verisi aktarımı)
- İleti Merkezi ile veri işleyen sözleşmesinin fiilen imzalanması (yukarıya bakınız)
- Faz 6 iletişim listesi, Faz 7.2 (sözleşme incelemesi), Faz 8 (fiili eğitim)
- **Veritabanı migration'ları prod'a UYGULANMADI** — bu makinenin `.env`'i production MySQL'e bağlı olduğu için `php artisan migrate` bilerek çalıştırılmadı; bkz. konuşmanın sonundaki dağıtım talimatı.

---

**Tespit edilen somut, öncelikli riskler (mevcut kod taramasından):**
1. Röntgen görüntüleri, rıza imzaları ve masraf ekleri `public` diskte saklanıyor ve kimlik doğrulamasız, tahmin edilemez ama herkese açık URL'lerle erişilebiliyor (`app/Http/Resources/XrayImageResource.php:18`, `app/Jobs/AnalyzeXrayImageJob.php:46`). Bu, özel nitelikli sağlık verisinin yetkisiz erişime açık olması demektir (KVKK m.12 ihlali riski).
2. Röntgen görüntüleri, AI analizi için bu genel-erişimli URL üzerinden doğrudan OpenAI'a (ABD) gönderiliyor (`app/Services/AiTreatmentPlanService.php:435-443`) — açık rıza veya KVKK m.9 uyumlu bir aktarım mekanizması olmadan yurt dışına özel nitelikli veri aktarımı riski.
3. Hasta "açık rıza" / KVKK aydınlatma metni akışı yok — mevcut `ConsentTemplate`/`ClientConsent` sistemi (app/Models/ClientConsent.php) klinik tedavi rızası (signature pad) için, KVKK'nın öngördüğü ayrı "Aydınlatma Metni" + "Açık Rıza Beyanı" formatında değil.
4. Veri sahibi başvuru hakları (erişim/düzeltme/silme/itiraz — KVKK m.11) için ne API endpoint'i ne admin panelinde bir akış var.
5. `Client` modeli `SoftDeletes` kullanıyor (app/Models/Client.php:19) — bu "silme" değil, kayıt gizleme; KVKK'nın istediği silme/yok etme/anonim hale getirme değil.
6. Saklama süresi/imha politikası ve bunu uygulayan otomatik bir iş (job) yok — OTP kodları, arama kayıtları, AI konuşma kayıtları vb. süresiz saklanıyor.
7. Erişim/işlem denetim kaydı (audit log) — kim, hangi hasta kaydına ne zaman eriştiyi/değiştirdi — hiç yok.
8. `.env.example`'da `SESSION_ENCRYPT=false`, yedekleme şifresi (`BACKUP_ARCHIVE_PASSWORD`) varsayılan boş, `AWS_DEFAULT_REGION=us-east-1` varsayılan (bulut yedek açılırsa veri ABD'ye gider).

---

## FAZ 0 — Öncelikli Teknik Güvenlik Düzeltmeleri (kod, hemen yapılabilir)

Bu faz, KVKK'nın "veri güvenliğine ilişkin yükümlülükler" (m.12) kapsamına giren ve bugün canlıda mevcut, somut açıkları kapatır. Hukuki karar beklemeden yapılabilir.

### Görev 0.1 — Röntgen/rıza-imzası/ek dosyalarını herkese açık diskten özel diske taşı

**Dosyalar:**
- Değiştir: `config/filesystems.php` (yeni `private` disk tanımı, mevcut `public` disk'e ek olarak)
- Değiştir: `app/Http/Resources/XrayImageResource.php:18`
- Değiştir: `app/Http/Resources/ClientConsentResource.php:19`
- Değiştir: `app/Http/Resources/ExpenseResource.php:32`
- Değiştir: `app/Jobs/AnalyzeXrayImageJob.php:46`
- Yeni: `app/Http/Controllers/Api/SecureFileController.php` (veya benzeri) — yetkilendirme kontrolü yapıp `Storage::disk('private')->response($path)` dönen tek endpoint
- Yeni route: `routes/api.php` içine `GET /files/{type}/{path}` (auth:sanctum + company-scope middleware ile korunan)

**Yaklaşım:**
- Röntgen, rıza imzası ve masraf eki gibi kişisel/özel nitelikli veri içeren dosyalar `storage/app/private` altına taşınır (yeni yüklenenler için; mevcutlar bir migration/artisan komutuyla taşınır).
- `image_url`/`signature_url`/`attachment_url` alanları artık doğrudan `Storage::disk('public')->url()` değil, süreli imzalı URL (`Storage::disk('private')->temporaryUrl($path, now()->addMinutes(10))`, S3 kullanılıyorsa) veya kimlik-doğrulamalı proxy endpoint döner.
- `AnalyzeXrayImageJob` OpenAI'a gönderirken de bu geçici/imzalı URL'yi kullanır (public disk yerine).

**Kabul kriteri:** Kimlik doğrulaması olmadan (misafir/`curl`) hiçbir röntgen/imza/ek dosyasının URL'sine erişilemiyor; mevcut Feature testler (`tests/Feature/XrayImage*`, `tests/Feature/*Consent*`) hâlâ geçiyor.

### Görev 0.2 — Oturum ve yedek şifrelemesini üretim varsayılanı yap

**Dosyalar:**
- Değiştir: `.env.example` → `SESSION_ENCRYPT=true`
- Değiştir: prod `.env` (kullanıcı tarafından, WinSCP ile) → `SESSION_ENCRYPT=true`, `BACKUP_ARCHIVE_PASSWORD=<güçlü parola>`
- Not düş: `AWS_DEFAULT_REGION` — eğer S3 yedeği açılacaksa `eu-central-1` gibi bir AB bölgesi kullanılmalı, `us-east-1` değil (yurt dışı aktarım kapsamını genişletmemek için).

**Kabul kriteri:** Prod ortamda `php artisan tinker` ile `config('session.encrypt')` → `true`.

### Görev 0.3 — Erişim/işlem denetim kaydı (audit log) altyapısı

**Dosyalar:**
- Yeni migration: `database/migrations/2026_09_05_000000_create_audit_logs_table.php`
  - Kolonlar: `id`, `uuid`, `company_id`, `user_id` (nullable), `action` (`viewed`/`created`/`updated`/`deleted`/`exported`), `auditable_type`, `auditable_id`, `ip_address`, `user_agent`, `meta` (json, nullable), `created_at`
- Yeni model: `app/Models/AuditLog.php`
- Yeni trait: `app/Models/Concerns/Auditable.php` — model `saved`/`deleted` event'lerinde `AuditLog::create(...)` yazan basit bir trait (created_by zaten çoğu modelde var, buraya sadece "hangi alan değişti" + "kim" eklenir)
- Uygula: `Client`, `ClientConsent`, `XrayImage`, `TreatmentRecord`, `PatientLabResult` modellerine `use Auditable;` ekle
- Yeni: hassas okuma (görüntüleme) olayları için — `app/Http/Controllers/Api/ClientController.php::show()` içine `AuditLog::create([...'action' => 'viewed'...])` çağrısı

**Kabul kriteri:** Bir `Client` kaydı güncellendiğinde/silindiğinde/görüntülendiğinde `audit_logs` tablosunda satır oluşuyor; yeni bir Feature test (`tests/Feature/AuditLogTest.php`) bunu doğruluyor.

---

## FAZ 1 — Veri Envanteri ve VERBİS (hukuki, kod yok)

- [ ] **1.1** Kişisel Veri İşleme Envanteri çıkar: bu repodaki her tablo/model için (Client, User, ClientConsent, XrayImage, PatientLabResult, CallLog, SatisfactionSurvey, AiConversation, LandingPageInquiry, UserOtp...) hangi veri kategorisi (kimlik/iletişim/sağlık/finansal/biyometrik-benzeri imza), hangi işleme amacı, hangi hukuki sebep (KVKK m.5/6), kimlere aktarıldığı (OpenAI, İleti Merkezi, barındırma sağlayıcısı), ne kadar saklandığı bir tabloya dökülür. `app/Models/*.php` listesi (Görev başında taranan 49 model) bu envanterin başlangıç noktasıdır.
- [ ] **1.2** VERBİS'e kayıt gerekip gerekmediğini bir KVKK danışmanına/hukukçuya teyit ettir. Sağlık verisi işleyen veri sorumluları/işleyenler için Kurul'un yayımladığı istisna listesine göre çalışan sayısı/ciro eşiğinden bağımsız olarak kayıt zorunluluğu olabilir — bu, ben kod tarafında karar veremeyeceğim tek yasal belirsizlik, mutlaka teyit edilmeli.
- [ ] **1.3** Platform hem "her klinik için veri işleyen" hem "kendi personeli/site ziyaretçisi için veri sorumlusu" sıfatını taşıdığından, gerekiyorsa iki ayrı VERBİS kaydı/bildirimi değerlendirilir.
- [ ] **1.4** Bir Veri Sorumlusu/Kişisel Verileri Koruma İrtibat Kişisi belirle (DPO zorunlu değil ama KVKK m.10 gereği irtibat kişisi pratikte gerekli); bu kişinin adı `app/Support/LegalContent.php` içindeki "14. Contact us" bölümüne ve VERBİS kaydına işlenir.

---

## FAZ 2 — Aydınlatma Metni ve Açık Rıza (hukuki metin + kod)

### Görev 2.1 — Mevcut Privacy Policy'yi KVKK m.10 formatına uyarlama (hukuki + kod)

`app/Support/LegalContent.php` iyi bir taslak ama ABD-tipi bir "Privacy Policy" formatında; KVKK'nın Tebliğ'i, aydınlatma metninde şu başlıkların **ayrı ayrı ve açıkça** yer almasını ister: (a) veri sorumlusunun kimliği, (b) işlenen kişisel veri kategorileri, (c) işlenme amacı, (d) hukuki sebebi (m.5/6 hangi bendi), (e) kimlere ve hangi amaçla aktarıldığı, (f) veri sahibinin m.11'deki hakları. Mevcut metin bunların çoğunu içeriyor ama "hukuki sebep" (m.5/6 bendi) hiç belirtilmemiş — eklenmeli.

- Değiştir: `app/Support/LegalContent.php` → `privacy()` fonksiyonuna her bölüme "hukuki sebep" cümlesi eklenir (örn. "2. Information we collect" bölümünün her maddesine karşılık gelen KVKK m.5/6 bendi).
- Ayrı bir bölüm eklenir: "Yurt dışına aktarım" (mevcut "10. International transfers" var ama m.9 kapsamında hangi mekanizmaya dayanıldığı — açık rıza mı, SCC mi — belirtilmeli; bu FAZ 6 ile bağlantılı).

### Görev 2.2 — Hasta KVKK Aydınlatma Metni + Açık Rıza şablonunu `ConsentTemplate` sistemine ekle

Mevcut `ConsentTemplate`/`ClientConsent`/`ConsentService` (app/Services/ConsentService.php) altyapısı zaten imzalı, tarihli, IP kayıtlı rıza saklıyor — bunu tekrar icat etmeye gerek yok, sadece klinik-tedavi rızasının yanına bir **KVKK-özel** şablon türü eklenir.

**Dosyalar:**
- Değiştir: `database/migrations/2026_08_11_001000_create_consent_templates_table.php`'nin üzerine yeni migration: `add_kind_to_consent_templates_table` → `kind` enum kolonu (`clinical`, `kvkk_disclosure`, `kvkk_explicit_consent`)
- Değiştir: `app/Models/ConsentTemplate.php` → `kind` cast/fillable
- Yeni seeder: `database/seeders/KvkkConsentTemplateSeeder.php` — her yeni `Company` için otomatik olarak "Kişisel Verilerin İşlenmesine İlişkin Aydınlatma Metni" ve "Açık Rıza Beyanı" şablonlarını (yer tutucu `{client_name}`/`{company_name}` ile, `TreatmentCatalogSeeder::seedCompany()`'nin yaptığı gibi) oluşturur; metin içeriği hukuk danışmanınca onaylanmış bir taslaktan gelmeli (bu adım için gerçek metni ben üretmem — kliniğin avukatı onaylamalı).
- Değiştir: `app/Http/Controllers/Admin/CompanyController.php::store()` — yeni klinik oluşturulurken `KvkkConsentTemplateSeeder::seedCompany($company)` çağrısı (mevcut `TreatmentCatalogSeeder::seedCompany()` çağrısının hemen yanına).
- Frontend (`Dental_FrontEnd`, bu repo değil ama not düşülmeli): hasta kaydı oluşturulurken/ilk ziyarette bu iki rızanın imzalatılması zorunlu kılınmalı — mevcut `ChargeItemsEditor`/consent akışının yanına bir adım eklenir.

**Kabul kriteri:** Yeni bir `Company` oluşturulduğunda `consent_templates` tablosunda `kind=kvkk_disclosure` ve `kind=kvkk_explicit_consent` satırları otomatik oluşuyor (mevcut `tests/Feature/ConsentTest.php`'ye yeni bir test eklenir).

---

## FAZ 3 — Veri Sahibi Başvuru Hakları (KVKK m.11) — kod

KVKK m.11 kapsamında hasta/kullanıcı; verisinin işlenip işlenmediğini öğrenme, amacını öğrenme, aktarıldığı üçüncü kişileri bilme, düzeltme, **silme/yok edilme**, ve otomatik sistemle analiz sonucu aleyhine bir sonuç çıkmasına itiraz talep edebilir. Bugün bu taleplerin işlenmesi tamamen manuel (e-posta ile).

### Görev 3.1 — Hasta veri dışa aktarma (portability/access) endpoint'i

**Dosyalar:**
- Yeni: `app/Services/ClientDataExportService.php` — `export(Client $client): array` metodu; `Client` + ilişkili `visits`, `appointments`, `payments`, `treatmentCharges`, `consents`, `xrayImages`, `labResults` verisini tek bir yapılandırılmış (JSON/PDF) çıktıya toplar. `ClientFinancialSummaryService`'nin yaptığı gibi ilgili servisleri enjekte ederek toplar.
- Yeni: `app/Http/Controllers/Api/ClientDataRequestController.php` → `export(Client $client)` — sadece kliniğin kendi doktoru/yetkilisi tetikleyebilir (mevcut `AuthorizesOwnDoctorRecords` trait'i, bkz. proje hafızası "Doctor scoping + IDOR fix", burada da uygulanmalı).
- Yeni route: `routes/api.php` → `GET /clients/{client}/data-export`

### Görev 3.2 — Silme/yok etme (erasure) akışı — gerçek anonimleştirme, sadece soft-delete değil

**Dosyalar:**
- Yeni: `app/Services/ClientErasureService.php` → `anonymize(Client $client): void` metodu:
  - `name`, `email`, `phone`, `address`, `date_of_birth`, `medical_notes` alanlarını geri döndürülemez şekilde maskeler (örn. `'Silinmiş Hasta #'.$client->id`, `null`),
  - ilişkili `XrayImage`/`ClientConsent` imza dosyalarını `Storage::disk('private')->delete()` ile fiziksel olarak siler,
  - finansal kayıtları (`Payment`, `TreatmentCharge`, `Invoice`) muhasebe mevzuatı gereği (VUK — 5 yıl) **silmez**, sadece hasta kimliğiyle ilişkisini keser/anonimleştirir — bu istisna KVKK m.7 ile VUK'un çatıştığı, "başka bir kanunda saklama süresi öngörülmüşse KVKK'nın silme talebi o süre sonuna ertelenir" kuralına dayanır; bu mantık kod yorumunda açıkça belirtilmeli.
  - `$client->forceDelete()` **çağırmaz** (finansal iz kalmalı); onun yerine bir `anonymized_at` timestamp kolonu (`database/migrations/..._add_anonymized_at_to_clients_table.php`) set edilir ve `Client::query()` global scope'u anonimleştirilmiş kayıtları normal listelerden gizler.
- Yeni: `app/Http/Controllers/Api/ClientDataRequestController.php` → `destroy(Client $client)` — silme talebini işler, `AuditLog`'a `action=erasure_requested` yazar.
- Yeni route: `routes/api.php` → `DELETE /clients/{client}/personal-data`

**Kabul kriteri:** `ClientErasureServiceTest` — anonimleştirme sonrası `Client::find($id)->name` artık gerçek isim değil, ama `TreatmentCharge`/`Payment` toplamları değişmiyor (muhasebe bütünlüğü korunuyor).

---

## FAZ 4 — Saklama Süresi ve Otomatik İmha Politikası (kod)

KVKK'nın Silme/Yok Etme Yönetmeliği, işleme amacı ortadan kalkan verinin **periyodik imha** ile (genelde 6 ayda bir) silinmesini/anonimleştirilmesini şart koşar. Bugün hiçbir tabloda otomatik süre bazlı temizlik yok.

**Dosyalar:**
- Yeni: `app/Console/Commands/PurgeExpiredPersonalData.php` — `php artisan kvkk:purge` komutu:
  - `user_otps` / `public_booking_otps` tablolarında `created_at < now()->subDays(1)` olan satırları siler (amaç zaten OTP doğrulamasıyla bitmiş, süresiz saklanmasının hiçbir amacı yok).
  - `ai_conversation_messages`/`ai_usage_logs` için Company bazlı bir saklama süresi (`Company` modeline `data_retention_days` nullable kolon, varsayılan örn. 730 gün) sonunda eski kayıtları anonimleştirir.
  - Aboneliği (subscription) `n` gün önce sona ermiş ve o zamandan beri hiç aktif aboneliği olmayan `Company`'lerin hasta verisini (Görev 3.2'deki `ClientErasureService::anonymize()` toplu şekilde çağrılarak) otomatik anonimleştirir — mevcut Privacy Policy'nin "9. Data retention" maddesindeki taahhüdü ("Clinics may request deletion... subject to these obligations") kod tarafında karşılanmış olur.
- Değiştir: `routes/console.php` → `Schedule::command('kvkk:purge')->daily();` (mevcut proje hafızasına göre — **prod'da hiçbir `schedule:run` cron'u çalışmıyor**, bu yüzden bu görev tek başına yeterli değil; kullanıcı cPanel'e `schedule:run` cron'u eklemeden bu iş asla tetiklenmeyecek — bkz. proje hafızası "Shared hosting deploy gotchas").
- Yeni: `docs/kvkk-saklama-ve-imha-politikasi.md` — yukarıdaki her tablo/kural için insan-okur bir politika belgesi (VERBİS kaydında ve olası bir Kurul denetiminde istenecek resmi doküman).

**Kabul kriteri:** `PurgeExpiredPersonalDataTest` — 2 günlük eski bir `UserOtp` kaydı komuttan sonra silinmiş oluyor; aktif aboneliği olan bir `Company`'nin hasta verisi dokunulmadan kalıyor.

---

## FAZ 5 — Üçüncü Taraf ve Yurt Dışı Aktarım (hukuki + kod)

- [ ] **5.1 (hukuki)** OpenAI ile mevcut Data Processing Addendum'un (DPA) KVKK m.9 gereksinimlerini karşılayıp karşılamadığı teyit edilir; karşılamıyorsa OpenAI'ın sunduğu Standard Contractual Clauses (SCC) eklentisi imzalanır **veya** hasta açık rızası (Görev 2.2'deki `kvkk_explicit_consent` şablonu) yurt dışı aktarımı da kapsayacak şekilde genişletilir. Bu, tek başına platformun en yüksek hukuki riskidir (özel nitelikli sağlık verisi + ABD'ye aktarım).
- [x] **5.2 (kod+hukuki, 2026-09-05'te kapandı)** SMS sağlayıcısı Infobip'ten (Hırvatistan) İleti Merkezi'ye (Türkiye) taşındı — artık KVKK m.9 değil, çok daha basit olan m.8 (yurt içi) rejimine tabi. Kalan tek adım: İleti Merkezi ile yazılı bir veri işleyen sözleşmesi imzalamak. Barındırma sağlayıcısı için hâlâ aynı teyit yapılmalı.
- [ ] **5.3 (kod)** `app/Support/LegalContent.php` → "10. International transfers" bölümüne hangi mekanizmaya (SCC/açık rıza) dayanıldığı somut olarak yazılır (hukuki karar netleşince).
- [ ] **5.4 (kod)** AI özelliklerini kullanmadan önce (bugün zaten var olan) klinik onayının yanına, "bu özelliği kullanarak hasta verisinin OpenAI'a (ABD) aktarılmasını kabul ediyorum" onayı `AiTreatmentPlanController`/`SpecialtyAiTreatmentPlanService` akışına eklenir — Görev 2.2'deki açık rıza şablonunun imzalanmış olması bir ön koşul (middleware) haline getirilir: `app/Http/Middleware/RequiresKvkkConsent.php` yeni middleware, AI plan/röntgen analiz route'larına uygulanır.

---

## FAZ 6 — Veri İhlali Müdahale Planı (hukuki + hafif kod)

KVKK m.12/5 gereği bir ihlal olduğunda Kurul'a 72 saat, etkilenen kişilere gecikmeksizin bildirim zorunlu.

- [ ] **6.1 (hukuki)** Yazılı bir "Veri İhlali Müdahale Prosedürü" hazırlanır: kim bilgilendirilir, hangi sırayla, KVKK Kurulu bildirim formu nerede.
- [ ] **6.2 (kod)** `app/Services/AuditLog` altyapısı (Görev 0.3) üzerinden anormal erişim tespiti için basit bir uyarı: bir kullanıcının 1 saat içinde N'den fazla farklı `Client` kaydı görüntülemesi durumunda `app/Console/Commands/DetectAnomalousAccess.php` komutuyla admin'e bildirim (mevcut `Notification` altyapısı, `BackupHasFailedNotification` benzeri).

---

## FAZ 7 — Sözleşmeler (hukuki, kod yok)

- [x] **7.1 (kod, tamam)** Mevcut `resources/views/legal.blade.php` / Terms of Service'e (`app/Support/LegalContent.php` → `terms()`) her klinikle kurulan ilişkiyi "Veri İşleyen Sözleşmesi" (KVKK m.9'un aradığı yazılı sözleşme) niteliğine kavuşturacak ek maddeler eklendi: platformun sadece talimat doğrultusunda işleme yapacağı, alt işleyen (OpenAI/İleti Merkezi) kullanımının klinik onayına tabi olduğu, ihlal bildirim süresi, sözleşme sonunda veri iadesi/imhası taahhüdü.
- [ ] **7.2** OpenAI ve İleti Merkezi ile mevcut sözleşme/DPA'ların gözden geçirilmesi (bkz. Görev 5.1/5.2).

---

## FAZ 8 — Eğitim ve Sürekli Uyum (hukuki, kod yok)

- [ ] **8.1** Klinik personeli ve platform ekibi için KVKK farkındalık eğitimi (özellikle özel nitelikli sağlık verisi işleyen doktorlar/asistanlar).
- [ ] **8.2** Yılda bir bu planın ve veri envanterinin (Görev 1.1) gözden geçirilmesi takvime bağlanır.

---

## Öz-değerlendirme (self-review)

- **Kapsam kontrolü:** Envanter (Faz 1), Aydınlatma/Rıza (Faz 2), Veri sahibi hakları (Faz 3), Saklama/imha (Faz 4), Yurt dışı aktarım (Faz 5), İhlal müdahalesi (Faz 6), Sözleşmeler (Faz 7), Eğitim (Faz 8) — KVKK'nın temel yükümlülük başlıklarının tamamı bir faza karşılık geliyor.
- **Yer tutucu taraması:** Kod içeren her göreve gerçek dosya yolu ve somut yaklaşım verildi; yalnızca hukuki metin içeriği (örn. gerçek Aydınlatma Metni/Açık Rıza cümleleri) bilinçli olarak boş bırakıldı çünkü bu metinlerin bir hukukçu tarafından onaylanması gerekiyor — bu bir eksiklik değil, KVKK'nın kendisinin gerektirdiği bir insan onayı adımı.
- **Öncelik sırası:** Faz 0 (bugün var olan somut güvenlik açıkları) ve Faz 5.1 (OpenAI'a özel nitelikli veri aktarımının hukuki dayanağı) en yüksek risk/en düşük efor oranına sahip, ilk ele alınmalı.
