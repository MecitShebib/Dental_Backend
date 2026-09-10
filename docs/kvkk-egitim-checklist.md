# KVKK Farkındalık Eğitimi Kontrol Listesi

> Bkz. [KVKK Uyumluluğu Planı](superpowers/plans/2026-09-05-kvkk-uyumlulugu.md), Görev 8.1. Bu belge, klinik personeli ve platform ekibi için asgari KVKK farkındalık eğitiminin kapsamını tanımlar; gerçek eğitimin verilmesi ve katılımın kaydı bu belgenin kapsamı dışındadır (organizasyonel bir adım, kodla otomatikleştirilemez).

## Klinik personeli (doktorlar, resepsiyon, sistem yöneticileri)

- [ ] KVKK'nın temel kavramları: kişisel veri, özel nitelikli kişisel veri (sağlık verisi dahil), veri sorumlusu/veri işleyen ayrımı.
- [ ] Kendi kliniklerinin **veri sorumlusu** olduğunu, platformun ise **veri işleyen** olduğunu anlarlar -- hastadan alınacak açık rıza ve aydınlatma yükümlülüğü klinik personeline aittir.
- [ ] Yeni bir hasta kaydı oluştururken Aydınlatma Metni'nin ve (yapay zeka özellikleri kullanılacaksa) Açık Rıza Beyanı'nın imzalatılması gerektiğini bilirler (bkz. Client Details > Consent sekmesi).
- [ ] Bir hastanın "verimi görmek/silmek istiyorum" talebini nasıl yönlendirecekleri: `GET /api/clients/{client}/data-export` ve `DELETE /api/clients/{client}/personal-data` endpoint'lerinin admin panel/frontend karşılığı (bkz. plan Faz 3).
- [ ] Şüpheli bir erişim/hesap ele geçirme durumunda kime (sistem yöneticisi/platform desteği) haber verecekleri.
- [ ] Hasta verisini (röntgen, notlar) e-posta/WhatsApp gibi güvensiz kanallardan **paylaşmamaları** gerektiği -- bu, platformun kendi güvenlik önlemlerini (imzalı URL'ler, private disk) baypas eder.

## Platform ekibi (geliştirme)

- [ ] Yeni bir tabloya kişisel/sağlık verisi eklendiğinde `docs/kvkk-veri-envanteri.md`'nin güncellenmesi gerektiği.
- [ ] Hiçbir kişisel/sağlık verisi içeren dosyanın `public` diske veya kimlik doğrulamasız bir route'a konulmaması (bkz. Faz 0.1'in düzelttiği açık).
- [ ] OpenAI'a (veya başka bir üçüncü tarafa) yeni bir veri kategorisi gönderen bir özellik eklenmeden önce, bunun KVKK m.9 aktarım dayanağı (açık rıza veya SCC) gerektirdiğinin farkında olunması -- bkz. `RequiresKvkkConsent` middleware'inin nasıl genişletileceği.
- [ ] `AuditLog`'un neyi kaydettiği ve yeni bir hassas-veri modeli eklerken `Auditable` trait'inin uygulanması gerektiği.
- [ ] `kvkk:purge` ve `kvkk:detect-anomalous-access` komutlarının prod'da çalışması için cPanel cron'una ihtiyaç olduğu (bkz. saklama politikası belgesi).

## Periyodik gözden geçirme

- [ ] Bu eğitim ve [KVKK Uyumluluğu Planı](superpowers/plans/2026-09-05-kvkk-uyumlulugu.md) yılda bir gözden geçirilir (bkz. plan Görev 8.2).
