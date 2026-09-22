# Doctovaria — Kapsamlı Sistem Denetimi ve Uçtan Uca Simülasyon Raporu

**Tarih:** 2026-09-22
**Kapsam:** Canlı ortam (`https://doctovaria.com.tr`), izole bir test şirketi üzerinden Dentavaria (diş) ve
Dietavaria (beslenme) uzmanlıklarının tam hasta akışı simülasyonu (hasta kaydı → randevu → check-in/visit →
odontogram/prosedürler → ödeme/fatura → X-ray/rapor fotoğrafı yükleme → AI sohbet + tedavi planı üretimi →
plan onayı ile gerçek randevu oluşturma) + üç paralel arka-plan ajanının kod seviyesinde muhasebe, randevu,
WhatsApp, KVKK, güvenlik ve genel kod taraması.

**Test ortamı:** Gerçek canlıda, ama tamamen izole bir şirket üzerinden çalışıldı — `"ZZZ QA SIMULASYON -
SILINECEK"` (code: `QA-TEST-DELETE-ME`, id=2), kendi Dentavaria+Dietavaria aboneliği ve kendi test
kullanıcıları/hastaları ile. Gerçek hiçbir klinik/hasta verisine dokunulmadı. Bu şirket ve altındaki tüm test
verileri incelemeniz bittikten sonra admin panelinden silinebilir (bkz. rapor sonundaki "Temizlik" bölümü).

---

## 0. Durum Güncellemesi (2026-09-23) — aşağıdaki bulguların tamamı koda uygulandı

Kullanıcının talebi üzerine, aşağıda listelenen TÜM bulgular (1.2 hariç — o zaten çözülmüştü; `setup.php`
kullanıcı tarafından ayrıca ele alınacak) koda uygulandı ve tüm test paketi (735 test, 3371 assertion)
yeşil. Özet:

- **1.2 Kritik IDOR** (ClientAppointmentController) → düzeltildi, `AuthorizesOwnDoctorRecords` eklendi.
- **2.1 Muhasebe/Faturalama** (3 IDOR + Expense→Cari çoklu para birimi hatası) → tümü düzeltildi.
- **2.2 AI plan çok-seans mükerrer faturalama** (bu denetimin en önemli bulgusu) → düzeltildi
  (`Dental_FrontEnd/app/frontend/src/utils/aiTreatmentPlan.js`'de plan genelinde tekilleştirme).
- **2.3 Randevular** (doktor-sahiplik atlaması, şirketler-arası client_id, iptalde ücret kalıntısı) →
  tümü düzeltildi (iptal-ücret temizliği artık `Appointment` model event'i ile TÜM 6 uzmanlık için
  merkezi).
- **2.4 Uzmanlık bağlamı** (hasta oluştururken uzmanlık kaydı kaybı + randevu ekranının yanlış uzmanlığı
  göstermesi) → tek bir kök-sebep düzeltmesiyle çözüldü: `activeSpecialtyKey` artık görüntülenen URL'den de
  senkronize ediliyor (`AppStateApiContext.jsx`). Bu, Bölüm 4'teki "uzmanlığa duyarsız kenar menüsü"
  bulgusunu da otomatik olarak çözdü.
- **2.5 Maaş ödemesi yarış durumu + avans mahsup hatası** → tümü düzeltildi (unique kısıt + lockForUpdate()
  + eskiden-yeniye kısmi avans mahsubu, `ReportController::payrollSummary()`'deki ilgili hesap hatası da
  düzeltildi).
- **3.1 BranchController aşırı-geniş yetki + 3.5 diğer 4 ayar controller'ı** → yeni `AuthorizesCompanySettings`
  ile düzeltildi.
- **3.2 KVKK lab-sonucu onay atlaması** → `kvkk.consent` middleware'i eklendi, onam metni güncellendi.
- **3.3 LabPaymentController IDOR** → düzeltildi.
- **3.4 Hatırlatma mesajı kaybı + WhatsApp/SMS hata yakalama** → düzeltildi (ayrı `reminder_claimed_at`
  alanı, `reminder_sent_at` artık sadece gerçek gönderim başarılı olunca yazılıyor).
- **3.6 No-show slot engeli** → düzeltildi.
- **Bölüm 4 (Cari debit/credit, Payment.visit_id, dil kayması, sayfalama)** → Cari debit/credit ve
  Payment.visit_id düzeltildi; AI dil kayması (Bölüm 2'deki Finding 4) konuşmanın diline göre artık tek
  seferde sabitleniyor ve her mesajda modele açıkça belirtiliyor; sayfalama "1/0" kozmetik hatası zaman
  kısıtı nedeniyle bu turda ele alınmadı (düşük öncelikli, kozmetik).
- **Yeni özellik (bu denetimin dışında, ayrıca istendi):** "Generate PDF" artık her uzmanlıkta hastanın
  kendi `preferred_language` alanına göre (RTL/LTR dahil) üretiliyor, görüntüleyen personelin dilinden
  bağımsız.

Geriye kalan tek gerçek kod değişikliği gerektirmeyen madde: canlı `.env`'deki
`KVKK_AI_CONSENT_REQUIRED` bayrağının gerçek değeri (kullanıcının 2026-09-12 tarihli bilinçli geçici
kararı) — bu denetimin kapsamı dışında, kullanıcı tarafından yönetiliyor.

Değişikliklerin canlıya yansıması için: (a) backend tarafı normal deploy süreciyle (migration'lar dahil,
4 yeni migration var) senkronize edilmeli, (b) frontend zaten build edilip `public/app`'e kopyalandı, aynı
şekilde senkronize edilmeli.

---

## 1. Kritik Bulgular

### 1.1 `public/setup.php` — kimlik doğrulamasız, tam veritabanı silme riski (KULLANICI KENDİSİ HALLEDECEK)

Bu dosya kimlik doğrulaması olmadan çalıştırılırsa `migrate:fresh --seed` komutunu çağırıyor — yani tüm
canlı veritabanını siler ve yeniden seed'ler. Dosyanın kendi çıktısı bile şunu söylüyor: *"Delete this file
(public/setup.php) now... anyone who finds this URL can erase your production data."* `migrate.php`'nin
aksine (`?wipe_and_reseed=yes-i-am-sure-wipe-everything` gibi bir onay parametresi gerektirir), bu dosyanın
hiçbir koruması yok.

Bu bulguyu test etmek için **hiçbir HTTP isteği atılmadı** (bir `HEAD` isteği bile tetikleyebileceği için).
Kullanıcı bu konuşma sırasında bunu kendisinin halledeceğini belirtti — sunucudan (cPanel/WinSCP ile)
dosyanın varlığını kontrol edip silmesi gerekiyor. **Bu rapor tarihi itibarıyla hâlâ açık bir risk olabilir,
lütfen doğrulayın.**

### 1.2 `ClientAppointmentController::index()` — hastanın tüm randevu geçmişi + klinik notları başka bir
### doktora sızıyor (IDOR)

`GET /clients/{client}/appointments` (`app/Http/Controllers/Api/ClientAppointmentController.php:9-17`)
`AuthorizesOwnDoctorRecords` trait'ini kullanmıyor, `assertActingDoctorOwnsClient()` çağırmıyor — hatta
`Request` parametresi bile almıyor. Kardeşi `ClientVisitController::index()` bunu doğru yapıyor.

**Sonuç:** Şirketteki HERHANGİ bir doktor, kendi hastası olmayan bir hastanın (`primary_doctor_id` başka bir
doktora ait) TÜM randevu geçmişini — `notes`, `planned_summary`, `planned_notes` (AI-plan klinik içeriği
dahil) — görebiliyor. Bu, 2026-08-18'de yapılan proje-geneli "doktor scoping" düzeltmesinin (11 controller)
gözden kaçırdığı bir controller. **Aynı eksiklik `ClientPaymentController`'da da var** (bkz. 2.1) — bu ikisi
birlikte, `/clients/{client}/...` altındaki iç içe (nested) read endpoint'lerinde sistemik bir boşluk
olduğunu gösteriyor.

**Düzeltme:** `AuthorizesOwnDoctorRecords` trait'ini ekleyip `index()` içinde
`assertActingDoctorOwnsClient($request, $client)` çağrısı eklemek; kardeş nested-read controller'ları
(özellikle payments) aynı açık için taramak.

---

## 2. Yüksek Önem Bulgular

### 2.1 Muhasebe/Faturalama — 3 ayrı IDOR açığı + 1 çoklu para birimi hesap hatası
*(kaynak: arka-plan kod denetimi, ayrıca finding 1.2 ile birlikte canlıda dolaylı doğrulandı)*

- **`ClientPaymentController`** — hiçbir action'da doktor-sahiplik kontrolü yok (yukarıdaki 1.2 ile aynı
  desen). Bir doktor, kendi hastası olmayan birinin ödeme kayıtlarını listeleyebilir/oluşturabilir/
  düzenleyebilir/silebilir.
- **`InvoiceController::show`** — hiçbir yetki kontrolü yok; herhangi bir kullanıcı, id tahmin ederek
  herhangi bir faturayı (müşteri adı + tutar dahil) görebilir.
- **`AiTreatmentPlanController`** — tüm action'lar sadece "kullanıcı bir doktor mu" kontrolü yapıyor,
  "bu hastanın doktoru mu" kontrolü yapmıyor. `addCharge()` (elle ek ücret ekleme) dahil — yani başka bir
  doktorun hastasına keyfi ücret satırı eklenebilir.
- **Expense → Cari Hesap çoklu para birimi hatası** (`app/Http/Controllers/Api/ExpenseController.php:174-201`,
  `syncCari()`): `Expense.amount` her zaman TL, ama `cari_currency`/`cari_exchange_rate` bağımsız olarak
  USD/farklı kur seçilebiliyor. Cari kaydına `exchange_rate` hiç uygulanmadan ham TL tutarı yazılıyor.
  **Örnek:** 100 TL'lik bir gider, USD hesaba `cari_exchange_rate=32` ile bağlanırsa, Cari Hesap "100 USD
  borç" (~3.200 TL karşılığı) gösteriyor — gerçekte fondan sadece 100 TL çıkmışken. ~32 kat hata.

**Düzeltme yönü:** Her üçüne de `assertActingDoctorOwnsClient()`/eşdeğeri eklemek; Expense→Cari senkronunda
ya `cari_currency`'yi şirketin ana para birimiyle sınırlamak ya da gerçek "cari_currency cinsinden tutar"
alanı ekleyip `exchange_rate`'i gerçekten uygulamak.

### 2.2 AI Tedavi Planı — çok seanslı planlarda AYNI prosedür birden fazla kez faturalanıyor (bu denetimin en önemli bulgusu)

**Canlıda üretilip doğrulandı.** Diş 46 için (pulpitis → kanal tedavisi + kron) 4 seanslık bir AI planı
oluşturuldu. Onaylandıktan sonra gerçek `treatment_charges` tablosuna şu satırlar yazıldı:

```
Seans 1 · 46: Root Canal Medication (Temporary Dressing) — 1.000 TL
Seans 1 · 46: Caries on Caries subcrown — 350 TL
Seans 2 · 46: Root Canal Treatment (Complete) — 4.500 TL
Seans 3 · 46: Crown Preparation — 1.500 TL
Seans 3 · 46: Crown - temporary — 1.800 TL
Seans 3 · 46: Root Canal Treatment (Complete) — 4.500 TL   ← AYNI işlem, aynı diş, tekrar
Seans 4 · 46: Root Canal Treatment (Complete) — 4.500 TL   ← AYNI işlem, aynı diş, ÜÇÜNCÜ kez
Toplam: 18.150 TL — bunun 13.500 TL'si TEK bir kanal tedavisinin üç kez faturalanmasından ibaret
(9.000 TL fazladan ücretlendirme, sadece bu tek test hastasında).
```

**Kök sebep (kodun kendisinden doğrulandı):** Bir dişin AI-planlı durumu (ör. "kanal tedavisi tamamlandı"),
o diş için sonraki her seansın odontogram anlık görüntüsünde de doğal olarak kalıcı kalıyor (odontogram
dişin o anki BİRİKİMLİ durumunu temsil ediyor, "bu seansta ne değişti"yi değil). Hem ekrandaki "Procedures &
Charges" önizlemesi hem de backend'e GERÇEKTEN gönderilen veri, HER SEANS için ayrı ayrı
`buildOdontogramV2DisplayRows(session.odontogramV2Status)` çağrılarak oluşturuluyor
(`Dental_FrontEnd/app/frontend/src/utils/aiTreatmentPlan.js:51-71`, `buildAiTreatmentPlanConfirmFormData()`
— satır 64'teki döngü) — önceki seansta zaten faturalanmış bir şeyin tekrar sayılmaması için HİÇBİR
mükerrerlik kontrolü yok. Backend'in `confirm()` action'ı da bu diziyi olduğu gibi işleyip
`TreatmentCharge` satırlarına dönüştürüyor — yani bu sadece bir ekran hatası değil, gerçekten
faturalanıyor. Bu, daha önce düzeltilen "double-counted treatment charges" (commit `1ef895a`) hatasından
FARKLI bir hata — o, randevu güncellemesiyle ilgiliydi, bu ise AI planının KENDİ çok-seanslı ücret
oluşturma mantığında.

**Düzeltme yönü:** Her seansın charge_items'ını oluştururken, bir diş+prosedür/durum kombinasyonunu plan
genelinde SADECE BİR KEZ faturalamak (ör. her seansın odontogram durumunu bir ÖNCEKİ seansla karşılaştırıp
sadece YENİ ortaya çıkan durumları fiyatlandırmak) — hem gerçek payload'da
(`buildAiTreatmentPlanConfirmFormData()`) hem ekran önizlemesinde (`chargeAutoItems`).

### 2.3 Randevular — doktor-sahiplik atlaması, şirketler-arası `client_id` doğrulama açığı, iptalde ücret kalıntısı
*(kaynak: arka-plan kod denetimi)*

- **`ClientAppointmentController::index()`** — bkz. 1.2 (Kritik olarak listelendi).
- **Doktor yazma-yolu ownership kontrolü yok:** `AppointmentController`/`ClientVisitController` sadece
  "bu doktor_id gerçekten sen misin" kontrolü yapıyor, "bu hasta senin hastan mı" kontrolü yapmıyor. Bir
  doktor, başka bir doktorun hastası için randevu/visit oluşturup klinik not + ücret ekleyebilir.
- **Şirketler-arası `client_id`:** `StoreAppointmentRequest`/`UpdateAppointmentRequest`'teki
  `exists:clients,id` kuralı, `Client`'ın şirket-scope'unu (global scope) BYPASS ediyor — başka bir
  şirketin `client_id`'si doğrulamadan geçebilir, sonra `$appointment->client` `null` dönüp
  `TypeError` (500) fırlatıyor ama önce bozuk kayıt zaten oluşturulmuş oluyor.
- **İptal edilen randevunun ücretleri silinmiyor:** `status` doğrudan `update()` ile `cancelled` yapılınca
  (silme değil), `destroy()`/`noShow()`'un aksine ilgili `TreatmentCharge` satırları temizlenmiyor — iptal
  edilmiş bir tedavi kalıcı olarak hastanın borcunda kalıyor.

**Düzeltme yönü:** İlgili yerlere `assertActingDoctorOwnsClient()` eklemek; `client_id` doğrulamasını
şirkete göre scope'lamak (`treatment_catalog_id` için zaten kullanılan `Rule::exists()->where('company_id',...)`
deseniyle); `update()`'te `status=cancelled` olduğunda `treatmentCharges->deleteAllForAppointment()`
çağırmak.

### 2.4 Çoklu-uzmanlık bağlamı: doktor-olmayan personel yeni hasta oluşturduğunda uzmanlığa hiç kayıt olmayabiliyor

**Canlıda üretilip doğrulandı.** Çoklu uzmanlıklı bir şirkette (bu test şirketi gibi), doktor OLMAYAN bir
personel (System Manager vb.) yeni hasta eklediğinde, hasta gerçekten oluşturuluyor (201, arayüzde görünüyor)
ama HİÇBİR uzmanlığa kayıtlı olmayabiliyor — bir sonraki sayfa yüklemesinde o uzmanlığın hasta listesinden
tamamen kayboluyor, hiçbir hata mesajı göstermeden. (`GET /api/nutrition/clients` boş dönüyor, ama
`GET /api/clients?name=...` hastanın gerçekten var olduğunu, sadece uzmanlık kaydı olmadığını doğruluyor.)

**Kök sebep:** `ClientController::store()` sadece şu iki durumda uzmanlık kaydı açıyor: (a) işlemi yapan
doktorsa kendi uzmanlığına, (b) istek gövdesinde `specialty_id` varsa ona. Frontend, doktor-olmayan
kullanıcılar için `activeSpecialtyId`'yi göndermeye çalışıyor (`AppStateApiContext.jsx:2293`) ama bu değer
sadece Launcher sayfasındaki karo tıklamasıyla set ediliyor ve `localStorage`'da tutuluyor — **hiçbir zaman
o an görüntülenen URL'den türetilmiyor.** Yani bir personel, Launcher'daki karoya hiç tıklamadan (bookmark,
paylaşılan link, yeni sekme, temizlenmiş localStorage) doğrudan bir uzmanlığın sayfasına giderse,
`activeSpecialtyId` boş kalıyor ve kayıt sessizce atlanıyor.

**Bununla bağlantılı, daha da görünür bir tezahürü:** Aynı kök sebepten dolayı, bir BESLENME hastasının
Client Details sayfasından ("Appts" sekmesi) "New Appointment" açıldığında — URL açıkça
`/app/nutrition/client-details/...` olsa bile — modal DİŞ uzmanlığının arayüzünü gösterdi: "Doctor" alanı
varsayılan olarak bir DİŞ doktoruna ayarlandı, ve anlamsız bir "Selected Teeth / Select Teeth" (odontogram)
seçici belirdi. Yani bir personel, bir beslenme hastasının randevusunu yanlışlıkla bir diş doktoruna
atayabilir ve/veya hastanın faturasına diş işlemi ekleyebilir — arayüzde hiçbir uyarı olmadan.

**Düzeltme yönü:** `activeSpecialtyId`'yi sadece `localStorage`'a değil, o an görüntülenen rotaya göre de
doğrulamak/türetmek; randevu-oluşturma modalının uzmanlığını (dolayısıyla doktor listesini, prosedür
listesini, diş-seçiminin gösterilip gösterilmeyeceğini) açıldığı hasta/sayfadan almasını sağlamak, ayrı ve
bayat kalabilen global bir bayraktan değil.

### 2.5 Maaş ödemesi yarış durumu + avans mahsup hatası
*(kaynak: arka-plan kod denetimi)*

- **Yarış durumu:** "Bu dönem için zaten ödendi mi" kontrolü, ödeme kaydını oluşturan `DB::transaction()`'ın
  DIŞINDA ve ÖNCESİNDE çalışıyor, tekrar kilit/kontrol yok, `salary_payments` tablosunda benzersizlik
  (unique) kısıtı yok. İki eşzamanlı ödeme isteği aynı çalışana aynı dönem için iki kez ödeme
  oluşturabilir (fon çıkışı ve komisyon iki katına çıkar).
- **Avans mahsup hatası:** Bir çalışanın avansı, o dönemin net kazancından büyükse, net ödeme 0'da
  sınırlanıyor (doğru) ama avansın TAMAMI "mahsup edildi" olarak işaretleniyor (yanlış) — mahsup
  edilemeyen kısım hiçbir yerde görünmez hale geliyor (sadece ayrı Cari Hesap bakiyesinde, gizli negatif
  olarak hayatta kalıyor).

**Düzeltme yönü:** `(company_id,user_id,period_year,period_month)` üzerine unique kısıt + transaction
içinde `lockForUpdate()`; avans mahsubunu sadece o dönem gerçekten karşılanan tutar kadar yapıp kalanını
bir sonraki döneme bırakmak.

---

## 3. Orta Önem Bulgular

### 3.1 `BranchController::index()` — muhasebe yetkisi olmayan HER kullanıcı giriş sonrası hata görüyor
*(canlıda bizzat üretildi, ayrıca 2 ayrı arka-plan ajanı bağımsız olarak da buldu)*

Salt diş hekimi (Doctor rolü, `manage_accounting` yetkisi olmayan) bir hesapla giriş yapıldığında, Patients
sayfasına varır varmaz **"Request Error — You are not authorized to access accounting"** hata penceresi
çıkıyor. Sebep: `GET /api/branches` (sadece şube adı/sayısı döner, hiçbir finansal veri yok) tamamen
`assertHasAccountingAccess()` ile kilitli. Bu, 2026-08-13'te düzeltilen "Branch/Payroll permission-gate"
hatasıyla AYNI desen — o zaman Dashboard/Inventory/Reports düzeltilmişti, `BranchController::index()`
gözden kaçmış (ya da aynı desenin başka bir tekrarı).

**Düzeltme:** Sadece `index()`'i bu kilitten çıkarmak; `store()`/`update()`/`destroy()`/`summary()`
(finansal veri döndüren/yapısal değişiklik yapan) doğru şekilde kilitli kalmalı.

### 3.2 KVKK — lab sonucu görsel analizinde onay kontrolü atlanıyor
*(kaynak: arka-plan kod denetimi)*

`POST clients/{client}/lab-results/analyze` (`PatientLabResultController::analyze()`), diğer TÜM AI
uçlarının aksine `kvkk.consent` middleware'i ile sarılmamış — bir lab raporu fotoğrafını hiçbir hasta onay
kontrolü olmadan doğrudan OpenAI'ye gönderiyor. `KVKK_AI_CONSENT_REQUIRED` bayrağı ileride tekrar `true`
yapıldığında, DİĞER tüm AI uçları onayı yeniden zorunlu kılacak ama bu uç kılmayacak.

**Düzeltme:** Rotayı `kvkk.consent` ile sarmak; onam metnine lab raporu fotoğrafı ibaresi eklemek.

### 3.3 `LabPaymentController` — doktor-sahiplik kontrolü eksik (aynı şirket içi IDOR)
*(kaynak: arka-plan kod denetimi)*

Kardeşi `LabCaseController`'ın aksine, hiçbir action'da doktor-sahiplik kontrolü yok — bir doktor başka bir
doktorun hastasının lab ödemelerini listeleyebilir/ekleyebilir/silebilir.

### 3.4 Hatırlatma mesajları sessizce kayboluyor, WhatsApp/SMS gönderiminde ağ hatası yakalanmıyor
*(kaynak: arka-plan kod denetimi)*

`reminder_sent_at`, mesaj gerçekten GÖNDERİLMEDEN ÖNCE işaretleniyor — gönderim başarısız olsa bile
(3 deneme hakkı tükenince) o randevunun hatırlatması bir daha asla denenmiyor, hiçbir yerde görünür
olmadan kayboluyor. Ayrıca `WhatsAppService`/`IletiMerkeziSmsService`, ağ seviyesi hatalarını (zaman aşımı,
bağlantı reddi) yakalamıyor — bu da yukarıdaki kayıp senaryosunu tetikleyen olaylardan biri.

### 3.5 Aşırı-geniş `AuthorizesAccounting` kullanımı — finansal olmayan ayarlar da "muhasebe" iznine bağlı
*(kaynak: arka-plan kod denetimi + kısmen canlıda doğrulandı: WhatsApp ayarları sayfası gerçekten sadece
muhasebe yetkili kullanıcıya açık, ama bu davranış zaten mevcut testlerle kasıtlı/test edilmiş durumda)*

`MessageTemplateController`, `CallWebhookSettingsController`, `CrmSettingsController`,
`WhatsAppSettingsController` — hiçbiri finansal veri döndürmüyor ama hepsi "muhasebe erişimi" ile kilitli.
Sadece muhasebeci (Accountant) rolündeki, başka hiçbir yönetici yetkisi olmayan bir hesap; WhatsApp API
tokenini değiştirebilir, webhook sırrını yeniden üretebilir, CRM bağlantısını değiştirebilir. Bu, 3.1'deki
sorunun aynı kalıbı — yeni ayar ekranları eklendikçe tekrarlanma riski taşıyor.

### 3.6 Randevu iptal/no-show durumları slot müsaitliğini tutarsız etkiliyor
*(kaynak: arka-plan kod denetimi)*

`no_show` olarak işaretlenmiş bir randevu, o slotu hâlâ dolu gösteriyor (sadece `cancelled` hariç
tutuluyor) — resepsiyon, doktor gerçekte müsaitken o saati yeniden dolduramıyor.

---

## 4. Düşük Önem / Kozmetik Bulgular

- **Sayfalama göstergesi "1 / 0" gösteriyor** — tam olarak 1 kayıt varken sayfalama "1 / 0" yazıyor
  (dental ve nutrition hasta listelerinde gözlemlendi); muhtemelen toplam-sayfa hesaplamasında bir
  yuvarlama/sıfıra bölme kenar durumu.
- **Uzmanlığa duyarsız kenar menüsü:** Beslenme (Dietavaria) uygulaması içindeyken bile, muhasebe yetkili
  bir personel hesabı "X-Ray Images" ve "CBCT Scans" gibi tamamen diş-hekimliğine özgü bağlantıları
  görüyor (doktor hesaplarında görünmüyor — yani uzmanlığa göre değil, izne göre gösteriliyor ve
  uzmanlık kontrolü hiç eklenmemiş).
- **Maaş avansı/Cari Hesap satırında hem borç hem alacak aynı anda ayarlanabiliyor** — validasyon
  "en az biri" diyor, "sadece biri" demiyor; toplamlar bozulmuyor ama model'in kendi "asla ikisi birden
  olmaz" varsayımını ihlal ediyor.
- **`Payment.visit_id`** ilgili visit'in aynı hasta/şirkete ait olduğunu doğrulamıyor (referans bütünlüğü
  boşluğu, finansal toplamları etkilemiyor).
- **Zaman dilimi:** `config/app.php` `'timezone' => 'UTC'` olarak sabitlenmiş, ortam değişkeninden
  okunmuyor; Türkiye UTC+3 sabit. Randevu saatlerinin gerçekten UTC mi yoksa yerel saat mi olarak
  girildiği doğrulanamadı — **kullanıcıya sorulmalı.**

---

## 5. Doğrulanan / Sağlıklı Çalışan Alanlar (bilgi amaçlı, aksiyon gerekmez)

- Ödeme kaydı → Fon (Fund) defterine doğru yansıma (canlıda üretilip doğrulandı: 1.500 TL ödeme, Fund
  sayfasında Balance/Money In/Net'e doğru şekilde yansıdı).
- Fatura numarası tekilliği ve `InvoiceService::createForPayment()`'ın `withTrashed()->lockForUpdate()`
  kilidi sağlam — daha önceki "soft-delete edilmiş fatura numarası" hatası tekrar etmiyor.
- Doktor komisyonu hesaplaması (`sumRealizedRevenueForDoctorInMonth()`) doğru şekilde sadece kendi
  hastalarının, sadece o ay, sadece gerçekleşmiş (visit-realized) ücretlerini baz alıyor.
- OTP giriş akışı (`ILETIMERKEZI_ENABLED=false` + `MOBILE_OTP_FIXED_CODE`) doğru çalışıyor, gerçek SMS
  göndermiyor.
- KVKK onay bayrağının kendi mantığı (açık/kapalıyken doğru engelleme/izin) sağlam; tek eksik AI
  uzmanlık-planı akışlarının KVKK kapısına bağlı olması değil, 3.2'deki lab-sonucu ucu.
- `otp-peek.php` gerçekten kaldırılmış (dosya sisteminde ve git geçmişinde iz yok).
- KVKK demo veri seeder'ı (`DemoDataSeeder`) idempotent, `DatabaseSeeder`'a otomatik bağlı değil — sadece
  `migrate.php` üzerinden bilinçli tetikleniyor.
- Reçeteler (Prescriptions) modülü tam doktor-sahiplik kontrollü, XSS'e karşı temiz.
- Contact/Get-a-Quote formlarında XSS yok (tüm alanlar doğru escape ediliyor) — sadece rate-limit eksik
  (bkz. aşağıdaki liste).
- AI Tedavi Asistanı'nın klinik akıl yürütmesi (belirti sorgulama, netleştirici sorular, çok-seanslı plan
  önerisi) hem diş hem beslenme uzmanlığında gerçekten tutarlı ve klinik olarak makul (dil kayması dışında).

---

## 6. Diğer Açık Öğeler (bu oturumda bilgi amaçlı toplandı, yeni değil)

- Landing sayfası Contact/Quote formlarında rate-limit yok (spam riski, XSS yok).
- `public/setup.php` dışında başka bir tehlikeli betik bulunamadı.
- `public/app` içindeki dosyaların "orphan" (kaynak karşılığı olmayan) silinme riski hâlâ yapısal olarak
  mevcut ama şu an bilinen bir yetim dosya yok.
- KVKK AI onay bayrağının canlı `.env`'deki gerçek değeri statik incelemeden doğrulanamadı (yalnızca kod
  mantığı doğrulandı) — daha önceki bellek kaydına göre 2026-09-12'den beri kullanıcının bilinçli kararıyla
  `false`.

---

## 7. Düzeltme Planı (öncelik sırası)

**Aşama 0 — Hemen (kullanıcı tarafından):**
1. `public/setup.php`'nin canlı sunucuda hâlâ erişilebilir olup olmadığını kontrol edip silin/koruyun.

**Aşama 1 — Bu hafta (veri bütünlüğü + para kaybı):**
2. AI plan çok-seans mükerrer faturalama (2.2) — gerçek para kaybına yol açıyor, her yeni AI planında
   tekrarlanıyor.
3. `ClientAppointmentController`/`ClientPaymentController` IDOR açıkları (1.2, 2.1) — hasta gizliliği/KVKK
   riski.
4. Doktor-olmayan personelin hasta oluştururken uzmanlık kaydı kaybı + randevu modalının yanlış uzmanlık
   göstermesi (2.4) — sessiz veri kaybı, yanlış doktora randevu ataması riski.

**Aşama 2 — Bu ay:**
5. `BranchController::index()` ve benzer aşırı-geniş `AuthorizesAccounting` kullanımları (3.1, 3.5) —
   günlük kullanımı bozuyor ama veri kaybı yok.
6. Expense→Cari çoklu para birimi hatası (2.1) — muhasebe raporlarını yanlış gösteriyor.
7. Randevu tarafındaki diğer IDOR/doğrulama açıkları (2.3).
8. Maaş yarış durumu + avans mahsup hatası (2.5).
9. KVKK lab-sonucu onay atlaması (3.2).

**Aşama 3 — Fırsat buldukça:**
10. AI sohbet dil kayması (2.2/Finding 4) — kullanıcı deneyimi sorunu, veri kaybı yok ama güven kırıcı.
11. Hatırlatma mesajı kaybı + WhatsApp/SMS hata yakalama (3.4).
12. Kozmetik/düşük öncelikli maddeler (bölüm 4).

---

## 8. Test Ortamı / Temizlik

Bu denetim için oluşturulan izole test verileri:
- Şirket: **"ZZZ QA SIMULASYON - SILINECEK"** (admin panelinde Companies listesinde, code
  `QA-TEST-DELETE-ME`)
- Bu şirkete bağlı ~11 test kullanıcısı (QA Dental Doctor, QA Nutrition Doctor, QA Staff Manager 1-25 vb.,
  hepsi `+9639005550xx` numaralarıyla, e-postaları `...@doctovaria.internal`)
- 3 test hastası ("QA Test Hasta Bir/Iki/Uc") ve bunlara bağlı randevu/visit/ödeme/fatura/X-ray/vücut ölçümü
  kayıtları

Bu şirketi admin panelinden (Companies → Delete) silmek, bağlı tüm kullanıcı/hasta/randevu/ödeme verilerini
de kaldırır. İncelemeniz bittiğinde silinmesini isterseniz haber verin.
