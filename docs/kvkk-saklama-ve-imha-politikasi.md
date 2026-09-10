# Kişisel Veri Saklama ve İmha Politikası

> Bkz. [KVKK Uyumluluğu Planı](superpowers/plans/2026-09-05-kvkk-uyumlulugu.md), Faz 4. Bu belge, Kişisel Verilerin Silinmesi, Yok Edilmesi veya Anonim Hale Getirilmesi Hakkında Yönetmelik'in beklediği yazılı politika belgesidir ve `php artisan kvkk:purge` komutunun (`app/Console/Commands/PurgeExpiredPersonalData.php`) ne yaptığını insan-okur biçimde açıklar.

## Genel ilke

İşlenme amacı ortadan kalkan kişisel veri, KVKK m.7 uyarınca silinir, yok edilir veya anonim hale getirilir. Aşağıdaki tablo, her veri kategorisi için saklama süresini ve süre sonunda uygulanan işlemi listeler.

## Saklama süreleri ve imha kuralları

| Veri kategorisi | Saklama süresi | Süre sonunda | Uygulayan |
|---|---|---|---|
| OTP kodları (`user_otps`, `public_booking_otps`) | 1 gün | Kalıcı silme | `kvkk:purge` (otomatik, günlük) |
| Hasta verisi (aktif klinik) | Kliniğin aboneliği aktif olduğu sürece | — | — |
| Hasta verisi (aboneliği 365 günden uzun süredir sona ermiş klinik) | 365 gün | Anonimleştirme (`ClientErasureService::anonymize()`) -- isim/e-posta/telefon/doğum tarihi/adres/notlar maskelenir, röntgen ve rıza imza dosyaları kalıcı silinir | `kvkk:purge` (otomatik, günlük) |
| Finansal kayıtlar (`payments`, `treatment_charges`, `invoices`) | VUK gereği 5 yıl (hasta anonimleştirilse dahi bu kayıtlar silinmez) | Hasta kimliğiyle bağı zaten anonimleştirme adımında kesilmiştir; ayrıca bir işlem yapılmaz | — |
| Denetim kayıtları (`audit_logs`) | Süresiz (güvenlik olayı soruşturması için gerekli) | — | — |
| Yapay zeka konuşma kayıtları (`ai_conversations`, `ai_conversation_messages`) | Hastanın kendisiyle aynı (hasta anonimleştirildiğinde bu kayıtlar da hasta kimliğinden bağımsız hale gelir) | — | — |
| Yedekler (`storage/app/backup-temp`, `BACKUP_DISKS`) | `config/backup.php`'deki `keep_*` ayarlarına göre (7 gün tam, 16 gün günlük, 8 hafta haftalık, 4 ay aylık, 2 yıl yıllık) | Otomatik silme | `backup:clean` (mevcut, zaten planlı) |

## Manuel silme talepleri (KVKK m.11)

Bir hastanın veya kliniğin doğrudan silme talebi, yukarıdaki otomatik süreyi beklemeden `DELETE /api/clients/{client}/personal-data` (`ClientDataRequestController::destroy`) üzerinden anında işlenir -- bkz. plan Faz 3.2.

## İşletimsel not

`kvkk:purge` komutu `routes/console.php`'de `Schedule::command('kvkk:purge')->daily()` olarak tanımlıdır, ancak bu sunucuda `schedule:run`'ı tetikleyen bir cron **çalışmıyor** (bkz. proje hafızası "Shared hosting deploy gotchas"). Bu komutun gerçekten periyodik çalışması için üretim sunucusunda bir cPanel cron eklenmesi gerekir; eklenene kadar bu politika belgede tarif edildiği gibi değil, yalnızca elle (`php artisan kvkk:purge`) çalıştırıldığında uygulanır.
