# Veri İhlali Müdahale Prosedürü

> Bkz. [KVKK Uyumluluğu Planı](superpowers/plans/2026-09-05-kvkk-uyumlulugu.md), Faz 6. KVKK m.12 uyarınca, işlenen kişisel verilerin kanuni olmayan yollarla başkaları tarafından elde edilmesi hâlinde, veri sorumlusu bunu en kısa sürede ilgilisine ve Kişisel Verileri Koruma Kurulu'na ("Kurul") bildirmekle yükümlüdür. Kurul, mevcut uygulamasında bu bildirimin **72 saat içinde** yapılmasını beklemektedir.

## Platformun rolü: veri işleyen

Dentavaria, klinik hasta verisi için **veri işleyendir**; klinik ise **veri sorumlusudur**. Bu nedenle, platformda tespit edilen bir ihlalde Kurul'a bildirim yükümlülüğü doğrudan platforma değil, etkilenen kliniğe aittir -- platformun görevi, ihlali **gecikmeksizin kliniğe bildirmektir** (bkz. Terms of Service, bölüm 15, `app/Support/LegalContent.php`), böylece klinik kendi 72 saatlik süresini işletebilsin.

## Adım adım prosedür

### 1. Tespit

İhlal şu yollardan biriyle fark edilebilir:
- `kvkk:detect-anomalous-access` komutunun otomatik e-posta uyarısı (bkz. `app/Console/Commands/DetectAnomalousAccess.php`) -- bir kullanıcının 1 saat içinde 30'dan fazla farklı hasta kaydı görüntülemesi.
- `audit_logs` tablosunun manuel incelenmesi (örn. beklenmeyen bir IP adresinden erişim, mesai saatleri dışında toplu görüntüleme).
- Sunucu/barındırma sağlayıcısından gelen bir güvenlik bildirimi.
- Bir kullanıcının hesabının ele geçirildiğine dair şüphe (örn. şifre sıfırlama e-postaları beklenmedik şekilde gelmesi).

### 2. Kapsamın belirlenmesi

- Hangi tablo(lar)/kayıtlar etkilendi? `audit_logs` tablosunda ilgili zaman aralığında hangi `auditable_type`/`auditable_id` çiftlerine erişildiği sorgulanır.
- Kaç farklı klinik (`company_id`) etkilendi?
- Veri dışarı çıktı mı (indirildi/dışa aktarıldı), yoksa yalnızca görüntülendi mi? (`AuditLog::record('exported', ...)` çağrıları `action='exported'` olarak ayrı işaretlenir, bkz. `ClientDataRequestController::export()`.)

### 3. Durdurma

- Etkilenen kullanıcı hesabı derhal askıya alınır (`User.status = 'inactive'` veya admin panelinden devre dışı bırakılır).
- Sızıntının teknik kök nedeni varsa (örn. Görev 0.1'den önceki genel-erişimli dosya URL'leri gibi) ilgili açık kapatılır.

### 4. Bildirim

- **Etkilenen her Klinik'e**: gecikmeksizin, en geç 24 saat içinde, hangi hasta kayıtlarının etkilendiği bilgisiyle birlikte e-posta ile bildirilir. Klinik, kendi 72 saatlik Kurul bildirim süresini bu bildirimden itibaren işletir.
- **Kurul'a bildirim**: bu, etkilenen Kliniğin (veri sorumlusunun) sorumluluğundadır; platform yalnızca gerekli teknik detayları (etkilenen kayıt sayısı/türü, ihlalin nasıl gerçekleştiği, alınan önlemler) sağlar.

### 5. İyileştirme

- Kök neden analizi yazılı olarak kaydedilir.
- Benzer bir ihlali önleyecek teknik/idari önlem uygulanır ve bu belgeye veya [KVKK Uyumluluğu Planı](superpowers/plans/2026-09-05-kvkk-uyumlulugu.md)'na bir madde olarak eklenir.

## İletişim listesi

Bu bölüm, güncel irtibat bilgileriyle doldurulmalıdır (kod bu kısmı otomatikleştiremez):

- Platform tarafı sorumlu: _[isim/e-posta girilecek]_
- KVKK danışmanı/hukuk müşaviri: _[varsa girilecek]_
