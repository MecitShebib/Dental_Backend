<?php

namespace App\Support;

/**
 * Static copy behind the public /privacy-policy and /terms-of-service pages.
 * Kept as plain PHP (like ApiDocumentation) rather than admin-editable content,
 * since legal text needs a human review pass before it changes, not a form.
 */
class LegalContent
{
    public static function get(string $page, string $locale): array
    {
        $data = $page === 'terms' ? self::terms() : self::privacy();

        return $data[$locale] ?? $data['en'];
    }

    private static function privacy(): array
    {
        return [
            'en' => [
                'title' => 'Privacy Policy',
                'updated_label' => 'Last updated',
                'updated_date' => 'August 2026',
                'intro' => 'Dentavaria ("Dentavaria", "we", "us") provides a clinical operating system for dental practices, including a website, admin panel, and mobile/web application (together, the "Service"). This Privacy Policy explains what information we collect, how we use it, and the choices you have. It applies to visitors of this website and to dental practices ("Clinics") and their staff who use the Service.',
                'sections' => [
                    ['heading' => '1. Two kinds of data we handle', 'body' => [
                        'When you browse this website or submit an inquiry, you are interacting with us directly. When a Clinic uses the Service to manage patients, Dentavaria acts as a data processor on the Clinic\'s behalf: the Clinic decides what patient information to enter and remains the data controller responsible for that information, including obtaining any consent required from its patients. This policy describes both relationships.',
                    ]],
                    ['heading' => '2. Information we collect', 'body' => [
                        'From website visitors: name, email address, phone number, clinic/company name, and any message submitted through the Contact or Get a Quote forms.',
                        'From Clinic accounts: staff name, email, phone number, role, and login activity, used to authenticate access via one-time passcodes (OTP) or admin credentials.',
                        'From Clinics, on behalf of their patients: patient name, contact details, gender, date of birth, address, appointment and visit history, treatment records and odontogram charts, billing and payment records, X-ray images, and clinical notes entered by clinic staff.',
                        'AI treatment planning data: case descriptions typed or dictated by clinic staff, audio recordings made for transcription, and the treatment plans generated from them.',
                        'Technical data: IP address, browser type, device information, and basic usage logs collected automatically when you use the website or Service.',
                    ]],
                    ['heading' => '3. How we use information', 'body' => [
                        'We use this information to operate and maintain the Service; authenticate users and secure accounts; respond to inquiries and demo requests; generate AI-assisted treatment plans at a Clinic\'s request; send appointment-related SMS notifications and OTP codes; monitor and improve the Service\'s performance and reliability; and comply with legal obligations.',
                    ]],
                    ['heading' => '4. AI features and third-party processing', 'body' => [
                        'When a Clinic uses the AI treatment plan assistant, the case description (and, where applicable, a transcription of a voice recording) is sent to OpenAI, our AI processing provider, to generate a structured treatment plan and to transcribe audio. OpenAI processes this data under its own data processing terms and does not use it to train its models. We do not send full patient names or contact details to OpenAI as part of this process.',
                    ]],
                    ['heading' => '5. SMS delivery', 'body' => [
                        'To deliver one-time login codes and appointment reminders, we share the recipient\'s phone number and message content with our SMS delivery provider (Turkey SMS), solely for the purpose of sending that message.',
                    ]],
                    ['heading' => '6. Cookies and local storage', 'body' => [
                        'The website uses a small number of essential cookies to keep you signed in to the admin panel, and browser local storage to remember your light/dark theme preference. We do not use advertising or cross-site tracking cookies.',
                    ]],
                    ['heading' => '7. How we share information', 'body' => [
                        'We do not sell personal information. We share information only with: the AI and SMS processors described above; hosting and infrastructure providers who store data on our behalf; and authorities, where required by law or to protect the rights, safety, or property of Dentavaria, our customers, or others.',
                    ]],
                    ['heading' => '8. Data security', 'body' => [
                        'We use industry-standard measures to protect information, including encrypted connections (HTTPS), token-based authentication, and access controls that scope every Clinic\'s data to that Clinic only (multi-tenant isolation). No method of transmission or storage is completely secure, and we cannot guarantee absolute security.',
                    ]],
                    ['heading' => '9. Data retention', 'body' => [
                        'We retain account and patient data for as long as a Clinic\'s subscription is active, and for a reasonable period afterward to allow data export, comply with legal or accounting obligations, and resolve disputes. Clinics may request deletion of their data subject to these obligations.',
                    ]],
                    ['heading' => '10. International transfers', 'body' => [
                        'Our infrastructure and processors, including OpenAI, may process data outside of Türkiye. Where this occurs, we rely on the safeguards provided by those processors\' own compliance frameworks.',
                    ]],
                    ['heading' => '11. Your rights', 'body' => [
                        'Subject to applicable law (including the Turkish Personal Data Protection Law, KVKK, and, where relevant, the GDPR), you may have the right to access, correct, delete, or export your personal information, and to object to or restrict certain processing. Patients should direct these requests to their Clinic, which controls their records; website visitors and Clinic staff may contact us directly using the details below.',
                    ]],
                    ['heading' => '12. Children\'s data', 'body' => [
                        'The Service is intended for use by dental professionals. Patient records may include minors\' information entered by a Clinic acting under its own legal basis (such as parental consent obtained by the Clinic); we do not knowingly collect personal information directly from children through the website.',
                    ]],
                    ['heading' => '13. Changes to this policy', 'body' => [
                        'We may update this Privacy Policy from time to time. Material changes will be reflected by an updated "Last updated" date on this page.',
                    ]],
                    ['heading' => '14. Contact us', 'body' => [
                        'Questions about this policy can be sent to privacy@dentavaria.com or through the Contact form on this website.',
                    ]],
                ],
            ],
            'ar' => [
                'title' => 'سياسة الخصوصية',
                'updated_label' => 'آخر تحديث',
                'updated_date' => 'أغسطس 2026',
                'intro' => 'تقدّم Dentavaria ("Dentavaria" أو "نحن") نظام تشغيل سريري لعيادات طب الأسنان، يشمل موقعًا إلكترونيًا ولوحة تحكم إدارية وتطبيقًا للجوال والويب (يُشار إليها معًا بـ"الخدمة"). توضح سياسة الخصوصية هذه المعلومات التي نجمعها، وكيفية استخدامها، والخيارات المتاحة لك. وتنطبق على زوار هذا الموقع، وعلى عيادات الأسنان ("العيادات") وموظفيها الذين يستخدمون الخدمة.',
                'sections' => [
                    ['heading' => '1. نوعان من البيانات نتعامل معهما', 'body' => [
                        'عندما تتصفح هذا الموقع أو ترسل استفسارًا، فأنت تتواصل معنا مباشرة. أما عندما تستخدم عيادة ما الخدمة لإدارة مرضاها، فتعمل Dentavaria كجهة معالجة للبيانات نيابةً عن العيادة: العيادة هي من تقرر البيانات التي تُدخلها، وتبقى هي الجهة المتحكمة بالبيانات المسؤولة عنها، بما في ذلك الحصول على أي موافقة مطلوبة من مرضاها. تصف هذه السياسة كلتا العلاقتين.',
                    ]],
                    ['heading' => '2. المعلومات التي نجمعها', 'body' => [
                        'من زوار الموقع: الاسم، والبريد الإلكتروني، ورقم الهاتف، واسم العيادة أو الشركة، وأي رسالة تُرسَل عبر نموذج "تواصل معنا" أو "اطلب عرض سعر".',
                        'من حسابات العيادات: اسم الموظف وبريده الإلكتروني ورقم هاتفه ودوره الوظيفي وسجل الدخول، وتُستخدم هذه البيانات للتحقق من الهوية عبر رمز مرور لمرة واحدة (OTP) أو بيانات دخول الإدارة.',
                        'من العيادات، نيابةً عن مرضاها: اسم المريض وبيانات التواصل والجنس وتاريخ الميلاد والعنوان، وسجل المواعيد والزيارات، والسجلات العلاجية ومخططات الأسنان (Odontogram)، وسجلات الفوترة والدفع، وصور الأشعة، والملاحظات السريرية التي يُدخلها موظفو العيادة.',
                        'بيانات التخطيط العلاجي بالذكاء الاصطناعي: وصف الحالة الذي يكتبه أو يُمليه موظفو العيادة، والتسجيلات الصوتية المُعدّة للتفريغ النصي، وخطط العلاج الناتجة عنها.',
                        'بيانات تقنية: عنوان IP، ونوع المتصفح، ومعلومات الجهاز، وسجلات استخدام أساسية تُجمع تلقائيًا عند استخدامك للموقع أو الخدمة.',
                    ]],
                    ['heading' => '3. كيف نستخدم المعلومات', 'body' => [
                        'نستخدم هذه المعلومات من أجل تشغيل الخدمة وصيانتها، والتحقق من هوية المستخدمين وتأمين الحسابات، والرد على الاستفسارات وطلبات العروض التوضيحية، وإنشاء خطط علاج بمساعدة الذكاء الاصطناعي بناءً على طلب العيادة، وإرسال إشعارات المواعيد النصية ورموز التحقق OTP، ومراقبة أداء الخدمة وموثوقيتها وتحسينها، والامتثال للالتزامات القانونية.',
                    ]],
                    ['heading' => '4. ميزات الذكاء الاصطناعي والمعالجة عبر أطراف ثالثة', 'body' => [
                        'عند استخدام العيادة لمساعد خطة العلاج بالذكاء الاصطناعي، يُرسَل وصف الحالة (وكذلك تفريغ التسجيل الصوتي عند الحاجة) إلى OpenAI، مزوّد المعالجة بالذكاء الاصطناعي لدينا، لإنشاء خطة علاج منظمة ولتفريغ الصوت نصيًا. تعالج OpenAI هذه البيانات وفق شروط معالجة البيانات الخاصة بها، ولا تستخدمها لتدريب نماذجها. لا نُرسل الاسم الكامل للمريض أو بيانات التواصل الخاصة به إلى OpenAI كجزء من هذه العملية.',
                    ]],
                    ['heading' => '5. إرسال الرسائل النصية', 'body' => [
                        'لإرسال رموز الدخول لمرة واحدة وتذكيرات المواعيد، نشارك رقم هاتف المستلم ومحتوى الرسالة مع مزوّد خدمة الرسائل النصية لدينا (Turkey SMS)، لغرض إرسال تلك الرسالة فقط.',
                    ]],
                    ['heading' => '6. ملفات تعريف الارتباط والتخزين المحلي', 'body' => [
                        'يستخدم الموقع عددًا محدودًا من ملفات تعريف الارتباط الأساسية للحفاظ على تسجيل دخولك إلى لوحة التحكم الإدارية، ويستخدم التخزين المحلي للمتصفح لحفظ تفضيلك للوضع الفاتح أو الداكن. لا نستخدم ملفات تعريف ارتباط إعلانية أو تتبّع عبر مواقع أخرى.',
                    ]],
                    ['heading' => '7. كيف نشارك المعلومات', 'body' => [
                        'لا نبيع المعلومات الشخصية. نشارك المعلومات فقط مع معالجيّ الذكاء الاصطناعي والرسائل النصية المذكورين أعلاه، ومزودي الاستضافة والبنية التحتية الذين يخزّنون البيانات نيابةً عنا، والجهات الرسمية عند الاقتضاء بموجب القانون أو لحماية حقوق Dentavaria أو عملائنا أو غيرهم أو سلامتهم أو ممتلكاتهم.',
                    ]],
                    ['heading' => '8. أمن البيانات', 'body' => [
                        'نستخدم إجراءات معيارية في القطاع لحماية المعلومات، منها الاتصالات المشفّرة (HTTPS)، والتحقق القائم على الرموز (Tokens)، وضوابط وصول تحصر بيانات كل عيادة بها وحدها (عزل بين العيادات). لا توجد وسيلة نقل أو تخزين آمنة تمامًا، ولا يمكننا ضمان أمان مطلق.',
                    ]],
                    ['heading' => '9. مدة الاحتفاظ بالبيانات', 'body' => [
                        'نحتفظ ببيانات الحسابات والمرضى طوال فترة اشتراك العيادة النشط، ولفترة معقولة بعد ذلك للسماح بتصدير البيانات والامتثال للالتزامات القانونية أو المحاسبية وتسوية أي نزاعات. يمكن للعيادات طلب حذف بياناتها ضمن حدود هذه الالتزامات.',
                    ]],
                    ['heading' => '10. نقل البيانات دوليًا', 'body' => [
                        'قد تُعالج بنيتنا التحتية والجهات المعالِجة لدينا، بما فيها OpenAI، البيانات خارج تركيا. وفي هذه الحالة، نعتمد على الضمانات التي توفرها أطر الامتثال الخاصة بهذه الجهات.',
                    ]],
                    ['heading' => '11. حقوقك', 'body' => [
                        'وفقًا للقوانين المعمول بها (بما في ذلك قانون حماية البيانات الشخصية التركي KVKK، واللائحة العامة لحماية البيانات GDPR عند الانطباق)، قد يكون لديك الحق في الوصول إلى معلوماتك الشخصية أو تصحيحها أو حذفها أو تصديرها، وفي الاعتراض على بعض عمليات المعالجة أو تقييدها. يجب على المرضى توجيه هذه الطلبات إلى عيادتهم التي تتحكم في سجلاتهم؛ أما زوار الموقع وموظفو العيادات فيمكنهم التواصل معنا مباشرة عبر البيانات أدناه.',
                    ]],
                    ['heading' => '12. بيانات القُصّر', 'body' => [
                        'الخدمة مخصصة للاستخدام من قبل أخصائيي طب الأسنان. قد تتضمن سجلات المرضى بيانات قُصّر تُدخلها العيادة استنادًا إلى الأساس القانوني الخاص بها (كموافقة ولي الأمر التي تحصل عليها العيادة)؛ ولا نجمع عن قصد معلومات شخصية مباشرة من الأطفال عبر الموقع.',
                    ]],
                    ['heading' => '13. التعديلات على هذه السياسة', 'body' => [
                        'قد نقوم بتحديث سياسة الخصوصية هذه من وقت لآخر. وستنعكس أي تغييرات جوهرية من خلال تحديث تاريخ "آخر تحديث" الموضح في هذه الصفحة.',
                    ]],
                    ['heading' => '14. تواصل معنا', 'body' => [
                        'يمكن إرسال أي استفسارات حول هذه السياسة إلى privacy@dentavaria.com أو عبر نموذج "تواصل معنا" في هذا الموقع.',
                    ]],
                ],
            ],
            'tr' => [
                'title' => 'Gizlilik Politikası',
                'updated_label' => 'Son güncelleme',
                'updated_date' => 'Ağustos 2026',
                'intro' => 'Dentavaria ("Dentavaria", "biz"), diş kliniklerine yönelik bir klinik işletim sistemi sunar; buna bir web sitesi, yönetim paneli ve mobil/web uygulaması dahildir (birlikte "Hizmet" olarak anılır). Bu Gizlilik Politikası, hangi bilgileri topladığımızı, bunları nasıl kullandığımızı ve sahip olduğunuz seçenekleri açıklar. Bu politika, bu web sitesini ziyaret edenler ile Hizmet\'i kullanan diş klinikleri ("Klinikler") ve personeli için geçerlidir.',
                'sections' => [
                    ['heading' => '1. Ele aldığımız iki tür veri', 'body' => [
                        'Bu web sitesini ziyaret ettiğinizde veya bir talep gönderdiğinizde bizimle doğrudan etkileşime geçmiş olursunuz. Bir Klinik, hastalarını yönetmek için Hizmet\'i kullandığında ise Dentavaria, Klinik adına bir veri işleyen sıfatıyla hareket eder: hangi hasta bilgilerinin gireceğine Klinik karar verir ve bu bilgilerden sorumlu veri sorumlusu olarak, hastalarından gerekli her türlü rızayı almak da dahil olmak üzere, Klinik kalır. Bu politika her iki ilişkiyi de açıklar.',
                    ]],
                    ['heading' => '2. Topladığımız bilgiler', 'body' => [
                        'Web sitesi ziyaretçilerinden: ad, e-posta adresi, telefon numarası, klinik/şirket adı ve İletişim veya Teklif Al formları aracılığıyla gönderilen her türlü mesaj.',
                        'Klinik hesaplarından: personel adı, e-posta adresi, telefon numarası, rolü ve tek kullanımlık şifre (OTP) veya yönetici kimlik bilgileriyle erişimin doğrulanmasında kullanılan giriş etkinliği.',
                        'Kliniklerden, hastaları adına: hasta adı, iletişim bilgileri, cinsiyet, doğum tarihi, adres, randevu ve muayene geçmişi, tedavi kayıtları ve diş şeması (odontogram) çizelgeleri, faturalama ve ödeme kayıtları, röntgen görüntüleri ve klinik personeli tarafından girilen klinik notlar.',
                        'Yapay zeka tedavi planlama verileri: klinik personeli tarafından yazılan veya sesli olarak anlatılan vaka açıklamaları, transkripsiyon için yapılan ses kayıtları ve bunlardan oluşturulan tedavi planları.',
                        'Teknik veriler: web sitesini veya Hizmet\'i kullandığınızda otomatik olarak toplanan IP adresi, tarayıcı türü, cihaz bilgileri ve temel kullanım kayıtları.',
                    ]],
                    ['heading' => '3. Bilgileri nasıl kullanıyoruz', 'body' => [
                        'Bu bilgileri Hizmet\'i işletmek ve sürdürmek, kullanıcıları doğrulamak ve hesapları güvence altına almak, talepleri ve demo isteklerini yanıtlamak, bir Kliniğin talebi üzerine yapay zeka destekli tedavi planları oluşturmak, randevuyla ilgili SMS bildirimlerini ve OTP kodlarını göndermek, Hizmet\'in performansını ve güvenilirliğini izlemek ve iyileştirmek, ve yasal yükümlülüklere uymak amaçlarıyla kullanırız.',
                    ]],
                    ['heading' => '4. Yapay zeka özellikleri ve üçüncü taraf işleme', 'body' => [
                        'Bir Klinik yapay zeka tedavi planı asistanını kullandığında, vaka açıklaması (ve varsa bir sesli kaydın transkripsiyonu), yapılandırılmış bir tedavi planı oluşturmak ve sesi metne dönüştürmek üzere yapay zeka işleme sağlayıcımız OpenAI\'a gönderilir. OpenAI bu verileri kendi veri işleme koşulları kapsamında işler ve bunları kendi modellerini eğitmek için kullanmaz. Bu süreçte hastanın tam adını veya iletişim bilgilerini OpenAI\'a göndermiyoruz.',
                    ]],
                    ['heading' => '5. SMS gönderimi', 'body' => [
                        'Tek kullanımlık giriş kodlarını ve randevu hatırlatmalarını iletmek için, alıcının telefon numarasını ve mesaj içeriğini yalnızca bu mesajı göndermek amacıyla SMS gönderim sağlayıcımız (Turkey SMS) ile paylaşırız.',
                    ]],
                    ['heading' => '6. Çerezler ve yerel depolama', 'body' => [
                        'Web sitesi, yönetim paneline girişinizi sürdürmek için az sayıda temel çerez ve açık/koyu tema tercihinizi hatırlamak için tarayıcı yerel depolamasını kullanır. Reklam veya siteler arası izleme çerezleri kullanmıyoruz.',
                    ]],
                    ['heading' => '7. Bilgileri nasıl paylaşıyoruz', 'body' => [
                        'Kişisel bilgileri satmıyoruz. Bilgileri yalnızca yukarıda açıklanan yapay zeka ve SMS işleyicileriyle, verileri bizim adımıza saklayan barındırma ve altyapı sağlayıcılarıyla ve yasaların gerektirdiği hallerde veya Dentavaria\'nın, müşterilerimizin ya da başkalarının hakları, güvenliği veya mülkiyetini korumak amacıyla yetkili makamlarla paylaşırız.',
                    ]],
                    ['heading' => '8. Veri güvenliği', 'body' => [
                        'Bilgileri korumak için şifreli bağlantılar (HTTPS), token tabanlı kimlik doğrulama ve her Kliniğin verilerini yalnızca o Klinikle sınırlayan erişim kontrolleri (çok kiracılı izolasyon) gibi sektör standardı önlemler kullanıyoruz. Hiçbir iletim veya depolama yöntemi tamamen güvenli değildir ve mutlak güvenliği garanti edemeyiz.',
                    ]],
                    ['heading' => '9. Veri saklama süresi', 'body' => [
                        'Hesap ve hasta verilerini, bir Kliniğin aboneliği aktif olduğu sürece ve veri dışa aktarımına imkan tanımak, yasal veya muhasebe yükümlülüklerine uymak ve uyuşmazlıkları çözmek amacıyla bunun ardından makul bir süre boyunca saklarız. Klinikler, bu yükümlülüklerle sınırlı olmak kaydıyla verilerinin silinmesini talep edebilir.',
                    ]],
                    ['heading' => '10. Uluslararası veri aktarımları', 'body' => [
                        'Altyapımız ve OpenAI dahil işleyicilerimiz, verileri Türkiye dışında işleyebilir. Bu durumlarda, söz konusu işleyicilerin kendi uyumluluk çerçevelerinin sağladığı güvencelere dayanırız.',
                    ]],
                    ['heading' => '11. Haklarınız', 'body' => [
                        'Yürürlükteki mevzuat uyarınca (Kişisel Verilerin Korunması Kanunu - KVKK ve ilgili olduğu ölçüde GDPR dahil), kişisel bilgilerinize erişme, bunları düzeltme, silme veya dışa aktarma ve belirli işleme faaliyetlerine itiraz etme veya bunları kısıtlama hakkına sahip olabilirsiniz. Hastalar bu taleplerini, kayıtlarını kontrol eden kendi Kliniklerine yöneltmelidir; web sitesi ziyaretçileri ve Klinik personeli ise aşağıdaki bilgiler üzerinden doğrudan bizimle iletişime geçebilir.',
                    ]],
                    ['heading' => '12. Çocuklara ait veriler', 'body' => [
                        'Hizmet, diş hekimliği profesyonellerinin kullanımı için tasarlanmıştır. Hasta kayıtları, bir Kliniğin kendi hukuki dayanağı (Kliniğin aldığı veli izni gibi) çerçevesinde girdiği reşit olmayanlara ait bilgileri içerebilir; web sitesi üzerinden çocuklardan doğrudan ve bilerek kişisel bilgi toplamıyoruz.',
                    ]],
                    ['heading' => '13. Bu politikadaki değişiklikler', 'body' => [
                        'Bu Gizlilik Politikasını zaman zaman güncelleyebiliriz. Önemli değişiklikler, bu sayfadaki "Son güncelleme" tarihine yansıtılır.',
                    ]],
                    ['heading' => '14. Bize ulaşın', 'body' => [
                        'Bu politikayla ilgili sorularınızı privacy@dentavaria.com adresine veya bu web sitesindeki İletişim formu aracılığıyla gönderebilirsiniz.',
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
                'updated_date' => 'August 2026',
                'intro' => 'These Terms of Service ("Terms") govern access to and use of Dentavaria\'s website and the Dentavaria clinical operating system, including its admin panel and mobile/web application (together, the "Service"), operated by Dentavaria ("we", "us", "our"). By accessing the website or using the Service, you agree to these Terms. If you are using the Service on behalf of a dental practice ("Clinic"), you confirm that you have authority to bind that Clinic to these Terms.',
                'sections' => [
                    ['heading' => '1. The Service', 'body' => [
                        'Dentavaria provides scheduling, patient records, billing, and AI-assisted treatment planning tools for dental practices. Features, including AI capabilities, may be added, changed, or removed over time.',
                    ]],
                    ['heading' => '2. Accounts and eligibility', 'body' => [
                        'The Service is intended for licensed dental practices and their authorized staff. Each Clinic is responsible for the accuracy of the information it provides, for keeping login credentials confidential, and for all activity that occurs under its account. Access to the admin panel is restricted to users designated as administrators by the Clinic.',
                    ]],
                    ['heading' => '3. Subscriptions and fees', 'body' => [
                        'Access to the Service is provided under a subscription arranged with our sales team. Subscription plans, seat limits, and AI usage allowances are as agreed at signup or as shown on this website for reference. Fees are invoiced as agreed with each Clinic; overdue accounts may have access suspended after notice.',
                    ]],
                    ['heading' => '4. Patient data and Clinic responsibilities', 'body' => [
                        'Clinics are solely responsible for the lawfulness of the patient data they enter into the Service, including obtaining any consent required under applicable health-data and data-protection law. Dentavaria processes this data only to provide the Service and as described in our Privacy Policy, and does not use patient data for any other purpose.',
                    ]],
                    ['heading' => '5. AI-generated treatment plans are not medical advice', 'body' => [
                        'The AI treatment plan assistant produces suggested treatment sessions and pricing based on information provided by clinic staff. It is a drafting aid, not a diagnosis or medical recommendation. A licensed dentist must review, edit as needed, and approve any AI-generated plan before it is presented to or used on a patient. Dentavaria is not responsible for clinical decisions made using the Service.',
                    ]],
                    ['heading' => '6. Acceptable use', 'body' => [
                        'You agree not to use the Service for any unlawful purpose; attempt to access another Clinic\'s data; interfere with or disrupt the Service\'s operation; reverse engineer the Service; or use the Service to store data you are not legally entitled to process.',
                    ]],
                    ['heading' => '7. Intellectual property', 'body' => [
                        'Dentavaria and its licensors own all rights in the Service, including its software, design, and branding. Clinics retain all rights to the patient and business data they enter into the Service.',
                    ]],
                    ['heading' => '8. Third-party services', 'body' => [
                        'The Service integrates third-party providers, including OpenAI (for AI treatment plans and audio transcription) and an SMS provider (for OTP and notifications). Use of these features is subject to those providers\' availability, and Dentavaria is not liable for their outages or errors.',
                    ]],
                    ['heading' => '9. Service availability', 'body' => [
                        'We aim to keep the Service available and reliable but do not guarantee uninterrupted access. Scheduled maintenance and unforeseen outages may occur.',
                    ]],
                    ['heading' => '10. Limitation of liability', 'body' => [
                        'To the maximum extent permitted by law, Dentavaria is not liable for indirect, incidental, or consequential damages arising from use of the Service. Our total liability for any claim relating to the Service is limited to the fees paid by the Clinic in the three months preceding the claim.',
                    ]],
                    ['heading' => '11. Termination', 'body' => [
                        'Either party may terminate a subscription in accordance with the terms agreed at signup. We may suspend or terminate access immediately if these Terms are materially breached or the Service is used unlawfully. Upon termination, Clinics may request an export of their data within a reasonable period.',
                    ]],
                    ['heading' => '12. Governing law', 'body' => [
                        'These Terms are governed by the laws of the Republic of Türkiye. Any dispute arising from these Terms will be subject to the exclusive jurisdiction of the competent courts of Türkiye.',
                    ]],
                    ['heading' => '13. Changes to these Terms', 'body' => [
                        'We may update these Terms from time to time. Continued use of the Service after changes take effect constitutes acceptance of the revised Terms.',
                    ]],
                    ['heading' => '14. Contact us', 'body' => [
                        'Questions about these Terms can be sent to support@dentavaria.com or through the Contact form on this website.',
                    ]],
                ],
            ],
            'ar' => [
                'title' => 'شروط الخدمة',
                'updated_label' => 'آخر تحديث',
                'updated_date' => 'أغسطس 2026',
                'intro' => 'تحكم شروط الخدمة هذه ("الشروط") الوصول إلى موقع Dentavaria واستخدام نظام Dentavaria للتشغيل السريري، بما يشمل لوحة التحكم الإدارية وتطبيق الجوال والويب (يُشار إليها معًا بـ"الخدمة")، والتي تديرها Dentavaria ("نحن"). بوصولك إلى الموقع أو استخدامك للخدمة، فإنك توافق على هذه الشروط. وإذا كنت تستخدم الخدمة نيابةً عن عيادة أسنان ("العيادة")، فأنت تُقر بأن لديك الصلاحية لإلزام تلك العيادة بهذه الشروط.',
                'sections' => [
                    ['heading' => '1. الخدمة', 'body' => [
                        'تقدّم Dentavaria أدوات للجدولة، وسجلات المرضى، والفوترة، والتخطيط العلاجي المدعوم بالذكاء الاصطناعي لعيادات الأسنان. قد تُضاف الميزات، بما فيها قدرات الذكاء الاصطناعي، أو تُعدَّل أو تُزال بمرور الوقت.',
                    ]],
                    ['heading' => '2. الحسابات والأهلية', 'body' => [
                        'الخدمة مخصصة لعيادات الأسنان المرخصة وموظفيها المخوّلين. تتحمل كل عيادة مسؤولية دقة المعلومات التي تقدّمها، والحفاظ على سرية بيانات الدخول، وجميع الأنشطة التي تتم عبر حسابها. يقتصر الوصول إلى لوحة التحكم الإدارية على المستخدمين الذين تحددهم العيادة كمسؤولين إداريين.',
                    ]],
                    ['heading' => '3. الاشتراكات والرسوم', 'body' => [
                        'يُتاح الوصول إلى الخدمة بموجب اشتراك يُرتَّب مع فريق المبيعات لدينا. تكون خطط الاشتراك وحدود عدد المستخدمين ومساحة استخدام الذكاء الاصطناعي وفق ما يُتفق عليه عند التسجيل أو كما هو موضح في هذا الموقع للاستدلال. تُصدَر الفواتير وفق ما يُتفق عليه مع كل عيادة؛ وقد يُعلَّق الوصول للحسابات المتأخرة عن السداد بعد إشعارها.',
                    ]],
                    ['heading' => '4. بيانات المرضى ومسؤوليات العيادة', 'body' => [
                        'تتحمل العيادات وحدها مسؤولية مشروعية بيانات المرضى التي تُدخلها إلى الخدمة، بما في ذلك الحصول على أي موافقة مطلوبة بموجب قوانين البيانات الصحية وحماية البيانات المعمول بها. تعالج Dentavaria هذه البيانات فقط لتقديم الخدمة وكما هو موضح في سياسة الخصوصية الخاصة بنا، ولا تستخدم بيانات المرضى لأي غرض آخر.',
                    ]],
                    ['heading' => '5. خطط العلاج المولّدة بالذكاء الاصطناعي ليست استشارة طبية', 'body' => [
                        'يُنتج مساعد خطة العلاج بالذكاء الاصطناعي جلسات علاج وأسعارًا مقترحة استنادًا إلى المعلومات التي يقدّمها موظفو العيادة. وهو أداة مساعدة لإعداد المسودة، وليس تشخيصًا أو توصية طبية. يجب على طبيب أسنان مرخّص مراجعة أي خطة يولّدها الذكاء الاصطناعي وتعديلها عند الحاجة والموافقة عليها قبل عرضها على المريض أو استخدامها. لا تتحمل Dentavaria مسؤولية القرارات السريرية المتخذة باستخدام الخدمة.',
                    ]],
                    ['heading' => '6. الاستخدام المقبول', 'body' => [
                        'توافق على عدم استخدام الخدمة لأي غرض غير قانوني، وعدم محاولة الوصول إلى بيانات عيادة أخرى، وعدم التدخل في تشغيل الخدمة أو تعطيله، وعدم إجراء هندسة عكسية للخدمة، وعدم استخدام الخدمة لتخزين بيانات لا يحق لك قانونًا معالجتها.',
                    ]],
                    ['heading' => '7. الملكية الفكرية', 'body' => [
                        'تملك Dentavaria والجهات المرخِّصة لها جميع الحقوق المتعلقة بالخدمة، بما في ذلك برمجياتها وتصميمها وهويتها التجارية. تحتفظ العيادات بجميع حقوقها في بيانات المرضى والبيانات التجارية التي تُدخلها إلى الخدمة.',
                    ]],
                    ['heading' => '8. خدمات الأطراف الثالثة', 'body' => [
                        'تدمج الخدمة مزوّدين من أطراف ثالثة، منهم OpenAI (لخطط العلاج بالذكاء الاصطناعي وتفريغ الصوت نصيًا) ومزوّد خدمة الرسائل النصية (لرموز التحقق OTP والإشعارات). يخضع استخدام هذه الميزات لمدى توفر هذه الجهات، ولا تتحمل Dentavaria مسؤولية انقطاعها أو أخطائها.',
                    ]],
                    ['heading' => '9. توفر الخدمة', 'body' => [
                        'نسعى للحفاظ على توفر الخدمة وموثوقيتها، لكننا لا نضمن الوصول دون انقطاع. قد تحدث أعمال صيانة مجدولة أو انقطاعات غير متوقعة.',
                    ]],
                    ['heading' => '10. حدود المسؤولية', 'body' => [
                        'إلى أقصى حد يسمح به القانون، لا تتحمل Dentavaria مسؤولية الأضرار غير المباشرة أو العرضية أو التبعية الناشئة عن استخدام الخدمة. وتقتصر مسؤوليتنا الإجمالية عن أي مطالبة متعلقة بالخدمة على الرسوم التي دفعتها العيادة خلال الأشهر الثلاثة السابقة للمطالبة.',
                    ]],
                    ['heading' => '11. إنهاء الخدمة', 'body' => [
                        'يجوز لأي من الطرفين إنهاء الاشتراك وفقًا للشروط المتفق عليها عند التسجيل. يجوز لنا تعليق الوصول أو إنهاءه فورًا في حال الإخلال الجوهري بهذه الشروط أو استخدام الخدمة بشكل غير قانوني. عند الإنهاء، يمكن للعيادات طلب تصدير بياناتها خلال فترة معقولة.',
                    ]],
                    ['heading' => '12. القانون الحاكم', 'body' => [
                        'تخضع هذه الشروط لقوانين الجمهورية التركية. وتختص المحاكم التركية المختصة حصريًا بالنظر في أي نزاع ينشأ عن هذه الشروط.',
                    ]],
                    ['heading' => '13. التعديلات على هذه الشروط', 'body' => [
                        'قد نقوم بتحديث هذه الشروط من وقت لآخر. ويُعدّ استمرارك في استخدام الخدمة بعد سريان التعديلات موافقة على الشروط المعدَّلة.',
                    ]],
                    ['heading' => '14. تواصل معنا', 'body' => [
                        'يمكن إرسال أي استفسارات حول هذه الشروط إلى support@dentavaria.com أو عبر نموذج "تواصل معنا" في هذا الموقع.',
                    ]],
                ],
            ],
            'tr' => [
                'title' => 'Kullanım Şartları',
                'updated_label' => 'Son güncelleme',
                'updated_date' => 'Ağustos 2026',
                'intro' => 'Bu Kullanım Şartları ("Şartlar"), Dentavaria ("biz") tarafından işletilen Dentavaria web sitesine ve yönetim paneli ile mobil/web uygulaması dahil Dentavaria klinik işletim sistemine (birlikte "Hizmet") erişimi ve bunların kullanımını düzenler. Web sitesine erişerek veya Hizmet\'i kullanarak bu Şartları kabul etmiş olursunuz. Hizmet\'i bir diş kliniği ("Klinik") adına kullanıyorsanız, o Kliniği bu Şartlarla bağlama yetkisine sahip olduğunuzu beyan etmiş olursunuz.',
                'sections' => [
                    ['heading' => '1. Hizmet', 'body' => [
                        'Dentavaria, diş klinikleri için randevu planlama, hasta kayıtları, faturalama ve yapay zeka destekli tedavi planlama araçları sunar. Yapay zeka yetenekleri dahil özellikler zaman içinde eklenebilir, değiştirilebilir veya kaldırılabilir.',
                    ]],
                    ['heading' => '2. Hesaplar ve uygunluk', 'body' => [
                        'Hizmet, ruhsatlı diş kliniklerinin ve bunların yetkili personelinin kullanımı için tasarlanmıştır. Her Klinik, sağladığı bilgilerin doğruluğundan, giriş bilgilerinin gizliliğini korumaktan ve hesabı altında gerçekleşen tüm faaliyetlerden sorumludur. Yönetim paneline erişim, Klinik tarafından yönetici olarak belirlenen kullanıcılarla sınırlıdır.',
                    ]],
                    ['heading' => '3. Abonelikler ve ücretler', 'body' => [
                        'Hizmet\'e erişim, satış ekibimizle düzenlenen bir abonelik kapsamında sağlanır. Abonelik planları, kullanıcı sınırları ve yapay zeka kullanım hakları, kayıt sırasında üzerinde anlaşılan veya bu web sitesinde bilgi amaçlı gösterilen şekildedir. Ücretler her Klinikle üzerinde anlaşılan şekilde faturalandırılır; ödemesi gecikmiş hesapların erişimi, bildirim sonrasında askıya alınabilir.',
                    ]],
                    ['heading' => '4. Hasta verileri ve Klinik sorumlulukları', 'body' => [
                        'Klinikler, Hizmet\'e girdikleri hasta verilerinin hukuka uygunluğundan -ilgili sağlık verisi ve veri koruma mevzuatı uyarınca gereken her türlü rızanın alınması dahil- tek başına sorumludur. Dentavaria bu verileri yalnızca Hizmet\'i sunmak amacıyla ve Gizlilik Politikamızda açıklandığı şekilde işler; hasta verilerini başka hiçbir amaçla kullanmaz.',
                    ]],
                    ['heading' => '5. Yapay zeka tarafından oluşturulan tedavi planları tıbbi tavsiye değildir', 'body' => [
                        'Yapay zeka tedavi planı asistanı, klinik personeli tarafından sağlanan bilgilere dayanarak önerilen tedavi seansları ve fiyatlandırma üretir. Bu bir taslak hazırlama aracıdır; tanı veya tıbbi tavsiye niteliği taşımaz. Yapay zeka tarafından oluşturulan herhangi bir planın bir hastaya sunulmadan veya kullanılmadan önce ruhsatlı bir diş hekimi tarafından incelenmesi, gerekiyorsa düzenlenmesi ve onaylanması zorunludur. Dentavaria, Hizmet kullanılarak alınan klinik kararlardan sorumlu değildir.',
                    ]],
                    ['heading' => '6. Kabul edilebilir kullanım', 'body' => [
                        'Hizmet\'i herhangi bir yasa dışı amaçla kullanmamayı, başka bir Kliniğin verilerine erişmeye çalışmamayı, Hizmet\'in işleyişine müdahale etmemeyi veya onu aksatmamayı, Hizmet üzerinde tersine mühendislik yapmamayı ve Hizmet\'i işleme hakkına yasal olarak sahip olmadığınız verileri depolamak için kullanmamayı kabul edersiniz.',
                    ]],
                    ['heading' => '7. Fikri mülkiyet', 'body' => [
                        'Hizmet üzerindeki, yazılımı, tasarımı ve markası dahil tüm haklar Dentavaria ve lisans verenlerine aittir. Klinikler, Hizmet\'e girdikleri hasta ve işletme verileri üzerindeki tüm haklarını saklı tutar.',
                    ]],
                    ['heading' => '8. Üçüncü taraf hizmetleri', 'body' => [
                        'Hizmet; yapay zeka tedavi planları ve ses transkripsiyonu için OpenAI ile OTP ve bildirimler için bir SMS sağlayıcısı dahil üçüncü taraf sağlayıcılarla entegredir. Bu özelliklerin kullanımı, söz konusu sağlayıcıların kullanılabilirliğine tabidir ve Dentavaria, bunların kesintilerinden veya hatalarından sorumlu değildir.',
                    ]],
                    ['heading' => '9. Hizmetin kullanılabilirliği', 'body' => [
                        'Hizmet\'i kullanılabilir ve güvenilir tutmayı hedefliyoruz ancak kesintisiz erişimi garanti etmiyoruz. Planlı bakım çalışmaları ve öngörülemeyen kesintiler meydana gelebilir.',
                    ]],
                    ['heading' => '10. Sorumluluğun sınırlandırılması', 'body' => [
                        'Yasaların izin verdiği azami ölçüde, Dentavaria, Hizmet\'in kullanımından kaynaklanan dolaylı, arızi veya sonuç niteliğindeki zararlardan sorumlu değildir. Hizmet ile ilgili herhangi bir talebe ilişkin toplam sorumluluğumuz, Kliniğin talep tarihinden önceki üç ay içinde ödediği ücretlerle sınırlıdır.',
                    ]],
                    ['heading' => '11. Fesih', 'body' => [
                        'Taraflardan her biri, kayıt sırasında üzerinde anlaşılan şartlara uygun olarak aboneliği feshedebilir. Bu Şartların esaslı biçimde ihlal edilmesi veya Hizmet\'in hukuka aykırı kullanılması halinde erişimi derhal askıya alabilir veya sonlandırabiliriz. Fesih üzerine, Klinikler makul bir süre içinde verilerinin dışa aktarılmasını talep edebilir.',
                    ]],
                    ['heading' => '12. Uygulanacak hukuk', 'body' => [
                        'Bu Şartlar, Türkiye Cumhuriyeti kanunlarına tabidir. Bu Şartlardan doğan her türlü uyuşmazlıkta Türkiye\'deki yetkili mahkemeler münhasıran yetkilidir.',
                    ]],
                    ['heading' => '13. Bu Şartlardaki değişiklikler', 'body' => [
                        'Bu Şartları zaman zaman güncelleyebiliriz. Değişikliklerin yürürlüğe girmesinden sonra Hizmet\'i kullanmaya devam etmeniz, güncellenmiş Şartları kabul ettiğiniz anlamına gelir.',
                    ]],
                    ['heading' => '14. Bize ulaşın', 'body' => [
                        'Bu Şartlarla ilgili sorularınızı support@dentavaria.com adresine veya bu web sitesindeki İletişim formu aracılığıyla gönderebilirsiniz.',
                    ]],
                ],
            ],
        ];
    }
}
