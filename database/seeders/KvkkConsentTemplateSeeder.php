<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\ConsentTemplate;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Seeds the two KVKK-specific consent templates every Company needs on top
 * of whatever clinical (procedure) consent forms it writes itself: the
 * Aydınlatma Metni (KVKK m.10 disclosure -- informational, patient
 * acknowledges having read it) and the Açık Rıza Beyanı (KVKK m.6/2
 * explicit consent -- required before a doctor can use the AI treatment
 * plan assistant, AI X-ray reading, or AI lab/analysis-report reading for
 * that patient, since all of them send health data to OpenAI in the
 * United States; see
 * RequiresKvkkConsent middleware and the KVKK compliance plan,
 * docs/superpowers/plans/2026-09-05-kvkk-uyumlulugu.md, Görev 2.2/5.4).
 *
 * The body text below is a functional first draft, written the same way
 * app/Support/LegalContent.php's Privacy Policy/Terms text was: it should
 * still get a lawyer's review pass before go-live, same as that page's
 * "Last updated" note implies -- but it is real, complete KVKK-format text,
 * not a placeholder, so the consent flow works end to end today.
 *
 * updateOrCreate keyed on (company_id, kind) so this stays idempotent and
 * safe to run again (e.g. from CompanyController::store() on every new
 * company, and once retroactively for existing companies).
 */
class KvkkConsentTemplateSeeder extends Seeder
{
    public function run(): void
    {
        Company::query()->each(fn (Company $company) => $this->seedCompany($company));
    }

    public function seedCompany(Company $company): void
    {
        $this->updateOrCreateTemplate(
            ['company_id' => $company->id, 'kind' => ConsentTemplate::KIND_KVKK_DISCLOSURE],
            [
                'title' => 'Kişisel Verilerin İşlenmesine İlişkin Aydınlatma Metni',
                'language' => 'tr',
                'is_active' => true,
                'body' => $this->disclosureBody(),
                'sections' => $this->disclosureSections(),
            ]
        );

        $this->updateOrCreateTemplate(
            ['company_id' => $company->id, 'kind' => ConsentTemplate::KIND_KVKK_EXPLICIT_CONSENT],
            [
                'title' => 'Açık Rıza Beyanı',
                'language' => 'tr',
                'is_active' => true,
                'body' => $this->explicitConsentBody(),
                'sections' => null,
            ]
        );
    }

    /**
     * Same effect as ConsentTemplate::updateOrCreate($attributes, $values),
     * except it's safe when model events are suppressed (DatabaseSeeder --
     * which this is also called from via the blanket per-deploy backfill --
     * uses WithoutModelEvents, which silently disables HasUuid's `creating`
     * hook, so a plain updateOrCreate() would try to insert a NULL into the
     * NOT NULL `uuid` column and fail). Assigns a uuid only when actually
     * inserting a new row; an existing row's uuid is left untouched on every
     * later re-run, since save() only fills the keys present in $values.
     */
    protected function updateOrCreateTemplate(array $attributes, array $values): ConsentTemplate
    {
        $template = ConsentTemplate::query()->firstOrNew($attributes);

        if (! $template->exists) {
            $template->uuid = (string) Str::uuid();
        }

        $template->fill($values)->save();

        return $template;
    }

    protected function disclosureBody(): string
    {
        return <<<'TEXT'
        {company_name} ("Klinik") olarak, 6698 sayılı Kişisel Verilerin Korunması Kanunu ("KVKK") uyarınca veri sorumlusu sıfatıyla, {client_name} olarak tarafımıza ilettiğiniz veya tedaviniz sırasında elde edilen kişisel verilerinizin işlenmesine ilişkin sizi aşağıdaki hususlarda bilgilendiririz.
        TEXT;
    }

    protected function disclosureSections(): array
    {
        return [
            [
                'heading' => 'İşlenen kişisel veri kategorileri',
                'body' => 'Kimlik bilgileriniz (ad-soyad, doğum tarihi, cinsiyet), iletişim bilgileriniz (telefon, e-posta, adres), sağlık verileriniz (muayene/teşhis/tedavi kayıtları, diş şeması, röntgen görüntüleri, laboratuvar sonuçları, kullanılan ilaç/malzeme bilgileri) ve finansal verileriniz (fatura, ödeme, tahsilat kayıtları) işlenmektedir.',
            ],
            [
                'heading' => 'İşlenme amaçları',
                'body' => 'Verileriniz; teşhis ve tedavi süreçlerinin yürütülmesi, randevu planlaması, hasta iletişimi (randevu hatırlatmaları dahil), faturalandırma ve tahsilat, mevzuattan doğan saklama/raporlama yükümlülüklerinin yerine getirilmesi ve -yalnızca ayrıca alınacak açık rızanız bulunması halinde- yapay zeka destekli tedavi planlaması, röntgen değerlendirmesi ve laboratuvar/tahlil raporu fotoğrafınızın okunarak sonuçlarının kayda geçirilmesi amaçlarıyla işlenmektedir.',
            ],
            [
                'heading' => 'Hukuki sebep',
                'body' => 'Sağlık verileriniz KVKK m.6/3 uyarınca, sır saklama yükümlülüğü altındaki sağlık çalışanlarınca kamu sağlığının korunması, koruyucu hekimlik, tıbbi teşhis ve tedavi amacıyla; diğer verileriniz ise KVKK m.5/2 kapsamında sözleşmenin ifası ve kanunlarda açıkça öngörülme hukuki sebeplerine dayanılarak işlenmektedir.',
            ],
            [
                'heading' => 'Kimlere ve hangi amaçla aktarılabilir',
                'body' => 'Verileriniz; yasal yükümlülükler kapsamında yetkili kamu kurum ve kuruluşlarına, randevu/hatırlatma SMS\'lerinin iletilmesi amacıyla SMS hizmet sağlayıcımıza, ve -yalnızca ayrıca imzalayacağınız Açık Rıza Beyanı bulunması halinde- yapay zeka destekli tedavi planlaması, röntgen okuma ve laboratuvar/tahlil raporu okuma hizmeti sağlayan OpenAI\'a (Amerika Birleşik Devletleri) aktarılabilir.',
            ],
            [
                'heading' => 'Toplama yöntemi',
                'body' => 'Verileriniz; muayene/kayıt sırasında sözlü veya yazılı olarak tarafınızdan, randevu/rezervasyon sistemleri aracılığıyla ve tedavi sürecinde kullanılan tıbbi cihazlar (röntgen vb.) aracılığıyla elektronik ortamda toplanmaktadır.',
            ],
            [
                'heading' => 'KVKK m.11 kapsamındaki haklarınız',
                'body' => 'KVKK\'nın 11. maddesi uyarınca; kişisel verinizin işlenip işlenmediğini öğrenme, işlenmişse buna ilişkin bilgi talep etme, işlenme amacını ve amacına uygun kullanılıp kullanılmadığını öğrenme, yurt içinde/yurt dışında aktarıldığı üçüncü kişileri bilme, eksik/yanlış işlenmişse düzeltilmesini isteme, KVKK m.7 şartları çerçevesinde silinmesini/yok edilmesini isteme, yapılan işlemlerin verilerin aktarıldığı üçüncü kişilere bildirilmesini isteme, münhasıran otomatik sistemlerle analiz sonucu aleyhinize bir sonucun ortaya çıkmasına itiraz etme ve kanuna aykırı işleme sebebiyle zarara uğramanız hâlinde zararın giderilmesini talep etme haklarına sahipsiniz. Bu haklarınızı kullanmak için Klinik\'e yazılı olarak başvurabilirsiniz.',
            ],
        ];
    }

    protected function explicitConsentBody(): string
    {
        return <<<'TEXT'
        {client_name} olarak, {company_name} tarafından yukarıdaki Aydınlatma Metni'nde belirtilen kapsamda tarafıma ait sağlık verilerimin (röntgen görüntülerim, laboratuvar/tahlil raporu fotoğraf veya belgelerim ve/veya vaka açıklamam dahil), yapay zeka destekli tedavi planlaması, röntgen değerlendirmesi ve/veya laboratuvar/tahlil raporu okuma hizmeti sunmak amacıyla, bu hizmeti sağlayan OpenAI, L.L.C. şirketine (Amerika Birleşik Devletleri) aktarılmasına, KVKK m.6/2 uyarınca özgür irademle, bilgilendirilmiş ve tereddüde yer vermeyecek açıklıkta AÇIK RIZA gösteriyorum.

        Bu rızayı istediğim zaman, Klinik'e yazılı başvuruda bulunarak geri çekebileceğimi; rızamı geri çekmemin, geri çekme tarihinden önce bu rızaya dayanılarak gerçekleştirilmiş işlemlerin hukuka uygunluğunu etkilemeyeceğini biliyorum. Bu rızayı vermemem veya geri çekmemin, Klinik'ten alacağım tedavi hizmetinin diğer kısımlarını hiçbir şekilde etkilemeyeceği tarafıma bildirilmiştir; yapay zeka destekli özellikler kullanılmadan da tedavim aynı şekilde sürdürülür.
        TEXT;
    }
}
