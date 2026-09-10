# KVKK Kişisel Veri İşleme Envanteri

> Bkz. [KVKK Uyumluluğu Planı](superpowers/plans/2026-09-05-kvkk-uyumlulugu.md), Görev 1.1. Bu envanter, `app/Models/*.php` altındaki modellerin kod taramasından çıkarılmıştır; VERBİS kaydı yapılacaksa bu tablo başlangıç noktası olarak kullanılabilir, ancak resmi VERBİS bildirimi öncesi bir KVKK danışmanınca teyit edilmelidir.

## Platformun rolü

- **Hasta verisi için**: Dentavaria/Doctovaria platformu **veri işleyendir**; her klinik (`Company`) kendi hastalarının verisi için **veri sorumlusudur**.
- **Web sitesi ziyaretçisi, klinik personeli, ve platformun kendi çalışanları için**: Dentavaria **veri sorumlusudur**.

## Veri kategorileri ve tablolar

| Veri kategorisi | İlgili tablo(lar) | Hukuki sebep | Aktarılan taraflar |
|---|---|---|---|
| Hasta kimlik/iletişim | `clients` | KVKK m.5/2(c), sözleşme | — |
| Hasta sağlık verisi (özel nitelikli) | `treatment_records`, `treatment_record_teeth`, `visits`, `patient_lab_results`, `xray_images`, `care_plans`, `care_plan_sessions` | KVKK m.6/3, sır saklama yükümlülüğü altında sağlık hizmeti amacıyla | Röntgen/vaka açıklaması → OpenAI (yalnızca açık rıza ile) |
| Hasta rıza kayıtları (imza = biyometrik-benzeri) | `consent_templates`, `client_consents` | KVKK m.5/2(ç), yasal yükümlülük | — |
| Hasta randevu/ziyaret geçmişi | `appointments`, `visits`, `patient_recalls`, `satisfaction_surveys`, `call_logs` | KVKK m.5/2(c) | SMS/WhatsApp sağlayıcısı (hatırlatmalar için) |
| Hasta finansal verisi | `payments`, `treatment_charges`, `invoices`, `cari_transactions` | KVKK m.5/2(c), VUK saklama yükümlülüğü | — |
| Klinik personeli hesap verisi | `users`, `user_otps`, `sessions`, `personal_access_tokens` | KVKK m.5/2(c), m.5/2(ç) | — |
| Web sitesi ziyaretçi/talep verisi | `landing_page_inquiries` | KVKK m.5/2(c), m.5/2(f) | — |
| Yapay zeka konuşma/plan verisi | `ai_conversations`, `ai_conversation_messages`, `ai_usage_logs`, `treatment_charges` (source=ai_plan) | KVKK m.6/2 (açık rıza, özel nitelikli veri için) | OpenAI (ABD) |
| Denetim/erişim kaydı | `audit_logs` | KVKK m.5/2(f), m.12 (veri güvenliği yükümlülüğü) | — |

## Üçüncü taraf işleyenler (alt işleyenler)

| Sağlayıcı | Amaç | Aktarılan veri | Ülke |
|---|---|---|---|
| OpenAI | Yapay zeka tedavi planı, röntgen okuma, ses transkripsiyonu | Vaka açıklaması, röntgen görüntüsü (base64), ses kaydı | ABD |
| İleti Merkezi (SMS) | OTP, randevu hatırlatma SMS'leri | Telefon numarası, mesaj içeriği | **Türkiye** (yurt içi aktarım, KVKK m.9 kapsamı dışında — bkz. `IletiMerkeziSmsService`) |
| WhatsApp Cloud API (Meta) | Randevu hatırlatmaları (opsiyonel, klinik kendi hesabını bağlar) | Telefon numarası, mesaj içeriği | ABD/İrlanda (Meta altyapısı) |
| Barındırma sağlayıcısı | Uygulama + veritabanı barındırma | Tüm veri kategorileri | Sunucunun bulunduğu ülke (bkz. prod ortam) |
| AWS S3 (opsiyonel, yedekleme) | Yedekleme (varsayılan olarak devre dışı) | Tüm veri kategorileri | `.env`'deki `AWS_DEFAULT_REGION`'a bağlı |

## Sonraki adımlar

1. VERBİS kaydı gerekip gerekmediğini bir KVKK danışmanına teyit ettirin (bkz. plan Görev 1.2).
2. OpenAI ile mevcut sözleşmenin KVKK m.9 gereksinimlerini karşıladığını teyit edin (bkz. plan Görev 5.1) — İleti Merkezi artık yurt içi olduğundan bu madde SMS için geçerli değil, sadece bir veri işleyen sözleşmesi (KVKK m.8) yeterli.
3. Bu tabloyu VERBİS'e kayıt öncesi güncel tutun -- yeni bir tablo/entegrasyon eklendiğinde buraya bir satır eklemek, sonradan toplu bir envanter çalışması yapmaktan çok daha ucuzdur.
