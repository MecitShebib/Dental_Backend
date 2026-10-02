<?php

namespace App\Support;

/**
 * Static copy behind the public /privacy-policy and /terms-of-service pages,
 * written for the whole Doctovaria platform (all specialty products), not one
 * product. Kept as plain PHP rather than admin-editable content, since legal
 * text needs a human review pass before it changes, not a form.
 *
 * Placeholders resolved by get(): {entity} (legal entity name), {email}
 * (support inbox), {privacy_email} (data-protection inbox) -- all from
 * config('services.legal' / 'services.support').
 *
 * TERMS_VERSION: bump it whenever the Terms change materially -- every clinic
 * user is then asked to accept again before the API lets them continue (see
 * EnsureTermsAccepted / AuthController::acceptTerms()).
 */
class LegalContent
{
    public const TERMS_VERSION = '2026-10-02';

    public static function get(string $page, string $locale): array
    {
        $data = $page === 'terms' ? self::terms() : self::privacy();
        $content = $data[$locale] ?? $data['en'];

        $replacements = [
            '{entity}' => config('services.legal.entity_name') ?: 'Doctovaria',
            '{email}' => config('services.support.email') ?: 'hello@doctovaria.com',
            '{privacy_email}' => config('services.legal.privacy_email') ?: (config('services.support.email') ?: 'hello@doctovaria.com'),
        ];

        $content['intro'] = strtr($content['intro'], $replacements);
        foreach ($content['sections'] as $i => $section) {
            $content['sections'][$i]['heading'] = strtr($section['heading'], $replacements);
            $content['sections'][$i]['body'] = array_map(fn ($p) => strtr($p, $replacements), $section['body']);
        }

        if ($page === 'privacy') {
            // KVKK m.10: the controller's identity. Only the lines actually
            // configured are printed -- never a made-up address or number.
            $labels = self::identityLabels()[$locale] ?? self::identityLabels()['en'];
            $identity = array_filter([
                $labels['address'] => config('services.legal.entity_address'),
                $labels['mersis'] => config('services.legal.entity_mersis'),
                $labels['kep'] => config('services.legal.entity_kep'),
                $labels['email'] => $replacements['{privacy_email}'],
            ]);
            foreach ($identity as $label => $value) {
                $content['sections'][0]['body'][] = $label.': '.$value;
            }
        }

        return $content;
    }

    private static function identityLabels(): array
    {
        return [
            'en' => ['address' => 'Address', 'mersis' => 'MERSİS no', 'kep' => 'KEP address', 'email' => 'Data protection contact'],
            'tr' => ['address' => 'Adres', 'mersis' => 'MERSİS no', 'kep' => 'KEP adresi', 'email' => 'Kişisel veri başvuruları'],
            'ar' => ['address' => 'العنوان', 'mersis' => 'رقم MERSİS', 'kep' => 'عنوان KEP', 'email' => 'التواصل بشأن حماية البيانات'],
        ];
    }

    private static function privacy(): array
    {
        return [
            'en' => [
                'title' => 'Privacy Policy',
                'updated_label' => 'Last updated',
                'updated_date' => '2 October 2026',
                'intro' => 'Doctovaria is a cloud-based clinic management platform made up of specialty products — Dentavaria (dentistry), Gynevaria (gynecology), Medivaria (internal medicine), Orthovaria (orthopedics), Estevaria (aesthetic medicine), Dietavaria (nutrition), Pediavaria (pediatrics), Physiovaria (physiotherapy), Hemavaria (hematology), Surgivaria (general surgery) and Genervaria (general practice) — together with this website, the web and mobile application, the online booking pages and the API (together, the "Platform"). This Privacy Policy, issued under Turkish Law No. 6698 on the Protection of Personal Data ("KVKK") and, where applicable, the EU GDPR, explains which personal data is processed through the Platform, why, on what legal basis, with whom it is shared, how long it is kept and how you can exercise your rights.',
                'sections' => [
                    ['heading' => '1. Who we are', 'body' => [
                        'The Platform is operated by {entity} ("Doctovaria", "we", "us").',
                    ]],
                    ['heading' => '2. Our two roles: data controller and data processor', 'body' => [
                        'Data controller: for the personal data of website visitors, people who send us inquiries, and the users of our customer clinics (account, login, security and billing data), Doctovaria is the data controller.',
                        'Data processor: for the personal data of patients that a clinic, practice or healthcare provider ("Clinic") records on the Platform, the Clinic is the data controller and Doctovaria processes that data only on the Clinic\'s behalf and instructions, as a data processor (veri işleyen). The Clinic is responsible for informing its patients (aydınlatma), obtaining any required consent, and answering patients\' requests. Patients should contact their Clinic first; if a request reaches us, we forward it to the Clinic without delay.',
                    ]],
                    ['heading' => '3. Personal data we process', 'body' => [
                        'Website visitors and prospects: name, email, phone number, clinic/company name, specialty of interest, and the content of Contact / Get-a-Quote messages.',
                        'Clinic users (doctors, assistants, receptionists, accountants, managers): name, mobile phone number, email, role and permissions, specialty, branch, password (stored only as an irreversible hash), one-time login codes, login times, session tokens, the device/browser and IP address of each session, the signature and stamp images a doctor uploads for prescriptions and documents, and — where the Clinic uses the payroll module — salary, commission, salary payment and advance records.',
                        'Patient identity and contact data (entered by the Clinic or by the patient through the Clinic\'s online booking page): name, phone number, email, gender, date of birth, address, preferred language, assigned doctor, notes.',
                        'Patient health data (special-category data): appointments and visits, examination and treatment records, odontograms and procedure checklists, specialty-specific clinical records (e.g. vital signs, growth and vaccination records, ultrasound examinations, blood counts and transfusions, operations and follow-ups, physiotherapy sessions, body measurements and nutrition plans), care plans, prescriptions, laboratory cases and test results, X-ray images and DICOM/CBCT scans, uploaded clinical files, and signed consent forms (including the handwritten signature image).',
                        'Patient financial data: treatment charges and price lists, payments, invoices and current-account (cari) records, product sales.',
                        'Communication data: appointment reminders, recall messages, satisfaction surveys and their answers, WhatsApp/SMS/email messages sent through the Platform, documents shared as links, and — if the Clinic connects its telephone system — call logs (caller number, time, duration).',
                        'AI data: case descriptions typed or dictated by clinic staff, voice recordings sent for transcription, AI conversations, X-ray images or laboratory results submitted for AI analysis, the resulting suggestions, and the number of AI tokens consumed.',
                        'Security and audit data: an activity log of who viewed, created, changed, deleted or exported which record and when, with IP address and browser information; failed login attempts; technical server logs.',
                        'Support data: the content of help and support requests you send us.',
                    ]],
                    ['heading' => '4. Purposes of processing', 'body' => [
                        'To provide the Platform and each of its features; to create and secure accounts and verify identity with one-time codes; to schedule appointments and send reminders; to keep clinical, financial and inventory records on behalf of Clinics; to generate documents, prescriptions and reports; to provide AI-assisted features when a Clinic chooses to use them; to enforce subscription limits and the one-person-per-account rule; to keep an audit trail and detect misuse, unauthorized access and account sharing; to provide support; to invoice subscriptions; to answer inquiries; to back up data and recover from incidents; and to comply with legal obligations and requests from competent authorities.',
                    ]],
                    ['heading' => '5. Legal bases (KVKK Art. 5 and 6)', 'body' => [
                        'Website and inquiry data: Art. 5/2(c) — necessary to set up or perform a contract; Art. 5/2(f) — our legitimate interest in answering inquiries.',
                        'Clinic user data: Art. 5/2(c) — performance of the subscription contract; Art. 5/2(ç) — our legal obligations (e.g. information security under Art. 12, tax records); Art. 5/2(e) — establishment, exercise or defence of a right (including the activity log and the record of who accepted these terms); Art. 5/2(f) — legitimate interest in securing the Platform and preventing account sharing.',
                        'Patient data: processed on the Clinic\'s legal basis as data controller — typically Art. 6/3 for health data (processing by persons under a duty of confidentiality for medical diagnosis, treatment and care services and the planning and management of healthcare services) and Art. 5/2(c) and (ç) for identity, contact and financial data.',
                        'Explicit consent (açık rıza): required before a patient\'s health data is sent to an AI provider abroad. The Platform enforces this: AI features cannot be used for a patient until the Clinic has recorded that patient\'s consent through a KVKK consent form.',
                    ]],
                    ['heading' => '6. Artificial intelligence features', 'body' => [
                        'AI features (treatment-plan drafting, conversational AI assistant, voice transcription, X-ray reading and laboratory-result analysis) use OpenAI\'s API (OpenAI, L.L.C., United States). Only the content needed for the task is sent — the case description, transcript, image or test values — not the patient\'s contact details. Under OpenAI\'s API terms, data sent through the API is not used to train its models.',
                        'AI output is a suggestion for a licensed healthcare professional to review; it is never applied to a patient automatically. No decision producing legal or similarly significant effects is taken about any person solely by automated processing.',
                    ]],
                    ['heading' => '7. Who we share data with', 'body' => [
                        'We do not sell personal data and do not use patient data for advertising. Data is shared only with the following, each limited to its purpose:',
                        'Hosting and infrastructure providers that host the application, database, files and backups on our behalf.',
                        'İleti Merkezi (Türkiye) — delivery of login codes and appointment SMS messages; a domestic transfer.',
                        'Email delivery provider — login codes, notifications, reminders and support messages.',
                        'OpenAI (United States) — AI features only, and only after the patient\'s explicit consent is recorded.',
                        'Integrations a Clinic chooses to enable with its own accounts: Meta WhatsApp Business Platform (WhatsApp messages and documents), Zoho CRM (patient name, phone and email pushed as CRM contacts), the Clinic\'s own telephone/PBX provider (call logs), and any system the Clinic connects through its API tokens. These are activated by the Clinic as data controller; the Clinic is responsible for its contracts with those providers.',
                        'Competent public authorities and courts, when required by law.',
                    ]],
                    ['heading' => '8. International transfers', 'body' => [
                        'Transfers abroad (OpenAI, and Meta or Zoho where a Clinic enables them) are carried out in accordance with KVKK Art. 9 — on the basis of appropriate safeguards such as standard contractual clauses, or, for patient health data sent to AI, the patient\'s explicit consent. SMS delivery through İleti Merkezi stays within Türkiye.',
                    ]],
                    ['heading' => '9. Shared links, surveys and online booking', 'body' => [
                        'When a Clinic sends a document (prescription, plan, invoice, report) to a patient by WhatsApp, the PDF is stored privately and reached through an unguessable link that expires after 30 days. Satisfaction-survey links contain a unique random token. The online booking page asks the patient for a one-time SMS code before an appointment is created.',
                    ]],
                    ['heading' => '10. Cookies and browser storage', 'body' => [
                        'We use only strictly necessary cookies (the admin-panel session and its CSRF protection). The web application keeps the user\'s sign-in token and preferences (language, light/dark theme, selected specialty and branch) in the browser\'s local storage. We use no advertising or cross-site tracking cookies. Signing out removes the sign-in token from the browser.',
                    ]],
                    ['heading' => '11. How we protect data', 'body' => [
                        'Encrypted connections (HTTPS); password + one-time code sign-in; only one active session per account, with expiring sessions; role- and permission-based access; doctors limited to their own patients; strict separation between Clinics; X-rays, signatures and clinical files kept on private storage and served only through short-lived signed links; rate limiting against brute-force attempts; a full activity log; regular backups; and staff confidentiality obligations. No system is completely secure; if a data breach affects a Clinic\'s data we notify the Clinic without undue delay so it can meet its own 72-hour notification duty to the Personal Data Protection Board.',
                    ]],
                    ['heading' => '12. How long we keep data', 'body' => [
                        'One-time login and booking codes: deleted after 1 day.',
                        'Documents shared by link: deleted after 30 days.',
                        'Patient and clinic data: kept while the Clinic\'s subscription is active. If a subscription has ended for more than 365 days, patient records are anonymized (names and contact details masked, X-ray and signature files permanently deleted).',
                        'Financial and invoicing records: kept for the periods required by tax and commercial law (generally 5 to 10 years), detached from the patient\'s identity once the patient is anonymized.',
                        'Activity log and records of terms acceptance: kept as long as needed to investigate security incidents and for the applicable statutory limitation periods, as evidence.',
                        'Backups: rotated automatically and kept for at most 2 years.',
                        'Inquiry data: kept for as long as needed to answer and follow up the inquiry. A Clinic may request immediate erasure or export of a patient\'s data at any time through the Platform, subject to legal retention duties.',
                    ]],
                    ['heading' => '13. Your rights (KVKK Art. 11)', 'body' => [
                        'You have the right to learn whether your personal data is processed; to request information about it; to learn the purpose of processing and whether it is used accordingly; to know the third parties in Türkiye or abroad to whom it is transferred; to request correction of incomplete or inaccurate data; to request erasure or destruction under Art. 7; to request that correction or erasure be notified to the third parties it was transferred to; to object to a result against you arising exclusively from automated analysis; and to claim compensation for damage caused by unlawful processing.',
                        'Patients: please send your request to the Clinic that holds your records — it is the data controller. Everyone else: write to {privacy_email} from the email address registered with us, or send a written request to our address or registered electronic mail (KEP) address, with your name, identity details and the subject of the request. We answer within 30 days at the latest and free of charge, unless the request requires an additional cost under the Board\'s tariff. You may lodge a complaint with the Personal Data Protection Board (Kişisel Verileri Koruma Kurulu).',
                    ]],
                    ['heading' => '14. Children', 'body' => [
                        'Some products (e.g. Pediavaria) are used to keep records of minors. That data is entered by the Clinic under its own legal basis and, where needed, with the consent of the parent or legal guardian. We do not knowingly collect data directly from children through the website.',
                    ]],
                    ['heading' => '15. Changes', 'body' => [
                        'We may update this policy. The "Last updated" date at the top shows the current version; significant changes are also announced inside the application.',
                    ]],
                    ['heading' => '16. Contact', 'body' => [
                        'Data protection requests: {privacy_email}. General questions: {email}.',
                    ]],
                ],
            ],

            'tr' => [
                'title' => 'Gizlilik Politikası ve KVKK Aydınlatma Metni',
                'updated_label' => 'Son güncelleme',
                'updated_date' => '2 Ekim 2026',
                'intro' => 'Doctovaria; Dentavaria (diş hekimliği), Gynevaria (kadın hastalıkları ve doğum), Medivaria (iç hastalıkları), Orthovaria (ortopedi), Estevaria (estetik), Dietavaria (beslenme), Pediavaria (çocuk sağlığı), Physiovaria (fizyoterapi), Hemavaria (hematoloji), Surgivaria (genel cerrahi) ve Genervaria (pratisyen hekimlik) uzmanlık ürünlerinden oluşan bulut tabanlı bir klinik yönetim platformudur; bu internet sitesi, web ve mobil uygulama, online randevu sayfaları ve API ile birlikte "Platform" olarak anılır. Bu metin, 6698 sayılı Kişisel Verilerin Korunması Kanunu ("KVKK") m.10 uyarınca hazırlanmış aydınlatma metnidir ve uygulanabildiği ölçüde AB Genel Veri Koruma Tüzüğü (GDPR) ile uyumludur. Platform üzerinden hangi kişisel verilerin, hangi amaçla ve hukuki sebeple işlendiğini, kimlerle paylaşıldığını, ne kadar saklandığını ve haklarınızı nasıl kullanabileceğinizi açıklar.',
                'sections' => [
                    ['heading' => '1. Biz kimiz', 'body' => [
                        'Platform, {entity} ("Doctovaria", "biz") tarafından işletilmektedir.',
                    ]],
                    ['heading' => '2. İki rolümüz: veri sorumlusu ve veri işleyen', 'body' => [
                        'Veri sorumlusu olarak: İnternet sitesi ziyaretçileri, bize talep gönderen kişiler ve müşterimiz olan kliniklerin kullanıcılarına ait (hesap, giriş, güvenlik ve faturalama) kişisel veriler bakımından veri sorumlusu Doctovaria\'dır.',
                        'Veri işleyen olarak: Bir klinik, muayenehane veya sağlık kuruluşunun ("Klinik") Platform\'a kaydettiği hasta verileri bakımından veri sorumlusu Klinik\'tir; Doctovaria bu verileri yalnızca Klinik adına ve talimatıyla, veri işleyen sıfatıyla işler. Hastaların aydınlatılması, gerekli rızaların alınması ve hasta başvurularının yanıtlanması Klinik\'in sorumluluğundadır. Hastalar öncelikle Klinik\'e başvurmalıdır; bize ulaşan başvurular gecikmeksizin ilgili Klinik\'e iletilir.',
                    ]],
                    ['heading' => '3. İşlenen kişisel veriler', 'body' => [
                        'İnternet sitesi ziyaretçileri ve potansiyel müşteriler: ad soyad, e-posta, telefon, klinik/şirket adı, ilgilenilen uzmanlık ve İletişim / Teklif Al formlarıyla gönderilen mesaj içeriği.',
                        'Klinik kullanıcıları (hekim, asistan, resepsiyon, muhasebe, yönetici): ad soyad, cep telefonu, e-posta, rol ve yetkiler, uzmanlık, şube, şifre (yalnızca geri döndürülemez özet/hash olarak), tek kullanımlık giriş kodları, giriş zamanları, oturum anahtarları, her oturumun cihaz/tarayıcı ve IP adresi bilgisi, hekimin reçete ve belgeler için yüklediği imza ve kaşe görselleri ve — Klinik bordro modülünü kullanıyorsa — maaş, prim/komisyon, maaş ödemesi ve avans kayıtları.',
                        'Hasta kimlik ve iletişim verileri (Klinik tarafından ya da hastanın Klinik\'in online randevu sayfası üzerinden girdiği): ad soyad, telefon, e-posta, cinsiyet, doğum tarihi, adres, tercih edilen dil, sorumlu hekim, notlar.',
                        'Hasta sağlık verileri (özel nitelikli kişisel veri): randevu ve muayeneler, muayene ve tedavi kayıtları, odontogram ve işlem listeleri, uzmanlığa özgü klinik kayıtlar (ör. vital bulgular, büyüme ve aşı kayıtları, ultrason muayeneleri, kan sayımları ve transfüzyonlar, ameliyat ve kontroller, fizyoterapi seansları, vücut ölçümleri ve beslenme planları), bakım planları, reçeteler, laboratuvar vakaları ve tahlil sonuçları, röntgen görüntüleri ve DICOM/CBCT taramaları, yüklenen klinik dosyalar ve imzalı onam formları (el yazısı imza görseli dahil).',
                        'Hasta finansal verileri: tedavi ücretleri ve fiyat listeleri, ödemeler, faturalar, cari hesap kayıtları, ürün satışları.',
                        'İletişim verileri: randevu hatırlatmaları, kontrol çağrıları (recall), memnuniyet anketleri ve yanıtları, Platform üzerinden gönderilen WhatsApp/SMS/e-posta mesajları, bağlantı ile paylaşılan belgeler ve — Klinik telefon santralini bağlarsa — çağrı kayıtları (arayan numara, zaman, süre).',
                        'Yapay zeka verileri: klinik personelinin yazdığı veya sesle anlattığı vaka açıklamaları, metne çevrilmek üzere gönderilen ses kayıtları, yapay zeka sohbetleri, yapay zeka analizine gönderilen röntgen görüntüleri veya tahlil sonuçları, üretilen öneriler ve harcanan yapay zeka token miktarı.',
                        'Güvenlik ve denetim verileri: hangi kaydın kim tarafından, ne zaman görüntülendiği, oluşturulduğu, değiştirildiği, silindiği veya dışa aktarıldığına dair işlem kaydı (IP adresi ve tarayıcı bilgisiyle), başarısız giriş denemeleri, teknik sunucu kayıtları.',
                        'Destek verileri: bize gönderdiğiniz yardım ve destek taleplerinin içeriği.',
                    ]],
                    ['heading' => '4. İşleme amaçları', 'body' => [
                        'Platform\'un ve özelliklerinin sunulması; hesapların oluşturulması, güvenliği ve tek kullanımlık kodla kimlik doğrulama; randevu planlama ve hatırlatma gönderimi; Klinikler adına klinik, finansal ve stok kayıtlarının tutulması; belge, reçete ve raporların üretilmesi; Klinik tercih ettiğinde yapay zeka destekli özelliklerin sağlanması; abonelik limitlerinin ve "her hesap tek kişiye aittir" kuralının uygulanması; işlem kaydı tutulması, kötüye kullanımın, yetkisiz erişimin ve hesap paylaşımının tespiti; destek verilmesi; abonelik faturalaması; taleplerin yanıtlanması; yedekleme ve olaylardan kurtarma; hukuki yükümlülüklerin ve yetkili makam taleplerinin yerine getirilmesi.',
                    ]],
                    ['heading' => '5. Hukuki sebepler (KVKK m.5 ve m.6)', 'body' => [
                        'İnternet sitesi ve talep verileri: m.5/2(c) — sözleşmenin kurulması veya ifası için gerekli olması; m.5/2(f) — taleplere yanıt vermedeki meşru menfaatimiz.',
                        'Klinik kullanıcı verileri: m.5/2(c) — abonelik sözleşmesinin ifası; m.5/2(ç) — hukuki yükümlülüklerimiz (ör. m.12 kapsamındaki veri güvenliği, vergi kayıtları); m.5/2(e) — bir hakkın tesisi, kullanılması veya korunması (işlem kaydı ve bu şartları kimin kabul ettiğine dair kayıt dahil); m.5/2(f) — Platform\'un güvenliğini sağlama ve hesap paylaşımını önlemedeki meşru menfaatimiz.',
                        'Hasta verileri: veri sorumlusu Klinik\'in hukuki sebebine dayanılarak işlenir — sağlık verileri için genellikle m.6/3 (sır saklama yükümlülüğü altındaki kişilerce tıbbi teşhis, tedavi ve bakım hizmetleri ile sağlık hizmetlerinin planlanması ve yönetimi amacıyla işleme), kimlik, iletişim ve finansal veriler için m.5/2(c) ve (ç).',
                        'Açık rıza: Hastanın sağlık verisinin yurt dışındaki bir yapay zeka sağlayıcısına gönderilmesinden önce gereklidir. Platform bunu teknik olarak zorunlu kılar: Klinik, hastanın KVKK onam formunu kaydetmeden o hasta için yapay zeka özellikleri kullanılamaz.',
                    ]],
                    ['heading' => '6. Yapay zeka özellikleri', 'body' => [
                        'Yapay zeka özellikleri (tedavi planı taslağı, sohbet tabanlı yapay zeka asistanı, sesten metne çeviri, röntgen okuma ve tahlil sonucu analizi) OpenAI\'ın API hizmetini (OpenAI, L.L.C., ABD) kullanır. Yalnızca işlem için gerekli içerik — vaka açıklaması, metin, görüntü veya tahlil değerleri — gönderilir; hastanın iletişim bilgileri gönderilmez. OpenAI\'ın API şartlarına göre API üzerinden gönderilen veriler modellerini eğitmek için kullanılmaz.',
                        'Yapay zeka çıktısı, lisanslı bir sağlık profesyonelinin değerlendirmesi gereken bir öneridir; hastaya hiçbir zaman otomatik olarak uygulanmaz. Hiçbir kişi hakkında hukuki veya benzer derecede önemli sonuç doğuran bir karar yalnızca otomatik işleme ile verilmez.',
                    ]],
                    ['heading' => '7. Verilerin aktarıldığı taraflar', 'body' => [
                        'Kişisel verileri satmayız ve hasta verilerini reklam amacıyla kullanmayız. Veriler yalnızca aşağıdaki taraflarla ve her biri kendi amacıyla sınırlı olarak paylaşılır:',
                        'Uygulamayı, veritabanını, dosyaları ve yedekleri bizim adımıza barındıran barındırma ve altyapı sağlayıcıları.',
                        'İleti Merkezi (Türkiye) — giriş kodları ve randevu SMS\'lerinin iletilmesi; yurt içi aktarım.',
                        'E-posta gönderim sağlayıcısı — giriş kodları, bildirimler, hatırlatmalar ve destek mesajları.',
                        'OpenAI (ABD) — yalnızca yapay zeka özellikleri için ve yalnızca hastanın açık rızası kaydedildikten sonra.',
                        'Klinik\'in kendi hesaplarıyla etkinleştirmeyi seçtiği entegrasyonlar: Meta WhatsApp Business Platformu (WhatsApp mesajları ve belgeler), Zoho CRM (hasta adı, telefonu ve e-postası CRM kişisi olarak aktarılır), Klinik\'in kendi telefon/santral sağlayıcısı (çağrı kayıtları) ve Klinik\'in API anahtarlarıyla bağladığı diğer sistemler. Bunlar veri sorumlusu olarak Klinik tarafından etkinleştirilir; bu sağlayıcılarla sözleşme ilişkisinden Klinik sorumludur.',
                        'Kanunen gerekli olduğunda yetkili kamu kurum ve kuruluşları ile mahkemeler.',
                    ]],
                    ['heading' => '8. Yurt dışına aktarım', 'body' => [
                        'Yurt dışına aktarımlar (OpenAI ve Klinik etkinleştirirse Meta veya Zoho) KVKK m.9\'a uygun olarak — standart sözleşme gibi uygun güvencelere ya da yapay zekaya gönderilen hasta sağlık verileri için hastanın açık rızasına dayanılarak — gerçekleştirilir. İleti Merkezi üzerinden SMS gönderimi Türkiye sınırları içinde kalır.',
                    ]],
                    ['heading' => '9. Paylaşılan bağlantılar, anketler ve online randevu', 'body' => [
                        'Klinik bir belgeyi (reçete, plan, fatura, rapor) hastaya WhatsApp ile gönderdiğinde PDF gizli depolamada tutulur ve tahmin edilemeyen, 30 gün sonra geçerliliğini yitiren bir bağlantıyla erişilir. Memnuniyet anketi bağlantıları benzersiz ve rastgele bir anahtar içerir. Online randevu sayfası, randevu oluşturulmadan önce hastadan tek kullanımlık bir SMS kodu ister.',
                    ]],
                    ['heading' => '10. Çerezler ve tarayıcı depolaması', 'body' => [
                        'Yalnızca zorunlu çerezler kullanılır (yönetim paneli oturumu ve CSRF koruması). Web uygulaması, kullanıcının oturum anahtarını ve tercihlerini (dil, açık/koyu tema, seçili uzmanlık ve şube) tarayıcının yerel depolamasında (localStorage) tutar. Reklam veya siteler arası takip çerezi kullanılmaz. Çıkış yapıldığında oturum anahtarı tarayıcıdan silinir.',
                    ]],
                    ['heading' => '11. Veri güvenliği', 'body' => [
                        'Şifreli bağlantı (HTTPS); şifre + tek kullanımlık kod ile giriş; hesap başına tek aktif oturum ve süreli oturumlar; rol ve yetki bazlı erişim; hekimlerin yalnızca kendi hastalarına erişimi; Klinikler arasında kesin veri ayrımı; röntgen, imza ve klinik dosyaların gizli depolamada tutulup yalnızca kısa süreli imzalı bağlantılarla sunulması; kaba kuvvet denemelerine karşı istek sınırlama; kapsamlı işlem kaydı; düzenli yedekleme ve personelin gizlilik yükümlülüğü. Hiçbir sistem tamamen güvenli değildir; Klinik verilerini etkileyen bir veri ihlali olması hâlinde, Klinik\'in Kurul\'a 72 saat içinde bildirim yükümlülüğünü yerine getirebilmesi için Klinik\'i gecikmeksizin bilgilendiririz.',
                    ]],
                    ['heading' => '12. Saklama süreleri', 'body' => [
                        'Tek kullanımlık giriş ve randevu kodları: 1 gün sonra silinir.',
                        'Bağlantıyla paylaşılan belgeler: 30 gün sonra silinir.',
                        'Hasta ve klinik verileri: Klinik\'in aboneliği aktif olduğu sürece saklanır. Aboneliği 365 günden uzun süredir sona ermiş Kliniklerin hasta kayıtları anonim hâle getirilir (ad ve iletişim bilgileri maskelenir, röntgen ve imza dosyaları kalıcı olarak silinir).',
                        'Mali ve fatura kayıtları: vergi ve ticaret mevzuatının öngördüğü süreler boyunca (genellikle 5 ila 10 yıl) saklanır; hasta anonimleştirildiğinde hastanın kimliğiyle bağı kesilir.',
                        'İşlem kaydı ve kullanım şartlarının kabulüne ilişkin kayıtlar: güvenlik olaylarının incelenmesi için gerekli olduğu süre ve ilgili kanuni zamanaşımı süreleri boyunca delil amacıyla saklanır.',
                        'Yedekler: otomatik olarak döndürülür ve en fazla 2 yıl saklanır.',
                        'Talep verileri: talebin yanıtlanması ve takibi için gerekli süre boyunca saklanır. Klinik, yasal saklama yükümlülükleri saklı kalmak kaydıyla, bir hastanın verilerinin derhâl silinmesini veya dışa aktarılmasını Platform üzerinden her zaman talep edebilir.',
                    ]],
                    ['heading' => '13. Haklarınız (KVKK m.11)', 'body' => [
                        'Kişisel verilerinizin işlenip işlenmediğini öğrenme; işlenmişse buna ilişkin bilgi talep etme; işlenme amacını ve amacına uygun kullanılıp kullanılmadığını öğrenme; yurt içinde veya yurt dışında aktarıldığı üçüncü kişileri bilme; eksik veya yanlış işlenmişse düzeltilmesini isteme; m.7 kapsamında silinmesini veya yok edilmesini isteme; düzeltme ve silme işlemlerinin aktarıldığı üçüncü kişilere bildirilmesini isteme; münhasıran otomatik sistemlerle analiz edilmesi sonucu aleyhinize bir sonuç çıkmasına itiraz etme ve kanuna aykırı işleme nedeniyle zarara uğramanız hâlinde zararın giderilmesini talep etme haklarına sahipsiniz.',
                        'Hastalar: başvurunuzu kayıtlarınızı tutan Klinik\'e iletiniz; veri sorumlusu Klinik\'tir. Diğer ilgili kişiler: bize kayıtlı e-posta adresinizden {privacy_email} adresine yazabilir veya kimlik bilgileriniz ve talebinizin konusunu içeren yazılı bir dilekçeyi adresimize ya da kayıtlı elektronik posta (KEP) adresimize gönderebilirsiniz (Veri Sorumlusuna Başvuru Usul ve Esasları Hakkında Tebliğ). Başvurular en geç 30 gün içinde ve kural olarak ücretsiz yanıtlanır; işlemin ayrıca bir maliyet gerektirmesi hâlinde Kurul\'un belirlediği tarifedeki ücret alınabilir. Kişisel Verileri Koruma Kurulu\'na şikâyette bulunma hakkınız saklıdır.',
                    ]],
                    ['heading' => '14. Çocuklara ait veriler', 'body' => [
                        'Bazı ürünler (ör. Pediavaria) reşit olmayanların kayıtlarını tutmak için kullanılır. Bu veriler Klinik tarafından kendi hukuki sebebine dayanarak ve gerektiğinde veli veya yasal temsilcinin rızasıyla girilir. İnternet sitesi üzerinden çocuklardan doğrudan bilerek veri toplamayız.',
                    ]],
                    ['heading' => '15. Değişiklikler', 'body' => [
                        'Bu metni güncelleyebiliriz. Sayfanın başındaki "Son güncelleme" tarihi geçerli sürümü gösterir; önemli değişiklikler ayrıca uygulama içinde duyurulur.',
                    ]],
                    ['heading' => '16. İletişim', 'body' => [
                        'Kişisel veri başvuruları: {privacy_email}. Genel sorular: {email}.',
                    ]],
                ],
            ],

            'ar' => [
                'title' => 'سياسة الخصوصية',
                'updated_label' => 'آخر تحديث',
                'updated_date' => '2 أكتوبر 2026',
                'intro' => 'Doctovaria منصة سحابية لإدارة العيادات تتكوّن من منتجات تخصصية — Dentavaria (طب الأسنان)، وGynevaria (أمراض النساء والتوليد)، وMedivaria (الأمراض الباطنية)، وOrthovaria (العظام)، وEstevaria (الطب التجميلي)، وDietavaria (التغذية)، وPediavaria (طب الأطفال)، وPhysiovaria (العلاج الطبيعي)، وHemavaria (أمراض الدم)، وSurgivaria (الجراحة العامة)، وGenervaria (الطب العام) — إلى جانب هذا الموقع وتطبيق الويب والجوال وصفحات الحجز الإلكتروني وواجهة API (ويُشار إليها جميعًا بـ"المنصة"). صدرت هذه السياسة وفقًا للقانون التركي رقم 6698 لحماية البيانات الشخصية ("KVKK")، ووفقًا للائحة الأوروبية العامة لحماية البيانات (GDPR) حيثما انطبقت، وهي توضح البيانات الشخصية التي تُعالَج عبر المنصة، ولأي غرض، وعلى أي أساس قانوني، ومع من تُشارَك، ومدة الاحتفاظ بها، وكيف يمكنك ممارسة حقوقك.',
                'sections' => [
                    ['heading' => '1. من نحن', 'body' => [
                        'تُشغَّل المنصة من قِبل {entity} ("Doctovaria" أو "نحن").',
                    ]],
                    ['heading' => '2. دورانا: مسؤول البيانات ومعالج البيانات', 'body' => [
                        'بصفتنا مسؤولًا عن البيانات: بالنسبة للبيانات الشخصية لزوار الموقع، ومن يرسلون إلينا استفسارات، ومستخدمي العيادات من عملائنا (بيانات الحساب وتسجيل الدخول والأمان والفوترة)، تكون Doctovaria هي مسؤول البيانات.',
                        'بصفتنا معالجًا للبيانات: بالنسبة لبيانات المرضى التي تسجّلها عيادة أو مركز صحي ("العيادة") على المنصة، تكون العيادة هي مسؤول البيانات، وتعالج Doctovaria هذه البيانات نيابةً عن العيادة وبناءً على تعليماتها فقط. تتحمل العيادة مسؤولية إعلام مرضاها، والحصول على أي موافقة لازمة، والرد على طلباتهم. على المرضى التوجه إلى عيادتهم أولًا، وإذا وصلنا طلب منهم نحيله إلى العيادة دون تأخير.',
                    ]],
                    ['heading' => '3. البيانات الشخصية التي نعالجها', 'body' => [
                        'زوار الموقع والعملاء المحتملون: الاسم، والبريد الإلكتروني، ورقم الهاتف، واسم العيادة أو الشركة، والتخصص المهتم به، ومحتوى رسائل نموذجي "تواصل معنا" و"اطلب عرض سعر".',
                        'مستخدمو العيادات (الأطباء والمساعدون وموظفو الاستقبال والمحاسبون والمديرون): الاسم، ورقم الجوال، والبريد الإلكتروني، والدور والصلاحيات، والتخصص، والفرع، وكلمة المرور (تُخزَّن فقط كقيمة مُجزَّأة لا يمكن عكسها)، ورموز الدخول لمرة واحدة، وأوقات الدخول، ورموز الجلسات، وبيانات الجهاز/المتصفح وعنوان IP لكل جلسة، وصورتا التوقيع والختم اللتان يرفعهما الطبيب للوصفات والمستندات، وكذلك — إذا استخدمت العيادة وحدة الرواتب — سجلات الراتب والعمولة ودفعات الرواتب والسُّلف.',
                        'بيانات هوية المريض والتواصل معه (يُدخلها موظفو العيادة أو المريض نفسه عبر صفحة الحجز الإلكتروني للعيادة): الاسم، والهاتف، والبريد الإلكتروني، والجنس، وتاريخ الميلاد، والعنوان، واللغة المفضلة، والطبيب المسؤول، والملاحظات.',
                        'البيانات الصحية للمريض (بيانات ذات طبيعة خاصة): المواعيد والزيارات، وسجلات الفحص والعلاج، ومخططات الأسنان وقوائم الإجراءات، والسجلات السريرية الخاصة بكل تخصص (مثل العلامات الحيوية، وسجلات النمو والتطعيم، وفحوص الموجات فوق الصوتية، وتعداد الدم ونقل الدم، والعمليات والمتابعات، وجلسات العلاج الطبيعي، وقياسات الجسم وخطط التغذية)، وخطط الرعاية، والوصفات الطبية، وحالات المختبر ونتائج التحاليل، وصور الأشعة وفحوص DICOM/CBCT، والملفات السريرية المرفوعة، ونماذج الموافقة الموقّعة (بما فيها صورة التوقيع بخط اليد).',
                        'البيانات المالية للمريض: تكاليف العلاج وقوائم الأسعار، والمدفوعات، والفواتير، وسجلات الحساب الجاري، ومبيعات المنتجات.',
                        'بيانات التواصل: تذكيرات المواعيد، ورسائل الاستدعاء للمتابعة، واستبيانات الرضا وإجاباتها، ورسائل واتساب/الرسائل النصية/البريد الإلكتروني المرسلة عبر المنصة، والمستندات المشارَكة عبر روابط، وسجلات المكالمات (رقم المتصل والوقت والمدة) إذا ربطت العيادة نظام الهاتف الخاص بها.',
                        'بيانات الذكاء الاصطناعي: أوصاف الحالات التي يكتبها موظفو العيادة أو يُملونها صوتيًا، والتسجيلات الصوتية المرسلة للتفريغ النصي، ومحادثات الذكاء الاصطناعي، وصور الأشعة أو نتائج التحاليل المرسلة للتحليل، والاقتراحات الناتجة، وعدد رموز (tokens) الذكاء الاصطناعي المستهلكة.',
                        'بيانات الأمان والتدقيق: سجل نشاط يبيّن من عرض أو أنشأ أو عدّل أو حذف أو صدّر أي سجل ومتى، مع عنوان IP ومعلومات المتصفح؛ ومحاولات الدخول الفاشلة؛ وسجلات الخادم التقنية.',
                        'بيانات الدعم: محتوى طلبات المساعدة والدعم التي ترسلها إلينا.',
                    ]],
                    ['heading' => '4. أغراض المعالجة', 'body' => [
                        'تقديم المنصة وكل ميزاتها؛ وإنشاء الحسابات وتأمينها والتحقق من الهوية برموز لمرة واحدة؛ وجدولة المواعيد وإرسال التذكيرات؛ وحفظ السجلات السريرية والمالية والمخزنية نيابةً عن العيادات؛ وإنشاء المستندات والوصفات والتقارير؛ وتقديم ميزات الذكاء الاصطناعي عندما تختار العيادة استخدامها؛ وتطبيق حدود الاشتراك وقاعدة "كل حساب لشخص واحد"؛ والاحتفاظ بسجل تدقيق واكتشاف إساءة الاستخدام والوصول غير المصرّح به ومشاركة الحسابات؛ وتقديم الدعم؛ وفوترة الاشتراكات؛ والرد على الاستفسارات؛ والنسخ الاحتياطي والتعافي من الحوادث؛ والامتثال للالتزامات القانونية وطلبات الجهات المختصة.',
                    ]],
                    ['heading' => '5. الأسس القانونية (المادتان 5 و6 من KVKK)', 'body' => [
                        'بيانات الموقع والاستفسارات: المادة 5/2(c) — الضرورة لإبرام عقد أو تنفيذه؛ والمادة 5/2(f) — مصلحتنا المشروعة في الرد على الاستفسارات.',
                        'بيانات مستخدمي العيادات: المادة 5/2(c) — تنفيذ عقد الاشتراك؛ والمادة 5/2(ç) — التزاماتنا القانونية (مثل أمن البيانات بموجب المادة 12، والسجلات الضريبية)؛ والمادة 5/2(e) — إنشاء حق أو ممارسته أو حمايته (بما في ذلك سجل النشاط وسجل من قبِل هذه الشروط)؛ والمادة 5/2(f) — مصلحتنا المشروعة في تأمين المنصة ومنع مشاركة الحسابات.',
                        'بيانات المرضى: تُعالَج استنادًا إلى الأساس القانوني للعيادة بصفتها مسؤول البيانات — عادةً المادة 6/3 للبيانات الصحية (المعالجة من قِبل أشخاص ملزمين بالسرية لأغراض التشخيص الطبي والعلاج وخدمات الرعاية وتخطيط الخدمات الصحية وإدارتها)، والمادة 5/2(c) و(ç) لبيانات الهوية والتواصل والبيانات المالية.',
                        'الموافقة الصريحة: مطلوبة قبل إرسال البيانات الصحية للمريض إلى مزوّد ذكاء اصطناعي خارج تركيا. تفرض المنصة ذلك تقنيًا: لا يمكن استخدام ميزات الذكاء الاصطناعي لمريض ما لم تسجّل العيادة موافقته عبر نموذج موافقة KVKK.',
                    ]],
                    ['heading' => '6. ميزات الذكاء الاصطناعي', 'body' => [
                        'تستخدم ميزات الذكاء الاصطناعي (صياغة خطط العلاج، والمساعد الحواري، وتحويل الصوت إلى نص، وقراءة صور الأشعة، وتحليل نتائج التحاليل) واجهة API الخاصة بشركة OpenAI (OpenAI, L.L.C.، الولايات المتحدة). يُرسَل فقط المحتوى اللازم للمهمة — وصف الحالة أو النص أو الصورة أو قيم التحاليل — دون بيانات التواصل الخاصة بالمريض. ووفقًا لشروط OpenAI، لا تُستخدم البيانات المرسلة عبر الـAPI لتدريب نماذجها.',
                        'مخرجات الذكاء الاصطناعي اقتراح يجب أن يراجعه أخصائي صحي مرخّص، ولا تُطبَّق على المريض تلقائيًا أبدًا. لا يُتخذ أي قرار ذي أثر قانوني أو أثر مهم مماثل بشأن أي شخص بالاعتماد على المعالجة الآلية وحدها.',
                    ]],
                    ['heading' => '7. الجهات التي نشارك معها البيانات', 'body' => [
                        'لا نبيع البيانات الشخصية ولا نستخدم بيانات المرضى لأغراض إعلانية. تُشارَك البيانات فقط مع الجهات التالية، وكلٌّ منها في حدود غرضه:',
                        'مزوّدو الاستضافة والبنية التحتية الذين يستضيفون التطبيق وقاعدة البيانات والملفات والنسخ الاحتياطية نيابةً عنا.',
                        'İleti Merkezi (تركيا) — إرسال رموز الدخول ورسائل المواعيد النصية؛ نقل داخلي.',
                        'مزوّد خدمة البريد الإلكتروني — رموز الدخول والإشعارات والتذكيرات ورسائل الدعم.',
                        'OpenAI (الولايات المتحدة) — لميزات الذكاء الاصطناعي فقط، وبعد تسجيل الموافقة الصريحة للمريض فقط.',
                        'التكاملات التي تختار العيادة تفعيلها بحساباتها الخاصة: منصة Meta WhatsApp Business (رسائل ومستندات واتساب)، وZoho CRM (يُرسَل اسم المريض وهاتفه وبريده كجهة اتصال في CRM)، ومزوّد الهاتف/المقسم الخاص بالعيادة (سجلات المكالمات)، وأي نظام تربطه العيادة عبر رموز الـAPI. تُفعِّل العيادة هذه التكاملات بصفتها مسؤول البيانات، وتتحمل مسؤولية عقودها مع هؤلاء المزوّدين.',
                        'الجهات العامة والمحاكم المختصة عندما يقتضي القانون ذلك.',
                    ]],
                    ['heading' => '8. النقل الدولي للبيانات', 'body' => [
                        'تتم عمليات النقل إلى خارج تركيا (OpenAI، وكذلك Meta أو Zoho إذا فعّلتهما العيادة) وفقًا للمادة 9 من KVKK — بالاستناد إلى ضمانات مناسبة كالعقود النموذجية، أو بالنسبة للبيانات الصحية المرسلة إلى الذكاء الاصطناعي، إلى الموافقة الصريحة للمريض. تبقى الرسائل النصية المرسلة عبر İleti Merkezi داخل تركيا.',
                    ]],
                    ['heading' => '9. الروابط المشارَكة والاستبيانات والحجز الإلكتروني', 'body' => [
                        'عندما ترسل العيادة مستندًا (وصفة، خطة، فاتورة، تقرير) إلى المريض عبر واتساب، يُحفظ ملف PDF في تخزين خاص ويُفتح عبر رابط لا يمكن تخمينه تنتهي صلاحيته بعد 30 يومًا. تحتوي روابط استبيانات الرضا على رمز عشوائي فريد. وتطلب صفحة الحجز الإلكتروني من المريض رمزًا نصيًا لمرة واحدة قبل إنشاء الموعد.',
                    ]],
                    ['heading' => '10. ملفات تعريف الارتباط والتخزين في المتصفح', 'body' => [
                        'نستخدم فقط ملفات تعريف الارتباط الضرورية (جلسة لوحة الإدارة وحماية CSRF). يحتفظ تطبيق الويب برمز دخول المستخدم وتفضيلاته (اللغة، والوضع الفاتح/الداكن، والتخصص والفرع المختاران) في التخزين المحلي للمتصفح. لا نستخدم ملفات تعريف ارتباط إعلانية أو للتتبع عبر المواقع. يؤدي تسجيل الخروج إلى حذف رمز الدخول من المتصفح.',
                    ]],
                    ['heading' => '11. كيف نحمي البيانات', 'body' => [
                        'اتصالات مشفّرة (HTTPS)؛ ودخول بكلمة مرور ورمز لمرة واحدة؛ وجلسة نشطة واحدة فقط لكل حساب مع جلسات محدودة المدة؛ ووصول قائم على الأدوار والصلاحيات؛ وحصر وصول الطبيب بمرضاه؛ وفصل صارم بين بيانات العيادات؛ وحفظ صور الأشعة والتواقيع والملفات السريرية في تخزين خاص لا يُتاح إلا عبر روابط موقّعة قصيرة الأجل؛ وتحديد معدّل الطلبات ضد محاولات التخمين؛ وسجل نشاط شامل؛ ونسخ احتياطي منتظم؛ والتزامات السرية على موظفينا. لا يوجد نظام آمن تمامًا؛ وإذا وقع خرق يمسّ بيانات عيادة ما فإننا نُبلغها دون تأخير لتتمكن من الوفاء بالتزامها بإبلاغ هيئة حماية البيانات الشخصية خلال 72 ساعة.',
                    ]],
                    ['heading' => '12. مدة الاحتفاظ بالبيانات', 'body' => [
                        'رموز الدخول والحجز لمرة واحدة: تُحذف بعد يوم واحد.',
                        'المستندات المشارَكة عبر روابط: تُحذف بعد 30 يومًا.',
                        'بيانات المرضى والعيادات: تُحفظ طوال مدة اشتراك العيادة النشط. وإذا انقضى على انتهاء الاشتراك أكثر من 365 يومًا، تُجعل سجلات المرضى مجهولة الهوية (إخفاء الأسماء وبيانات التواصل، وحذف ملفات الأشعة والتواقيع نهائيًا).',
                        'السجلات المالية والفواتير: تُحفظ للمدد التي تفرضها القوانين الضريبية والتجارية (عادةً من 5 إلى 10 سنوات)، وتُفصل عن هوية المريض عند جعله مجهول الهوية.',
                        'سجل النشاط وسجلات قبول الشروط: تُحفظ طوال المدة اللازمة للتحقيق في الحوادث الأمنية ولمدد التقادم القانونية المعمول بها، كدليل.',
                        'النسخ الاحتياطية: تُدوَّر تلقائيًا وتُحفظ لمدة أقصاها سنتان.',
                        'بيانات الاستفسارات: تُحفظ للمدة اللازمة للرد عليها ومتابعتها. ويمكن للعيادة في أي وقت طلب حذف بيانات مريض فورًا أو تصديرها عبر المنصة، مع مراعاة التزامات الحفظ القانونية.',
                    ]],
                    ['heading' => '13. حقوقك (المادة 11 من KVKK)', 'body' => [
                        'يحق لك معرفة ما إذا كانت بياناتك الشخصية تُعالَج؛ وطلب معلومات عنها؛ ومعرفة غرض المعالجة وما إذا كانت تُستخدم وفقًا له؛ ومعرفة الأطراف الثالثة داخل تركيا أو خارجها التي نُقلت إليها؛ وطلب تصحيح البيانات الناقصة أو غير الدقيقة؛ وطلب حذفها أو إتلافها وفق المادة 7؛ وطلب إبلاغ الأطراف الثالثة بالتصحيح أو الحذف؛ والاعتراض على أي نتيجة ضدك ناشئة حصريًا عن تحليل آلي؛ والمطالبة بالتعويض عن الضرر الناتج عن معالجة غير قانونية.',
                        'المرضى: يُرجى توجيه طلبكم إلى العيادة التي تحتفظ بسجلاتكم، فهي مسؤول البيانات. وغيرهم: يمكنكم الكتابة إلى {privacy_email} من عنوان البريد المسجل لدينا، أو إرسال طلب كتابي إلى عنواننا أو إلى عنوان البريد الإلكتروني المسجّل (KEP) يتضمن الاسم وبيانات الهوية وموضوع الطلب. نرد خلال 30 يومًا كحد أقصى ومجانًا، ما لم يتطلب الطلب تكلفة إضافية وفق تعرفة الهيئة. ويحق لكم تقديم شكوى إلى هيئة حماية البيانات الشخصية (Kişisel Verileri Koruma Kurulu).',
                    ]],
                    ['heading' => '14. بيانات الأطفال', 'body' => [
                        'تُستخدم بعض المنتجات (مثل Pediavaria) لحفظ سجلات القاصرين. تُدخل العيادة هذه البيانات استنادًا إلى أساسها القانوني، وبموافقة الوالد أو الولي القانوني عند الحاجة. ولا نجمع عن قصد بيانات مباشرة من الأطفال عبر الموقع.',
                    ]],
                    ['heading' => '15. التعديلات', 'body' => [
                        'قد نحدّث هذه السياسة. يبيّن تاريخ "آخر تحديث" أعلى الصفحة الإصدار الحالي، ويُعلَن عن التغييرات الجوهرية أيضًا داخل التطبيق.',
                    ]],
                    ['heading' => '16. التواصل', 'body' => [
                        'طلبات حماية البيانات: {privacy_email}. الاستفسارات العامة: {email}.',
                    ]],
                ],
            ],
        ];
    }

    private static function terms(): array
    {
        return [
            'en' => [
                'title' => 'Terms of Service',
                'updated_label' => 'Last updated',
                'updated_date' => '2 October 2026',
                'intro' => 'These Terms of Service ("Terms") govern access to and use of the Doctovaria platform — including all of its specialty products (Dentavaria, Gynevaria, Medivaria, Orthovaria, Estevaria, Dietavaria, Pediavaria, Physiovaria, Hemavaria, Surgivaria and Genervaria), this website, the web and mobile application, online booking pages, integrations and API (together, the "Platform") — operated by {entity} ("Doctovaria", "we", "us"). The Terms bind the clinic or healthcare provider that subscribes ("Clinic") and every individual who signs in to the Platform ("User"). Each User must personally accept these Terms in the application before using it; the Platform keeps a record of who accepted which version, when, and from which device and IP address.',
                'sections' => [
                    ['heading' => '1. Definitions', 'body' => [
                        'Clinic: the practice, clinic, polyclinic, hospital or other legal or natural person that subscribes to the Platform. Account administrator: the person(s) the Clinic authorizes to manage users, roles and settings. User: a natural person to whom an account has been issued. Patient: a person whose data the Clinic records. Patient data: all personal data of Patients recorded on the Platform. Subscription: the plan, specialty products, user seats, branch and AI limits and optional features agreed with the Clinic.',
                    ]],
                    ['heading' => '2. The Platform', 'body' => [
                        'Depending on the Subscription, the Platform provides: appointment scheduling and doctor working calendars; online patient booking; patient records with specialty-specific clinical records, odontograms and care plans; prescriptions, consent forms and printable documents bearing the doctor\'s signature and stamp; X-ray, DICOM/CBCT and laboratory modules; AI-assisted treatment planning, transcription and analysis; treatment pricing, billing, payments and invoices; accounting, cash, expenses, payroll and current accounts; inventory and product sales; multi-branch management; reminders, recalls, satisfaction surveys and WhatsApp/SMS/email messaging; reports; and integrations (API tokens, call webhook, CRM, WhatsApp Business). Features may be improved, changed or withdrawn over time; we will not materially reduce a paid feature during a paid period without notice.',
                    ]],
                    ['heading' => '3. Accounts and sign-in security', 'body' => [
                        'Accounts are created by Doctovaria or by the Clinic\'s account administrator. Sign-in requires the User\'s mobile number, password and a one-time code sent to that User\'s own phone or email. Each account can have only one active session at a time; signing in on a new device ends the previous session, and sessions expire after a set period.',
                        'Users must keep their password and one-time codes secret, must never disclose a code to anyone (Doctovaria staff will never ask for it), must lock or sign out of shared computers, and must notify the Clinic and us immediately of any suspected unauthorized use.',
                    ]],
                    ['heading' => '4. One person, one account — account sharing is prohibited', 'body' => [
                        'Every account is personal and belongs to one identified natural person. An account may only be used by the person in whose name it was created. Using one account for several people — including doctors who work on different days or shifts ("rotating" use of the same account), a doctor\'s account being used by an assistant or another doctor, or several staff members sharing one login — is strictly prohibited.',
                        'Every doctor who examines, treats or prescribes for patients through the Platform must have his or her own doctor account. A Clinic that has more working doctors or staff than its Subscription allows must purchase additional seats; it may not compensate by sharing accounts.',
                        'Everything done under an account — viewing records, creating or changing clinical records, prescriptions, documents, charges and payments — is recorded in the activity log with date, time, device and IP address, and is legally attributed to the account holder. Prescriptions and documents are issued with the account holder\'s name, signature and stamp. Allowing another person to use your account therefore means that person\'s clinical and legal acts will appear as yours, and you remain personally responsible for them, including in any professional, civil or criminal proceedings.',
                        'A doctor\'s signature and stamp images may only be uploaded by that doctor and may only appear on documents that doctor has personally issued.',
                        'To protect patients and the integrity of medical records, we may analyse usage signals (number of devices and IP addresses, sign-in times, activity outside the doctor\'s declared working schedule, simultaneous or overlapping activity) to detect shared accounts.',
                        'If account sharing is detected we may, at our discretion: warn the Clinic; require the creation of separate accounts and the purchase of the necessary seats, invoiced from the date the sharing began; sign out and suspend the accounts concerned; and, for repeated or serious breaches, terminate the Subscription for cause. The Clinic is liable for any damage arising from account sharing by its personnel.',
                    ]],
                    ['heading' => '5. Clinic and User obligations', 'body' => [
                        'The Clinic confirms that it is lawfully authorized to provide healthcare services and that its healthcare professionals hold the licences required for their acts. The Clinic is responsible for: creating an account for each person who uses the Platform; giving each User only the roles and permissions their job requires; promptly deactivating accounts of staff who leave; the accuracy of the data it enters; and the acts and omissions of its Users.',
                        'Users must use the Platform only for the Clinic\'s legitimate healthcare and business purposes, within their profession\'s rules and their duty of medical confidentiality.',
                    ]],
                    ['heading' => '6. Subscription, limits and fees', 'body' => [
                        'The Subscription is agreed with our sales team or set out in an order/offer. A Clinic may hold a subscription for one or more specialty products. The number of users (including separate limits for doctors and assistants), branches and AI tokens are limits pooled across the Clinic\'s active subscriptions. Additional AI tokens may be purchased as top-ups.',
                        'Fees are invoiced in advance for the agreed period, are exclusive of VAT and other taxes unless stated otherwise, and are non-refundable for partial periods unless agreed in writing. If payment is overdue we may, after notice, restrict or suspend access until payment. We may change prices for future periods with at least 30 days\' notice.',
                    ]],
                    ['heading' => '7. Patient data and data protection', 'body' => [
                        'With respect to Patient data the Clinic is the data controller and Doctovaria is the data processor. The Clinic is solely responsible for the lawfulness of the processing, including informing Patients (aydınlatma), obtaining required consents (the Platform provides consent-form templates and digital signature for this), and responding to Patients\' requests. Section 17 sets out our obligations as processor; our Privacy Policy describes the data and sub-processors in detail.',
                    ]],
                    ['heading' => '8. Clinical decision support and AI', 'body' => [
                        'The Platform, including its AI features, reminders, care-plan templates, price lists and reports, is a management and decision-support tool. It does not provide medical advice, diagnosis or treatment. AI output may be incomplete or wrong; it must be reviewed, corrected where necessary and approved by a licensed healthcare professional before being relied on. All clinical decisions, and their consequences, remain the sole responsibility of the treating healthcare professional and the Clinic.',
                        'AI features may only be used for a Patient after that Patient\'s explicit consent for the transfer has been recorded on the Platform. AI usage counts against the Subscription\'s token limit.',
                    ]],
                    ['heading' => '9. Documents, prescriptions and official systems', 'body' => [
                        'Prescriptions, reports, consent forms and other documents produced by the Platform are prepared under the Clinic\'s and the doctor\'s responsibility. The signature image placed on a document is not a qualified electronic signature under Turkish Electronic Signature Law No. 5070. The Platform is not integrated with official systems such as e-Nabız, MEDULA, e-Reçete or e-Fatura/e-Arşiv; invoices produced in the Platform are internal records, and the Clinic remains responsible for its obligations under health, tax and invoicing legislation.',
                    ]],
                    ['heading' => '10. Messaging and integrations', 'body' => [
                        'The Clinic is responsible for the content of messages it sends through the Platform and for having a lawful basis for each. Appointment reminders are informational; recall, campaign or other commercial messages require the recipient\'s prior consent and registration with the Message Management System (İYS) where Law No. 6563 applies.',
                        'When the Clinic connects its own WhatsApp Business, CRM, telephone or other accounts, or issues API tokens, it does so under its own contracts with those providers and is responsible for the data transferred to them and for keeping API tokens secret. API access, its documentation (published separately for each specialty product) and rate limits may be changed; abusive or excessive API use may be throttled or blocked.',
                    ]],
                    ['heading' => '11. Acceptable use', 'body' => [
                        'You may not: use the Platform unlawfully or to store data you are not entitled to process; attempt to access another Clinic\'s or another User\'s data; probe, scan or test vulnerabilities without our written permission; interfere with the Platform or overload it (including automated scraping outside the API); copy, modify, reverse-engineer, resell, sublicense or provide the Platform to third parties; upload malware; or circumvent seat, feature, consent or security controls.',
                    ]],
                    ['heading' => '12. Intellectual property', 'body' => [
                        'The Platform, its software, design, content and the Doctovaria and specialty product names and logos belong to Doctovaria or its licensors. The Clinic receives a non-exclusive, non-transferable right to use the Platform for the term of its Subscription. The Clinic retains all rights in the data it enters; we use it only to provide the Platform and as described in the Privacy Policy.',
                    ]],
                    ['heading' => '13. Availability, maintenance and backups', 'body' => [
                        'We aim for high availability but do not guarantee uninterrupted or error-free service unless a service level is agreed in writing. Planned maintenance will be scheduled to minimize disruption. We take regular backups; the Clinic remains responsible for keeping any copies it needs (e.g. printed or exported documents) for its own legal retention duties.',
                    ]],
                    ['heading' => '14. Suspension and termination', 'body' => [
                        'Either party may terminate the Subscription as agreed at signup or at the end of the paid period. We may suspend or terminate access immediately for serious breach of these Terms (including account sharing, unlawful use or non-payment after notice) or where required by law.',
                        'After termination the Clinic may request an export of its data. Patient data of a Clinic whose subscription has ended for more than 365 days is anonymized, and other data is deleted or retained as described in the Privacy Policy and as required by law.',
                    ]],
                    ['heading' => '15. Limitation of liability and indemnity', 'body' => [
                        'To the extent permitted by law, Doctovaria is not liable for indirect or consequential damages, loss of profit or data loss caused by the Clinic, nor for clinical decisions, third-party service outages or the content entered by Users. Our total liability for any claim relating to the Platform is limited to the fees paid by the Clinic in the 12 months preceding the claim. These limitations do not apply to liability for intent or gross negligence.',
                        'The Clinic will indemnify Doctovaria against claims by Patients, authorities or third parties arising from the Clinic\'s data, its clinical activities, its messages, its breach of these Terms, or the use of its accounts by persons other than the account holders.',
                    ]],
                    ['heading' => '16. Evidence, changes, governing law', 'body' => [
                        'Evidence agreement (Code of Civil Procedure Art. 193): the parties agree that Doctovaria\'s electronic records — including the activity log, login records and the record of acceptance of these Terms — constitute conclusive evidence in disputes, without prejudice to the right to prove otherwise.',
                        'We may amend these Terms. Material changes will be announced in the application; Users will be asked to accept the new version before continuing. These Terms are governed by the laws of the Republic of Türkiye, and the courts and enforcement offices of Türkiye have jurisdiction.',
                    ]],
                    ['heading' => '17. Data processing terms (KVKK Art. 12/2)', 'body' => [
                        'This section forms the data processing agreement between the Clinic (data controller) and Doctovaria (data processor) and applies automatically.',
                        'Doctovaria processes Patient data only on the Clinic\'s instructions, as expressed through the Clinic\'s use and configuration of the Platform, and only to provide the Platform; it does not use Patient data for its own purposes.',
                        'Doctovaria ensures that its personnel are bound by confidentiality, and takes the technical and administrative measures described in the Privacy Policy (encryption in transit, access control, tenant separation, private file storage, audit logging, backups).',
                        'Sub-processors: hosting/infrastructure providers; İleti Merkezi (SMS, Türkiye); an email delivery provider; OpenAI (AI features, United States — used only after the Patient\'s explicit consent is recorded). Integrations the Clinic enables with its own accounts (Meta WhatsApp, Zoho CRM, telephone providers, API clients) are the Clinic\'s own processors. We will notify Clinics in advance of any change to our sub-processors.',
                        'Doctovaria assists the Clinic in answering data-subject requests (the Platform provides patient data export and erasure), notifies the Clinic without undue delay of any personal data breach affecting its data so the Clinic can notify the Board within 72 hours, and, on termination, returns (export) and then deletes or anonymizes Patient data as set out in section 14, subject to legal retention duties.',
                    ]],
                    ['heading' => '18. Contact', 'body' => [
                        'Questions about these Terms: {email}. Data protection: {privacy_email}.',
                    ]],
                ],
            ],

            'tr' => [
                'title' => 'Kullanım Şartları',
                'updated_label' => 'Son güncelleme',
                'updated_date' => '2 Ekim 2026',
                'intro' => 'Bu Kullanım Şartları ("Şartlar"), {entity} ("Doctovaria", "biz") tarafından işletilen Doctovaria platformuna — tüm uzmanlık ürünleri (Dentavaria, Gynevaria, Medivaria, Orthovaria, Estevaria, Dietavaria, Pediavaria, Physiovaria, Hemavaria, Surgivaria ve Genervaria), bu internet sitesi, web ve mobil uygulama, online randevu sayfaları, entegrasyonlar ve API dahil ("Platform") — erişimi ve kullanımı düzenler. Şartlar, aboneliği alan klinik veya sağlık hizmet sunucusunu ("Klinik") ve Platform\'a giriş yapan her bireyi ("Kullanıcı") bağlar. Her Kullanıcı, Platform\'u kullanmadan önce bu Şartları uygulama içinde bizzat kabul etmek zorundadır; Platform, hangi sürümün kim tarafından, ne zaman, hangi cihaz ve IP adresinden kabul edildiğini kayıt altına alır.',
                'sections' => [
                    ['heading' => '1. Tanımlar', 'body' => [
                        'Klinik: Platform\'a abone olan muayenehane, klinik, poliklinik, hastane veya diğer gerçek ya da tüzel kişi. Hesap yöneticisi: Klinik\'in kullanıcıları, rolleri ve ayarları yönetmekle yetkilendirdiği kişi(ler). Kullanıcı: adına hesap açılmış gerçek kişi. Hasta: verileri Klinik tarafından kaydedilen kişi. Hasta verisi: Platform\'a kaydedilen Hastalara ait tüm kişisel veriler. Abonelik: Klinik ile kararlaştırılan paket, uzmanlık ürünleri, kullanıcı koltukları, şube ve yapay zeka limitleri ile isteğe bağlı özellikler.',
                    ]],
                    ['heading' => '2. Platform', 'body' => [
                        'Aboneliğe bağlı olarak Platform şunları sağlar: randevu planlama ve hekim çalışma takvimleri; online hasta randevusu; uzmanlığa özgü klinik kayıtlar, odontogram ve bakım planlarıyla hasta dosyaları; hekimin imza ve kaşesini taşıyan reçete, onam formu ve yazdırılabilir belgeler; röntgen, DICOM/CBCT ve laboratuvar modülleri; yapay zeka destekli tedavi planlama, sesten metne çeviri ve analiz; tedavi fiyatlandırma, borçlandırma, tahsilat ve faturalar; muhasebe, kasa, giderler, bordro ve cari hesaplar; stok ve ürün satışı; çoklu şube yönetimi; hatırlatma, kontrol çağrısı (recall), memnuniyet anketi ve WhatsApp/SMS/e-posta mesajlaşma; raporlar; entegrasyonlar (API anahtarları, çağrı webhook\'u, CRM, WhatsApp Business). Özellikler zamanla geliştirilebilir, değiştirilebilir veya kaldırılabilir; ücreti ödenmiş bir dönem içinde ücretli bir özelliği bildirim yapmadan önemli ölçüde kısıtlamayız.',
                    ]],
                    ['heading' => '3. Hesaplar ve giriş güvenliği', 'body' => [
                        'Hesaplar Doctovaria veya Klinik\'in hesap yöneticisi tarafından oluşturulur. Giriş için Kullanıcı\'nın cep telefonu numarası, şifresi ve Kullanıcı\'nın kendi telefonuna veya e-postasına gönderilen tek kullanımlık kod gerekir. Her hesapta aynı anda yalnızca bir aktif oturum bulunabilir; yeni bir cihazdan giriş yapılması önceki oturumu sonlandırır ve oturumlar belirli bir süre sonunda sona erer.',
                        'Kullanıcı; şifresini ve tek kullanımlık kodları gizli tutmak, kodu hiç kimseyle paylaşmamak (Doctovaria personeli kodu asla sormaz), ortak bilgisayarlarda ekranı kilitlemek veya çıkış yapmak ve şüpheli bir yetkisiz kullanımı derhâl Klinik\'e ve bize bildirmek zorundadır.',
                    ]],
                    ['heading' => '4. Her hesap tek kişiye aittir — hesap paylaşımı yasaktır', 'body' => [
                        'Her hesap kişiseldir ve kimliği belirli tek bir gerçek kişiye aittir. Bir hesap yalnızca adına açıldığı kişi tarafından kullanılabilir. Tek bir hesabın birden fazla kişi tarafından kullanılması — farklı günlerde veya vardiyalarda çalışan hekimlerin aynı hesabı dönüşümlü kullanması, bir hekimin hesabının asistan veya başka bir hekim tarafından kullanılması ya da birden fazla personelin tek bir girişi ortak kullanması dahil — kesinlikle yasaktır.',
                        'Platform üzerinden hasta muayene eden, tedavi eden veya reçete yazan her hekimin kendine ait bir hekim hesabı olmalıdır. Aboneliğinin izin verdiğinden daha fazla çalışan hekimi veya personeli olan Klinik, ek koltuk satın almak zorundadır; bunu hesap paylaşarak telafi edemez.',
                        'Bir hesap altında yapılan her işlem — kayıtların görüntülenmesi, klinik kayıt, reçete, belge, ücret ve ödeme oluşturulması veya değiştirilmesi — tarih, saat, cihaz ve IP adresiyle işlem kaydına yazılır ve hukuken hesap sahibine ait sayılır. Reçeteler ve belgeler hesap sahibinin adı, imzası ve kaşesiyle düzenlenir. Dolayısıyla hesabınızı başkasının kullanmasına izin vermeniz, o kişinin tıbbi ve hukuki işlemlerinin sizin işleminiz olarak görünmesi anlamına gelir ve bu işlemlerden — mesleki, hukuki ve cezai süreçler dahil — kişisel olarak siz sorumlu olursunuz.',
                        'Bir hekimin imza ve kaşe görselleri yalnızca o hekim tarafından yüklenebilir ve yalnızca o hekimin bizzat düzenlediği belgelerde yer alabilir.',
                        'Hastaları ve tıbbi kayıtların bütünlüğünü korumak amacıyla, paylaşılan hesapları tespit etmek için kullanım sinyallerini (cihaz ve IP adresi sayısı, giriş saatleri, hekimin tanımlı çalışma takvimi dışındaki faaliyetler, eş zamanlı veya çakışan faaliyetler) analiz edebiliriz.',
                        'Hesap paylaşımı tespit edilirse takdirimize bağlı olarak: Klinik\'i uyarabilir; ayrı hesaplar oluşturulmasını ve gerekli koltukların, paylaşımın başladığı tarihten itibaren faturalandırılmak üzere satın alınmasını isteyebilir; ilgili hesapların oturumlarını kapatıp askıya alabilir ve tekrarlanan veya ağır ihlallerde aboneliği haklı sebeple feshedebiliriz. Personelinin hesap paylaşımından doğan her türlü zarardan Klinik sorumludur.',
                    ]],
                    ['heading' => '5. Klinik ve Kullanıcı yükümlülükleri', 'body' => [
                        'Klinik, sağlık hizmeti sunmaya yasal olarak yetkili olduğunu ve sağlık meslek mensuplarının işlemleri için gerekli izin ve diplomalara sahip olduğunu beyan eder. Klinik; Platform\'u kullanan her kişi için ayrı hesap açmaktan, her Kullanıcı\'ya yalnızca görevinin gerektirdiği rol ve yetkileri vermekten, işten ayrılan personelin hesabını derhâl pasif hâle getirmekten, girdiği verilerin doğruluğundan ve Kullanıcılarının fiil ve ihmallerinden sorumludur.',
                        'Kullanıcılar Platform\'u yalnızca Klinik\'in meşru sağlık hizmeti ve işletme amaçları için, meslek kuralları ve hekimlik sır saklama yükümlülüğü çerçevesinde kullanmalıdır.',
                    ]],
                    ['heading' => '6. Abonelik, limitler ve ücretler', 'body' => [
                        'Abonelik satış ekibimizle kararlaştırılır veya bir sipariş/teklifte belirtilir. Bir Klinik bir veya birden fazla uzmanlık ürününe abone olabilir. Kullanıcı sayısı (hekim ve asistan için ayrı limitler dahil), şube sayısı ve yapay zeka token miktarı, Klinik\'in tüm aktif abonelikleri genelinde ortak limitlerdir. Ek yapay zeka token\'ı ayrıca satın alınabilir.',
                        'Ücretler kararlaştırılan dönem için peşin faturalandırılır; aksi belirtilmedikçe KDV ve diğer vergiler hariçtir ve yazılı olarak kararlaştırılmadıkça kısmi dönemler için iade edilmez. Ödemenin gecikmesi hâlinde, bildirimden sonra ödeme yapılana kadar erişimi kısıtlayabilir veya askıya alabiliriz. Gelecek dönemler için fiyatları en az 30 gün önceden bildirerek değiştirebiliriz.',
                    ]],
                    ['heading' => '7. Hasta verileri ve kişisel verilerin korunması', 'body' => [
                        'Hasta verileri bakımından Klinik veri sorumlusu, Doctovaria veri işleyendir. Hastaların aydınlatılması, gerekli rızaların alınması (Platform bunun için onam formu şablonları ve dijital imza sunar) ve hasta başvurularının yanıtlanması dahil, işlemenin hukuka uygunluğundan yalnızca Klinik sorumludur. Veri işleyen olarak yükümlülüklerimiz 17. maddede; veriler ve alt işleyenler ise Gizlilik Politikamızda ayrıntılı olarak açıklanmıştır.',
                    ]],
                    ['heading' => '8. Klinik karar desteği ve yapay zeka', 'body' => [
                        'Platform — yapay zeka özellikleri, hatırlatmalar, bakım planı şablonları, fiyat listeleri ve raporlar dahil — bir yönetim ve karar destek aracıdır; tıbbi tavsiye, teşhis veya tedavi sunmaz. Yapay zeka çıktısı eksik veya hatalı olabilir; dayanılmadan önce lisanslı bir sağlık profesyoneli tarafından incelenmeli, gerektiğinde düzeltilmeli ve onaylanmalıdır. Tüm klinik kararlar ve sonuçları yalnızca tedaviyi yapan sağlık profesyoneli ile Klinik\'in sorumluluğundadır.',
                        'Yapay zeka özellikleri bir Hasta için ancak o Hastanın aktarıma ilişkin açık rızası Platform\'a kaydedildikten sonra kullanılabilir. Yapay zeka kullanımı aboneliğin token limitinden düşülür.',
                    ]],
                    ['heading' => '9. Belgeler, reçeteler ve resmî sistemler', 'body' => [
                        'Platform\'un ürettiği reçete, rapor, onam formu ve diğer belgeler Klinik\'in ve hekimin sorumluluğunda hazırlanır. Belgeye eklenen imza görseli, 5070 sayılı Elektronik İmza Kanunu kapsamında nitelikli elektronik imza değildir. Platform; e-Nabız, MEDULA, e-Reçete veya e-Fatura/e-Arşiv gibi resmî sistemlerle entegre değildir; Platform\'da oluşturulan faturalar iç kayıt niteliğindedir ve sağlık, vergi ve fatura mevzuatından doğan yükümlülükler Klinik\'e aittir.',
                    ]],
                    ['heading' => '10. Mesajlaşma ve entegrasyonlar', 'body' => [
                        'Platform üzerinden gönderdiği mesajların içeriğinden ve her biri için hukuki dayanağa sahip olmaktan Klinik sorumludur. Randevu hatırlatmaları bilgilendirme amaçlıdır; kontrol çağrısı, kampanya veya diğer ticari elektronik iletiler, 6563 sayılı Kanun kapsamında alıcının önceden onayını ve İleti Yönetim Sistemi\'ne (İYS) kaydı gerektirir.',
                        'Klinik kendi WhatsApp Business, CRM, telefon veya diğer hesaplarını bağladığında ya da API anahtarı oluşturduğunda, bunu söz konusu sağlayıcılarla kendi sözleşmesi kapsamında yapar; onlara aktarılan verilerden ve API anahtarlarının gizli tutulmasından sorumludur. API erişimi, her uzmanlık ürünü için ayrı yayımlanan API dokümantasyonu ve istek limitleri değiştirilebilir; kötüye kullanılan veya aşırı API kullanımı sınırlandırılabilir veya engellenebilir.',
                    ]],
                    ['heading' => '11. Kabul edilebilir kullanım', 'body' => [
                        'Platform\'u hukuka aykırı amaçlarla veya işleme yetkiniz olmayan verileri saklamak için kullanamaz; başka bir Klinik\'in veya Kullanıcı\'nın verilerine erişmeye çalışamaz; yazılı iznimiz olmadan güvenlik açığı taraması veya testi yapamaz; Platform\'un işleyişini bozamaz veya aşırı yükleyemez (API dışında otomatik veri çekme dahil); Platform\'u kopyalayamaz, değiştiremez, tersine mühendislik uygulayamaz, yeniden satamaz, alt lisans veremez veya üçüncü kişilere sunamaz; zararlı yazılım yükleyemez; koltuk, özellik, rıza veya güvenlik kontrollerini atlatamazsınız.',
                    ]],
                    ['heading' => '12. Fikri mülkiyet', 'body' => [
                        'Platform, yazılımı, tasarımı, içeriği ile Doctovaria ve uzmanlık ürünlerinin adları ve logoları Doctovaria\'ya veya lisans verenlerine aittir. Klinik, abonelik süresince Platform\'u kullanmak için münhasır olmayan ve devredilemeyen bir kullanım hakkı elde eder. Klinik girdiği verilere ilişkin tüm haklarını korur; bu verileri yalnızca Platform\'u sunmak için ve Gizlilik Politikası\'nda açıklandığı şekilde kullanırız.',
                    ]],
                    ['heading' => '13. Erişilebilirlik, bakım ve yedekleme', 'body' => [
                        'Yüksek erişilebilirlik hedefleriz ancak yazılı olarak bir hizmet seviyesi kararlaştırılmadıkça kesintisiz veya hatasız hizmeti garanti etmeyiz. Planlı bakımlar kesintiyi en aza indirecek şekilde yapılır. Düzenli yedek alırız; Klinik kendi yasal saklama yükümlülükleri için ihtiyaç duyduğu kopyaları (ör. yazdırılmış veya dışa aktarılmış belgeler) saklamaktan sorumludur.',
                    ]],
                    ['heading' => '14. Askıya alma ve fesih', 'body' => [
                        'Taraflardan her biri aboneliği kayıt sırasında kararlaştırılan şekilde veya ödenmiş dönemin sonunda sona erdirebilir. Bu Şartların ağır ihlali (hesap paylaşımı, hukuka aykırı kullanım veya bildirime rağmen ödeme yapılmaması dahil) veya kanunun gerektirmesi hâlinde erişimi derhâl askıya alabilir veya sona erdirebiliriz.',
                        'Sona ermeden sonra Klinik verilerinin dışa aktarılmasını talep edebilir. Aboneliği 365 günden uzun süredir sona ermiş bir Klinik\'in hasta verileri anonim hâle getirilir; diğer veriler Gizlilik Politikası\'nda açıklandığı ve kanunun gerektirdiği şekilde silinir veya saklanır.',
                    ]],
                    ['heading' => '15. Sorumluluğun sınırlandırılması ve tazmin', 'body' => [
                        'Kanunun izin verdiği ölçüde Doctovaria; dolaylı zararlardan, kâr kaybından veya Klinik\'ten kaynaklanan veri kayıplarından, klinik kararlardan, üçüncü taraf hizmet kesintilerinden ve Kullanıcıların girdiği içerikten sorumlu değildir. Platform\'la ilgili herhangi bir talep için toplam sorumluluğumuz, Klinik\'in talepten önceki 12 ayda ödediği ücretlerle sınırlıdır. Bu sınırlamalar kast ve ağır kusurdan doğan sorumluluk için geçerli değildir.',
                        'Klinik; kendi verilerinden, klinik faaliyetlerinden, mesajlarından, bu Şartları ihlalinden veya hesaplarının hesap sahibi dışındaki kişilerce kullanılmasından kaynaklanan Hasta, resmî makam veya üçüncü kişi taleplerine karşı Doctovaria\'yı tazmin eder.',
                    ]],
                    ['heading' => '16. Delil sözleşmesi, değişiklikler, uygulanacak hukuk', 'body' => [
                        'Delil sözleşmesi (HMK m.193): Taraflar, işlem kaydı, giriş kayıtları ve bu Şartların kabulüne ilişkin kayıt dahil olmak üzere Doctovaria\'nın elektronik kayıtlarının uyuşmazlıklarda kesin delil teşkil edeceğini kabul eder; karşı delil getirme hakkı saklıdır.',
                        'Bu Şartları değiştirebiliriz. Önemli değişiklikler uygulama içinde duyurulur ve Kullanıcılardan devam etmeden önce yeni sürümü kabul etmeleri istenir. Bu Şartlar Türkiye Cumhuriyeti hukukuna tabidir; Türkiye mahkemeleri ve icra daireleri yetkilidir.',
                    ]],
                    ['heading' => '17. Veri işleme şartları (KVKK m.12/2)', 'body' => [
                        'Bu madde, Klinik (veri sorumlusu) ile Doctovaria (veri işleyen) arasındaki veri işleme sözleşmesini oluşturur ve kendiliğinden uygulanır.',
                        'Doctovaria, hasta verilerini yalnızca Klinik\'in Platform\'u kullanması ve yapılandırmasıyla ifade edilen talimatları doğrultusunda ve yalnızca Platform\'u sunmak için işler; hasta verilerini kendi amaçları için kullanmaz.',
                        'Doctovaria, personelinin gizlilik yükümlülüğüyle bağlı olmasını sağlar ve Gizlilik Politikası\'nda açıklanan teknik ve idari tedbirleri (iletimde şifreleme, erişim kontrolü, klinikler arası ayrım, gizli dosya depolama, işlem kaydı, yedekleme) alır.',
                        'Alt işleyenler: barındırma/altyapı sağlayıcıları; İleti Merkezi (SMS, Türkiye); bir e-posta gönderim sağlayıcısı; OpenAI (yapay zeka özellikleri, ABD — yalnızca Hastanın açık rızası kaydedildikten sonra). Klinik\'in kendi hesaplarıyla etkinleştirdiği entegrasyonlar (Meta WhatsApp, Zoho CRM, telefon sağlayıcıları, API istemcileri) Klinik\'in kendi veri işleyenleridir. Alt işleyenlerimizdeki değişiklikleri Kliniklere önceden bildiririz.',
                        'Doctovaria, ilgili kişi başvurularının yanıtlanmasında Klinik\'e destek olur (Platform hasta verisini dışa aktarma ve silme imkânı sunar); Klinik verilerini etkileyen bir veri ihlalini, Klinik\'in Kurul\'a 72 saat içinde bildirim yapabilmesi için gecikmeksizin Klinik\'e bildirir ve sözleşme sona erdiğinde hasta verilerini, yasal saklama yükümlülükleri saklı kalmak kaydıyla, 14. maddede belirtildiği şekilde iade eder (dışa aktarım) ve ardından siler veya anonim hâle getirir.',
                    ]],
                    ['heading' => '18. İletişim', 'body' => [
                        'Bu Şartlarla ilgili sorular: {email}. Kişisel verilerin korunması: {privacy_email}.',
                    ]],
                ],
            ],

            'ar' => [
                'title' => 'شروط الخدمة',
                'updated_label' => 'آخر تحديث',
                'updated_date' => '2 أكتوبر 2026',
                'intro' => 'تنظّم شروط الخدمة هذه ("الشروط") الوصول إلى منصة Doctovaria واستخدامها — بما في ذلك جميع منتجاتها التخصصية (Dentavaria وGynevaria وMedivaria وOrthovaria وEstevaria وDietavaria وPediavaria وPhysiovaria وHemavaria وSurgivaria وGenervaria)، وهذا الموقع، وتطبيق الويب والجوال، وصفحات الحجز الإلكتروني، والتكاملات، وواجهة API (ويُشار إليها معًا بـ"المنصة") — التي تُشغّلها {entity} ("Doctovaria" أو "نحن"). تُلزم هذه الشروط العيادة أو مقدّم الرعاية الصحية المشترك ("العيادة")، وكل فرد يسجّل الدخول إلى المنصة ("المستخدم"). يجب على كل مستخدم قبول هذه الشروط شخصيًا داخل التطبيق قبل استخدامه، وتحتفظ المنصة بسجل يبيّن من قبِل أي إصدار، ومتى، ومن أي جهاز وعنوان IP.',
                'sections' => [
                    ['heading' => '1. التعريفات', 'body' => [
                        'العيادة: العيادة الخاصة أو المركز الطبي أو المستشفى أو أي شخص طبيعي أو اعتباري آخر يشترك في المنصة. مدير الحساب: الشخص (أو الأشخاص) الذي تخوّله العيادة إدارة المستخدمين والأدوار والإعدادات. المستخدم: شخص طبيعي صدر باسمه حساب. المريض: شخص تسجّل العيادة بياناته. بيانات المرضى: جميع البيانات الشخصية للمرضى المسجلة على المنصة. الاشتراك: الباقة والمنتجات التخصصية ومقاعد المستخدمين وحدود الفروع والذكاء الاصطناعي والميزات الاختيارية المتفق عليها مع العيادة.',
                    ]],
                    ['heading' => '2. المنصة', 'body' => [
                        'بحسب الاشتراك، توفّر المنصة: جدولة المواعيد وتقويمات عمل الأطباء؛ والحجز الإلكتروني للمرضى؛ وملفات المرضى مع السجلات السريرية الخاصة بكل تخصص ومخططات الأسنان وخطط الرعاية؛ والوصفات ونماذج الموافقة والمستندات القابلة للطباعة التي تحمل توقيع الطبيب وختمه؛ ووحدات الأشعة وDICOM/CBCT والمختبر؛ وتخطيط العلاج والتفريغ النصي والتحليل بمساعدة الذكاء الاصطناعي؛ وتسعير العلاج والفوترة والمدفوعات والفواتير؛ والمحاسبة والصندوق والمصروفات والرواتب والحسابات الجارية؛ والمخزون ومبيعات المنتجات؛ وإدارة الفروع المتعددة؛ والتذكيرات والاستدعاءات واستبيانات الرضا والمراسلة عبر واتساب والرسائل النصية والبريد الإلكتروني؛ والتقارير؛ والتكاملات (رموز API، وwebhook المكالمات، وCRM، وWhatsApp Business). قد تُطوَّر الميزات أو تُعدَّل أو تُسحب مع الوقت، ولن نقلّص ميزة مدفوعة بشكل جوهري خلال فترة مدفوعة دون إشعار.',
                    ]],
                    ['heading' => '3. الحسابات وأمان الدخول', 'body' => [
                        'تُنشئ Doctovaria أو مدير حساب العيادة الحسابات. يتطلب الدخول رقم جوال المستخدم وكلمة مروره ورمزًا لمرة واحدة يُرسل إلى هاتف المستخدم نفسه أو بريده الإلكتروني. لا يمكن أن يكون لكل حساب سوى جلسة نشطة واحدة في الوقت نفسه؛ إذ يؤدي الدخول من جهاز جديد إلى إنهاء الجلسة السابقة، وتنتهي الجلسات بعد مدة محددة.',
                        'يجب على المستخدم الحفاظ على سرية كلمة المرور والرموز لمرة واحدة، وعدم إفشاء أي رمز لأي شخص (لن يطلبه منك موظفو Doctovaria أبدًا)، وقفل الحواسيب المشتركة أو تسجيل الخروج منها، وإبلاغ العيادة وإبلاغنا فورًا بأي استخدام غير مصرّح به يُشتبه فيه.',
                    ]],
                    ['heading' => '4. شخص واحد، حساب واحد — يُحظر تقاسم الحسابات', 'body' => [
                        'كل حساب شخصي ويعود إلى شخص طبيعي واحد محدد الهوية، ولا يجوز أن يستخدمه إلا الشخص الذي أُنشئ باسمه. يُحظر حظرًا تامًا استخدام حساب واحد من قِبل عدة أشخاص — بما في ذلك تناوب أطباء يعملون في أيام أو مناوبات مختلفة على الحساب نفسه، أو استخدام مساعد أو طبيب آخر لحساب طبيب، أو تقاسم عدة موظفين لبيانات دخول واحدة.',
                        'يجب أن يكون لكل طبيب يفحص المرضى أو يعالجهم أو يكتب لهم وصفات عبر المنصة حساب طبيب خاص به. وعلى العيادة التي لديها أطباء أو موظفون عاملون أكثر مما يسمح به اشتراكها أن تشتري مقاعد إضافية، ولا يجوز لها التعويض عن ذلك بتقاسم الحسابات.',
                        'كل ما يُنفَّذ تحت حساب ما — عرض السجلات، وإنشاء أو تعديل السجلات السريرية والوصفات والمستندات والتكاليف والمدفوعات — يُسجَّل في سجل النشاط مع التاريخ والوقت والجهاز وعنوان IP، ويُنسب قانونيًا إلى صاحب الحساب. وتصدر الوصفات والمستندات باسم صاحب الحساب وتوقيعه وختمه. لذلك فإن سماحك لشخص آخر باستخدام حسابك يعني أن أفعاله الطبية والقانونية ستظهر على أنها أفعالك، وتبقى أنت مسؤولًا عنها شخصيًا، بما في ذلك في أي إجراءات مهنية أو مدنية أو جزائية.',
                        'لا يجوز رفع صورتي توقيع الطبيب وختمه إلا من قِبل الطبيب نفسه، ولا يجوز أن تظهرا إلا على المستندات التي أصدرها ذلك الطبيب شخصيًا.',
                        'حمايةً للمرضى ولسلامة السجلات الطبية، يجوز لنا تحليل مؤشرات الاستخدام (عدد الأجهزة وعناوين IP، وأوقات الدخول، والنشاط خارج جدول عمل الطبيب المعلن، والنشاط المتزامن أو المتداخل) لاكتشاف الحسابات المشتركة.',
                        'إذا اكتُشف تقاسم للحسابات، يجوز لنا وفق تقديرنا: إنذار العيادة؛ وطلب إنشاء حسابات منفصلة وشراء المقاعد اللازمة مع فوترتها اعتبارًا من تاريخ بدء التقاسم؛ وإنهاء جلسات الحسابات المعنية وتعليقها؛ وفي حالات المخالفة المتكررة أو الجسيمة، إنهاء الاشتراك لسبب مشروع. وتتحمل العيادة المسؤولية عن أي ضرر ينشأ عن تقاسم موظفيها للحسابات.',
                    ]],
                    ['heading' => '5. التزامات العيادة والمستخدم', 'body' => [
                        'تُقرّ العيادة بأنها مخوّلة قانونًا بتقديم الخدمات الصحية، وبأن مهنييها الصحيين يحملون التراخيص اللازمة لأعمالهم. وتتحمل العيادة مسؤولية: إنشاء حساب لكل شخص يستخدم المنصة؛ ومنح كل مستخدم الأدوار والصلاحيات التي يتطلبها عمله فقط؛ وتعطيل حسابات الموظفين المغادرين فورًا؛ ودقة البيانات التي تُدخلها؛ وأفعال مستخدميها وتقصيرهم.',
                        'يجب على المستخدمين استخدام المنصة فقط للأغراض الصحية والتجارية المشروعة للعيادة، في إطار قواعد مهنتهم وواجب السرية الطبية.',
                    ]],
                    ['heading' => '6. الاشتراك والحدود والرسوم', 'body' => [
                        'يُتفق على الاشتراك مع فريق المبيعات أو يُحدَّد في طلب/عرض. يجوز للعيادة الاشتراك في منتج تخصصي واحد أو أكثر. وعدد المستخدمين (بما في ذلك حدود منفصلة للأطباء والمساعدين) والفروع ورموز الذكاء الاصطناعي حدود مشتركة على مستوى جميع اشتراكات العيادة النشطة. ويمكن شراء رموز ذكاء اصطناعي إضافية.',
                        'تُفوتر الرسوم مقدمًا عن الفترة المتفق عليها، ولا تشمل ضريبة القيمة المضافة والضرائب الأخرى ما لم يُذكر خلاف ذلك، ولا تُسترد عن الفترات الجزئية ما لم يُتفق كتابيًا. وفي حال تأخر الدفع، يجوز لنا بعد الإشعار تقييد الوصول أو تعليقه حتى السداد. ويجوز لنا تغيير الأسعار للفترات المقبلة بإشعار مسبق لا يقل عن 30 يومًا.',
                    ]],
                    ['heading' => '7. بيانات المرضى وحماية البيانات', 'body' => [
                        'فيما يخص بيانات المرضى، تكون العيادة مسؤول البيانات وDoctovaria معالج البيانات. وتتحمل العيادة وحدها مسؤولية مشروعية المعالجة، بما في ذلك إعلام المرضى، والحصول على الموافقات اللازمة (توفّر المنصة لذلك قوالب نماذج موافقة وتوقيعًا رقميًا)، والرد على طلبات المرضى. ويبيّن البند 17 التزاماتنا بصفتنا معالجًا، وتصف سياسة الخصوصية البيانات والمعالجين الفرعيين بالتفصيل.',
                    ]],
                    ['heading' => '8. دعم القرار السريري والذكاء الاصطناعي', 'body' => [
                        'المنصة — بما فيها ميزات الذكاء الاصطناعي والتذكيرات وقوالب خطط الرعاية وقوائم الأسعار والتقارير — أداة إدارة ودعم قرار، ولا تقدّم مشورة طبية أو تشخيصًا أو علاجًا. قد تكون مخرجات الذكاء الاصطناعي ناقصة أو خاطئة، ويجب أن يراجعها أخصائي صحي مرخّص ويصححها عند الحاجة ويعتمدها قبل الاعتماد عليها. وتبقى جميع القرارات السريرية ونتائجها مسؤولية الأخصائي الصحي المعالج والعيادة وحدهما.',
                        'لا يجوز استخدام ميزات الذكاء الاصطناعي لمريض إلا بعد تسجيل موافقته الصريحة على النقل في المنصة. ويُخصم استخدام الذكاء الاصطناعي من حد الرموز في الاشتراك.',
                    ]],
                    ['heading' => '9. المستندات والوصفات والأنظمة الرسمية', 'body' => [
                        'تُعدّ الوصفات والتقارير ونماذج الموافقة والمستندات الأخرى التي تنتجها المنصة تحت مسؤولية العيادة والطبيب. وصورة التوقيع الموضوعة على المستند ليست توقيعًا إلكترونيًا مؤهلًا بموجب قانون التوقيع الإلكتروني التركي رقم 5070. المنصة غير متكاملة مع الأنظمة الرسمية مثل e-Nabız وMEDULA وe-Reçete وe-Fatura/e-Arşiv؛ والفواتير الصادرة من المنصة سجلات داخلية، وتبقى العيادة مسؤولة عن التزاماتها بموجب التشريعات الصحية والضريبية وتشريعات الفوترة.',
                    ]],
                    ['heading' => '10. المراسلة والتكاملات', 'body' => [
                        'تتحمل العيادة مسؤولية محتوى الرسائل التي ترسلها عبر المنصة ووجود أساس قانوني لكل منها. تذكيرات المواعيد ذات طابع إعلامي؛ أما رسائل الاستدعاء أو الحملات أو الرسائل التجارية الأخرى فتتطلب موافقة مسبقة من المستلم والتسجيل في نظام إدارة الرسائل (İYS) حيثما ينطبق القانون رقم 6563.',
                        'عندما تربط العيادة حساباتها الخاصة في WhatsApp Business أو CRM أو الهاتف أو غيرها، أو تُصدر رموز API، فإنها تفعل ذلك بموجب عقودها الخاصة مع تلك الجهات، وتتحمل مسؤولية البيانات المنقولة إليها والحفاظ على سرية رموز API. ويجوز تغيير الوصول إلى الـAPI ووثائقه (المنشورة بشكل منفصل لكل منتج تخصصي) وحدود الطلبات، ويجوز تقييد أو حظر الاستخدام المسيء أو المفرط.',
                    ]],
                    ['heading' => '11. الاستخدام المقبول', 'body' => [
                        'لا يجوز لك: استخدام المنصة بشكل غير قانوني أو لتخزين بيانات لا يحق لك معالجتها؛ أو محاولة الوصول إلى بيانات عيادة أخرى أو مستخدم آخر؛ أو فحص الثغرات أو اختبارها دون إذن كتابي منا؛ أو تعطيل المنصة أو إثقالها (بما في ذلك الجمع الآلي للبيانات خارج الـAPI)؛ أو نسخ المنصة أو تعديلها أو الهندسة العكسية لها أو إعادة بيعها أو ترخيصها من الباطن أو تقديمها لأطراف ثالثة؛ أو رفع برمجيات خبيثة؛ أو التحايل على ضوابط المقاعد أو الميزات أو الموافقات أو الأمان.',
                    ]],
                    ['heading' => '12. الملكية الفكرية', 'body' => [
                        'المنصة وبرمجياتها وتصميمها ومحتواها وأسماء Doctovaria والمنتجات التخصصية وشعاراتها مملوكة لـDoctovaria أو للجهات المرخِّصة لها. وتحصل العيادة على حق غير حصري وغير قابل للتحويل لاستخدام المنصة طوال مدة اشتراكها. وتحتفظ العيادة بجميع الحقوق في البيانات التي تُدخلها، ولا نستخدمها إلا لتقديم المنصة وكما هو موضح في سياسة الخصوصية.',
                    ]],
                    ['heading' => '13. التوافر والصيانة والنسخ الاحتياطي', 'body' => [
                        'نسعى إلى توافر عالٍ، لكننا لا نضمن خدمة دون انقطاع أو أخطاء ما لم يُتفق كتابيًا على مستوى خدمة. تُجدول الصيانة المخطط لها بما يقلل الانقطاع. ونأخذ نسخًا احتياطية منتظمة، وتبقى العيادة مسؤولة عن الاحتفاظ بأي نسخ تحتاجها (كالمستندات المطبوعة أو المصدّرة) لالتزامات الحفظ القانونية الخاصة بها.',
                    ]],
                    ['heading' => '14. التعليق والإنهاء', 'body' => [
                        'يجوز لأي من الطرفين إنهاء الاشتراك وفق ما اتُّفق عليه عند التسجيل أو في نهاية الفترة المدفوعة. ويجوز لنا تعليق الوصول أو إنهاؤه فورًا عند الإخلال الجسيم بهذه الشروط (بما في ذلك تقاسم الحسابات أو الاستخدام غير القانوني أو عدم الدفع بعد الإشعار) أو عندما يقتضي القانون ذلك.',
                        'بعد الإنهاء، يجوز للعيادة طلب تصدير بياناتها. وتُجعل بيانات المرضى لعيادة انقضى على انتهاء اشتراكها أكثر من 365 يومًا مجهولة الهوية، وتُحذف البيانات الأخرى أو تُحفظ وفق ما تصفه سياسة الخصوصية ويقتضيه القانون.',
                    ]],
                    ['heading' => '15. حدود المسؤولية والتعويض', 'body' => [
                        'في الحدود التي يسمح بها القانون، لا تتحمل Doctovaria المسؤولية عن الأضرار غير المباشرة أو التبعية أو فوات الربح أو فقدان البيانات الناتج عن العيادة، ولا عن القرارات السريرية أو انقطاع خدمات الأطراف الثالثة أو المحتوى الذي يُدخله المستخدمون. وتقتصر مسؤوليتنا الإجمالية عن أي مطالبة تتعلق بالمنصة على الرسوم التي دفعتها العيادة خلال الأشهر الاثني عشر السابقة للمطالبة. ولا تسري هذه الحدود على المسؤولية الناشئة عن العمد أو الإهمال الجسيم.',
                        'تعوّض العيادة Doctovaria عن مطالبات المرضى أو الجهات الرسمية أو الأطراف الثالثة الناشئة عن بيانات العيادة أو أنشطتها السريرية أو رسائلها أو إخلالها بهذه الشروط أو استخدام حساباتها من قِبل أشخاص غير أصحابها.',
                    ]],
                    ['heading' => '16. اتفاق الإثبات والتعديلات والقانون الواجب التطبيق', 'body' => [
                        'اتفاق الإثبات (المادة 193 من قانون أصول المحاكمات المدنية التركي): يتفق الطرفان على أن السجلات الإلكترونية لـDoctovaria — بما فيها سجل النشاط وسجلات الدخول وسجل قبول هذه الشروط — تُعدّ دليلًا قاطعًا في النزاعات، مع الاحتفاظ بحق إثبات العكس.',
                        'يجوز لنا تعديل هذه الشروط. يُعلَن عن التغييرات الجوهرية داخل التطبيق، ويُطلب من المستخدمين قبول الإصدار الجديد قبل المتابعة. تخضع هذه الشروط لقوانين الجمهورية التركية، وتختص بها محاكم ودوائر التنفيذ في تركيا.',
                    ]],
                    ['heading' => '17. شروط معالجة البيانات (المادة 12/2 من KVKK)', 'body' => [
                        'يُشكّل هذا البند اتفاقية معالجة البيانات بين العيادة (مسؤول البيانات) وDoctovaria (معالج البيانات)، ويسري تلقائيًا.',
                        'تعالج Doctovaria بيانات المرضى فقط وفق تعليمات العيادة كما تتجلى في استخدامها للمنصة وإعداداتها، ولغرض تقديم المنصة فقط، ولا تستخدم بيانات المرضى لأغراضها الخاصة.',
                        'تضمن Doctovaria التزام موظفيها بالسرية، وتتخذ التدابير التقنية والإدارية الموضحة في سياسة الخصوصية (التشفير أثناء النقل، وضبط الوصول، والفصل بين العيادات، والتخزين الخاص للملفات، وسجل النشاط، والنسخ الاحتياطي).',
                        'المعالجون الفرعيون: مزوّدو الاستضافة والبنية التحتية؛ وİleti Merkezi (الرسائل النصية، تركيا)؛ ومزوّد خدمة بريد إلكتروني؛ وOpenAI (ميزات الذكاء الاصطناعي، الولايات المتحدة — فقط بعد تسجيل الموافقة الصريحة للمريض). أما التكاملات التي تفعّلها العيادة بحساباتها الخاصة (Meta WhatsApp وZoho CRM ومزوّدو الهاتف وعملاء الـAPI) فهي معالجون تابعون للعيادة نفسها. وسنُبلغ العيادات مسبقًا بأي تغيير في معالجينا الفرعيين.',
                        'تساعد Doctovaria العيادة في الرد على طلبات أصحاب البيانات (توفّر المنصة تصدير بيانات المريض وحذفها)، وتُبلغ العيادة دون تأخير بأي خرق يمسّ بياناتها لتتمكن من إبلاغ الهيئة خلال 72 ساعة، وعند انتهاء العقد تُعيد بيانات المرضى (عبر التصدير) ثم تحذفها أو تجعلها مجهولة الهوية وفق البند 14، مع مراعاة التزامات الحفظ القانونية.',
                    ]],
                    ['heading' => '18. التواصل', 'body' => [
                        'الأسئلة المتعلقة بهذه الشروط: {email}. حماية البيانات: {privacy_email}.',
                    ]],
                ],
            ],
        ];
    }
}
