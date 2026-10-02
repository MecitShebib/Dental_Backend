# Aşama 1 — 5 Yeni Uzmanlık Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Pediavaria, Physiovaria, Hemavaria, Surgivaria, Genervaria uzmanlıklarını Orthovaria ile aynı derinlikte (backend + frontend + landing + admin + satış sayfaları) eklemek.

**Architecture:** Kopyala-yapıştır (kullanıcı kararı). Orthovaria'nın dosyaları bir Node token-değiştirme script'iyle 5 kez klonlanır; uzmanlığa özgü kısımlar (katalog, bakım planı kilometre taşları, AI sözlüğü, landing metni, çeviriler) elle yazılır. `dental` ve `nutrition`'a ait hiçbir dosya değişmez.

**Tech Stack:** Laravel 12 / PHPUnit, React + Vite (Dental_FrontEnd/app/frontend), Node (klonlama script'i).

**Spec:** `docs/superpowers/specs/2026-09-27-five-new-specialties-and-specialty-cleanup-design.md`

**Git:** Kullanıcı main'de çalışır ve commit/push'u kendisi ister — commit adımları yalnızca kullanıcı onay verirse yapılır.

---

## Referans tablo (tüm task'lar bunu kullanır)

| key | Pascal | CONST | Brand | slug | EN ad | TR ad | AR ad | accent | sort |
|---|---|---|---|---|---|---|---|---|---|
| pediatrics | Pediatrics | PEDIATRICS | Pediavaria | pediavaria | Pediatrics | Çocuk Sağlığı ve Hastalıkları | طب الأطفال | #3a86ff | 7 |
| physiotherapy | Physiotherapy | PHYSIOTHERAPY | Physiovaria | physiovaria | Physiotherapy | Fizyoterapi | العلاج الفيزيائي | #5c8a1f | 8 |
| hematology | Hematology | HEMATOLOGY | Hemavaria | hemavaria | Hematology | Hematoloji | أمراض الدم | #b3261e | 9 |
| general_surgery | GeneralSurgery | GENERAL_SURGERY | Surgivaria | surgivaria | General Surgery | Genel Cerrahi | الجراحة العامة | #37474f | 10 |
| general_practice | GeneralPractice | GENERAL_PRACTICE | Genervaria | genervaria | General Practice | Pratisyen Hekimlik | الطب العام | #8a6d1f | 11 |

Bakım planı adlandırması (Orthovaria'daki `RehabCarePlan` / `rehab-care-plan` / `injury` karşılıkları):

| key | PlanPascal | plan-kebab | planCamel | PlanShort | field | fieldLabelKey |
|---|---|---|---|---|---|---|
| pediatrics | WellChildCarePlan | well-child-care-plan | wellChildCarePlan | WellChild | notes | wellChildNotes |
| physiotherapy | PhysioSessionCarePlan | physio-session-care-plan | physioSessionCarePlan | PhysioSession | diagnosis | physioDiagnosis |
| hematology | BloodCountCarePlan | blood-count-care-plan | bloodCountCarePlan | BloodCount | diagnosis | hematologyDiagnosis |
| general_surgery | PerioperativeCarePlan | perioperative-care-plan | perioperativeCarePlan | Perioperative | operation | plannedOperation |
| general_practice | GeneralFollowupCarePlan | general-followup-care-plan | generalFollowupCarePlan | GeneralFollowup | complaint | chiefComplaint |

Katalog (kod → EN / TR / AR / TL):

- **pediatrics:** `ped_well_child_visit` Well-Child Visit / Çocuk İzlem Muayenesi / فحص متابعة الطفل / 400 · `ped_vaccination_visit` Vaccination Visit / Aşı Uygulaması / زيارة تطعيم / 250 · `ped_growth_assessment` Growth & Development Assessment / Büyüme-Gelişim Değerlendirmesi / تقييم النمو والتطور / 300 · `ped_sick_visit` Sick Child Visit / Hasta Çocuk Muayenesi / فحص طفل مريض / 450
- **physiotherapy:** `phy_initial_evaluation` Initial Physiotherapy Evaluation / İlk Fizyoterapi Değerlendirmesi / تقييم علاج فيزيائي أولي / 400 · `phy_therapy_session` Physiotherapy Session / Fizyoterapi Seansı / جلسة علاج فيزيائي / 350 · `phy_reevaluation` Re-evaluation / Ara Değerlendirme / إعادة تقييم / 300 · `phy_final_evaluation` Final Evaluation / Son Değerlendirme / تقييم نهائي / 400
- **hematology:** `hem_consultation` Hematology Consultation / Hematoloji Muayenesi / استشارة أمراض الدم / 600 · `hem_cbc_control` CBC Control / Hemogram Kontrolü / فحص تعداد الدم / 250 · `hem_iron_infusion` IV Iron Infusion / Damar Yolu Demir Tedavisi / تسريب حديد وريدي / 900 · `hem_evaluation` Treatment Evaluation / Tedavi Değerlendirmesi / تقييم العلاج / 450
- **general_surgery:** `gs_preop_evaluation` Pre-operative Evaluation / Ameliyat Öncesi Değerlendirme / تقييم ما قبل العملية / 600 · `gs_surgery` Surgical Procedure / Cerrahi İşlem / إجراء جراحي / 15000 · `gs_wound_care` Wound Care & Suture Removal / Pansuman ve Dikiş Alımı / العناية بالجرح وإزالة الغرز / 300 · `gs_postop_control` Post-operative Control / Ameliyat Sonrası Kontrol / متابعة ما بعد العملية / 450
- **general_practice:** `gp_examination` General Examination / Genel Muayene / فحص عام / 400 · `gp_followup` Follow-up Visit / Kontrol Muayenesi / زيارة متابعة / 250 · `gp_lab_panel` Basic Lab Panel / Temel Tahlil Paneli / لوحة تحاليل أساسية / 500 · `gp_checkup` Periodic Check-up / Periyodik Sağlık Kontrolü / فحص دوري شامل / 700

Kilometre taşları (`day_offset` → başlık → kod):

- **pediatrics** (8, son = +690): 0 "1st Month Well-Child Visit" ped_well_child_visit · 30 "2nd Month Vaccination" ped_vaccination_visit · 90 "4th Month Vaccination" ped_vaccination_visit · 150 "6th Month Vaccination" ped_vaccination_visit · 240 "9th Month Well-Child Visit" ped_well_child_visit · 330 "12th Month Vaccination" ped_vaccination_visit · 510 "18th Month Vaccination" ped_vaccination_visit · 690 "24th Month Growth Assessment" ped_growth_assessment
- **physiotherapy** (11, son = +28): 0 "Initial Evaluation" phy_initial_evaluation · 2 "Session 1" · 4 "Session 2" · 7 "Session 3" · 9 "Session 4" · 11 "Session 5" (hepsi phy_therapy_session) · 14 "Re-evaluation" phy_reevaluation · 16 "Session 6" · 18 "Session 7" · 21 "Session 8" (phy_therapy_session) · 28 "Final Evaluation" phy_final_evaluation
- **hematology** (6, son = +90): 0 "Hematology Consultation" hem_consultation · 14 "CBC Control - Week 2" · 28 "CBC Control - Week 4" · 56 "CBC Control - Week 8" · 84 "CBC Control - Week 12" (hem_cbc_control) · 90 "Treatment Evaluation" hem_evaluation
- **general_surgery** (4, son = +37): 0 "Pre-operative Evaluation" gs_preop_evaluation · 7 "Surgery" gs_surgery · 14 "Wound Care & Suture Removal" gs_wound_care · 37 "Post-operative Control" gs_postop_control
- **general_practice** (3, son = +90): 0 "General Examination" gp_examination · 14 "Follow-up Visit" gp_followup · 90 "Periodic Check-up" gp_checkup

AI domain etiketleri: pediatrics → `pediatrics (child health)`, physiotherapy → `physiotherapy and physical rehabilitation`, hematology → `hematology`, general_surgery → `general surgery and perioperative care`, general_practice → `general practice / primary care`.

---

### Task 1: Specialty sabitleri + seeder

**Files:**
- Modify: `app/Models/Specialty.php` (sabitler)
- Modify: `database/seeders/SpecialtySeeder.php` (5 satır, sort 7–11)
- Modify: `tests/Feature/SpecialtyTest.php` (altı → on bir)

- [ ] **Step 1: Testi güncelle (başarısız olmalı)** — `test_the_seeder_creates_exactly_six_active_specialties` içindeki beklenen sayıyı 11 yap, test adını `..._eleven_...` olarak değiştir ve yeni key'lerin varlığını assert et:

```php
foreach (['pediatrics', 'physiotherapy', 'hematology', 'general_surgery', 'general_practice'] as $key) {
    $this->assertDatabaseHas('specialties', ['key' => $key, 'is_active' => true]);
}
```

- [ ] **Step 2:** `php artisan test tests/Feature/SpecialtyTest.php` → FAIL (6 ≠ 11).
- [ ] **Step 3: Sabitler** — `Specialty.php`'de `NUTRITION`'dan sonra:

```php
    public const PEDIATRICS = 'pediatrics';

    public const PHYSIOTHERAPY = 'physiotherapy';

    public const HEMATOLOGY = 'hematology';

    public const GENERAL_SURGERY = 'general_surgery';

    public const GENERAL_PRACTICE = 'general_practice';
```

- [ ] **Step 4: Seeder** — `$specialties` dizisine referans tablodan 5 satır (`'icon' => key` ile tire: `general-surgery`, `general-practice`), ör.:

```php
            [
                'key' => Specialty::PEDIATRICS,
                'brand_name' => 'Pediavaria',
                'name_ar' => 'طب الأطفال',
                'name_en' => 'Pediatrics',
                'name_tr' => 'Çocuk Sağlığı ve Hastalıkları',
                'icon' => 'pediatrics',
                'is_active' => true,
                'sort_order' => 7,
            ],
```
(Diğer dördü aynı şekilde, tablodaki değerlerle.) Docblock'taki "six" ifadesini "eleven" yap.

- [ ] **Step 5:** Test → PASS.

### Task 2: Klonlama script'i

**Files:**
- Create: `<scratchpad>/clone-specialty.mjs`

- [ ] **Step 1: Script'i yaz**

```js
// node clone-specialty.mjs <backendRoot> <frontendSrc>
import fs from "node:fs";
import path from "node:path";

const [backend, frontendSrc] = process.argv.slice(2);

const SPECS = [
  { key: "pediatrics", Pascal: "Pediatrics", CONST: "PEDIATRICS", Brand: "Pediavaria", slug: "pediavaria", en: "Pediatric", PlanPascal: "WellChildCarePlan", planKebab: "well-child-care-plan", planCamel: "wellChildCarePlan", PlanShort: "WellChild", field: "notes", fieldLabelKey: "wellChildNotes", firstCode: "ped_well_child_visit" },
  { key: "physiotherapy", Pascal: "Physiotherapy", CONST: "PHYSIOTHERAPY", Brand: "Physiovaria", slug: "physiovaria", en: "Physiotherapy", PlanPascal: "PhysioSessionCarePlan", planKebab: "physio-session-care-plan", planCamel: "physioSessionCarePlan", PlanShort: "PhysioSession", field: "diagnosis", fieldLabelKey: "physioDiagnosis", firstCode: "phy_initial_evaluation" },
  { key: "hematology", Pascal: "Hematology", CONST: "HEMATOLOGY", Brand: "Hemavaria", slug: "hemavaria", en: "Hematology", PlanPascal: "BloodCountCarePlan", planKebab: "blood-count-care-plan", planCamel: "bloodCountCarePlan", PlanShort: "BloodCount", field: "diagnosis", fieldLabelKey: "hematologyDiagnosis", firstCode: "hem_consultation" },
  { key: "general_surgery", Pascal: "GeneralSurgery", CONST: "GENERAL_SURGERY", Brand: "Surgivaria", slug: "surgivaria", en: "General surgery", PlanPascal: "PerioperativeCarePlan", planKebab: "perioperative-care-plan", planCamel: "perioperativeCarePlan", PlanShort: "Perioperative", field: "operation", fieldLabelKey: "plannedOperation", firstCode: "gs_preop_evaluation" },
  { key: "general_practice", Pascal: "GeneralPractice", CONST: "GENERAL_PRACTICE", Brand: "Genervaria", slug: "genervaria", en: "General practice", PlanPascal: "GeneralFollowupCarePlan", planKebab: "general-followup-care-plan", planCamel: "generalFollowupCarePlan", PlanShort: "GeneralFollowup", field: "complaint", fieldLabelKey: "chiefComplaint", firstCode: "gp_examination" },
];

// Order matters: longer / more specific tokens first.
function replacements(s) {
  return [
    ["RehabCarePlan", s.PlanPascal],
    ["rehab-care-plan", s.planKebab],
    ["rehabCarePlanDescription", `${s.planCamel}Description`],
    ["rehabCarePlan", s.planCamel],
    ["createRehabPlan", `create${s.PlanShort}Plan`],
    ["confirmRehabPlan", `confirm${s.PlanShort}Plan`],
    ["injuryOrProcedure", s.fieldLabelKey],
    ['fieldKey="injury"', `fieldKey="${s.field}"`],
    ["'injury'", `'${s.field}'`],
    ["ortho_assessment", s.firstCode],
    ["Orthovaria", s.Brand],
    ["orthovaria", s.slug],
    ["ORTHOPEDICS", s.CONST],
    ["Orthopedics", s.Pascal],
    ["orthopedics", s.key],
    ["Orthopedic", s.en],
  ];
}

function transform(text, s) {
  return replacements(s).reduce((acc, [from, to]) => acc.split(from).join(to), text);
}

function cloneFile(src, dest, s) {
  if (fs.existsSync(dest)) throw new Error(`refusing to overwrite ${dest}`);
  fs.mkdirSync(path.dirname(dest), { recursive: true });
  fs.writeFileSync(dest, transform(fs.readFileSync(src, "utf8"), s));
  console.log("created", dest);
}

for (const s of SPECS) {
  const b = (p) => path.join(backend, p);
  for (const name of ["AiConversationController", "AppointmentController", "ClientController", "DashboardController"]) {
    cloneFile(b(`app/Http/Controllers/Api/Orthopedics/${name}.php`), b(`app/Http/Controllers/Api/${s.Pascal}/${name}.php`), s);
  }
  cloneFile(b("app/Http/Controllers/Api/RehabCarePlanController.php"), b(`app/Http/Controllers/Api/${s.PlanPascal}Controller.php`), s);
  cloneFile(b("app/Http/Requests/Orthopedics/ConfirmRehabCarePlanRequest.php"), b(`app/Http/Requests/${s.Pascal}/Confirm${s.PlanPascal}Request.php`), s);
  cloneFile(b("routes/api/orthopedics.php"), b(`routes/api/${s.key}.php`), s);
  for (const name of ["AiConversationTest", "AppointmentAndDashboardTest", "ClientControllerTest"]) {
    cloneFile(b(`tests/Feature/Orthopedics/${name}.php`), b(`tests/Feature/${s.Pascal}/${name}.php`), s);
  }
  for (const page of ["AppointmentsPage", "ClientDetailsPage", "ClientEditPage", "DashboardPage", "PatientsPage"]) {
    cloneFile(
      path.join(frontendSrc, `specialties/orthopedics/pages/Orthopedics${page}.jsx`),
      path.join(frontendSrc, `specialties/${s.key}/pages/${s.Pascal}${page}.jsx`),
      s,
    );
  }
}
```

- [ ] **Step 2: Çalıştır**

```bash
node "<scratchpad>/clone-specialty.mjs" /c/Users/MK/Desktop/Dental_Backend /c/Users/MK/Desktop/Dental_FrontEnd/app/frontend/src
```
Beklenen: 5 × 17 = 85 "created" satırı, hata yok.

- [ ] **Step 3: Artık kalan Orthopedics izlerini kontrol et**

```bash
grep -rniE "orthoped|orthovaria|rehab|injury" app/Http/Controllers/Api/{Pediatrics,Physiotherapy,Hematology,GeneralSurgery,GeneralPractice} app/Http/Controllers/Api/*CarePlanController.php app/Http/Requests/{Pediatrics,Physiotherapy,Hematology,GeneralSurgery,GeneralPractice} routes/api/{pediatrics,physiotherapy,hematology,general_surgery,general_practice}.php tests/Feature/{Pediatrics,Physiotherapy,Hematology,GeneralSurgery,GeneralPractice}
```
Beklenen: yalnızca `Rehab/Orthopedics` dışı zararsız yorum satırları (ör. "rehab" kelimesi yorumda). Kalanları elle uzmanlığa uygun hale getir; frontend sayfalarında da aynı grep'i çalıştır ve metinsel "rehab" yorumlarını düzelt. `PatientsPage`/`ClientDetailsPage`'de `activeModal === "prenatal"` gibi Orthovaria'dan miras kalan dahili state adları olduğu gibi kalabilir (davranışsal değil).

### Task 3: Modüller, bakım planı servisleri, registry, route'lar

**Files (her uzmanlık için):**
- Create: `app/Specialties/{Pascal}/{Pascal}Module.php`
- Create: `app/Specialties/{Pascal}/{PlanPascal}Service.php`
- Modify: `app/Specialties/SpecialtyModuleRegistry.php`
- Modify: `routes/api.php` (require + confirm route + use)
- Create: `tests/Feature/{Pascal}/{PlanPascal}Test.php`

- [ ] **Step 1: Bakım planı testini yaz** — her uzmanlık için `tests/Feature/Orthopedics/RehabCarePlanTest.php`'nin yapısıyla (aynı `setUp`, `doctorWithFullWeekSchedule`, `makeClient`), şu değerlerle:

| key | katalog 4 kod (assert contains) | sessions | son session_index | son tarih (start 2026-01-01) | field değeri | endpoint |
|---|---|---|---|---|---|---|
| pediatrics | ped_well_child_visit, ped_vaccination_visit, ped_growth_assessment, ped_sick_visit | 8 | 7 | 2027-11-22 | 'Routine follow-up' | /well-child-care-plan/confirm |
| physiotherapy | phy_initial_evaluation, phy_therapy_session, phy_reevaluation, phy_final_evaluation | 11 | 10 | 2026-01-29 | 'Lumbar disc herniation' | /physio-session-care-plan/confirm |
| hematology | hem_consultation, hem_cbc_control, hem_iron_infusion, hem_evaluation | 6 | 5 | 2026-04-01 | 'Iron deficiency anemia' | /blood-count-care-plan/confirm |
| general_surgery | gs_preop_evaluation, gs_surgery, gs_wound_care, gs_postop_control | 4 | 3 | 2026-02-07 | 'Laparoscopic cholecystectomy' | /perioperative-care-plan/confirm |
| general_practice | gp_examination, gp_followup, gp_lab_panel, gp_checkup | 3 | 2 | 2026-04-01 | 'Persistent cough' | /general-followup-care-plan/confirm |

Pediatri örneği (diğerleri tablodaki değerlerle birebir aynı gövde):

```php
<?php

namespace Tests\Feature\Pediatrics;

use App\Models\Client;
use App\Models\Company;
use App\Models\Specialty;
use App\Models\Subscription;
use App\Models\TreatmentCatalog;
use App\Models\User;
use App\Specialties\Pediatrics\PediatricsModule;
use App\Specialties\Pediatrics\WellChildCarePlanService;
use Database\Seeders\SpecialtySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WellChildCarePlanTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SpecialtySeeder::class);
    }

    protected function doctorWithFullWeekSchedule(): User
    {
        $doctor = User::factory()->create(['is_doctor' => true]);
        $company = $doctor->company;
        $company->subscriptions()->delete();
        $specialty = Specialty::query()->where('key', Specialty::PEDIATRICS)->firstOrFail();
        Subscription::create([
            'company_id' => $company->id,
            'specialty_id' => $specialty->id,
            'plan_name' => 'Test Plan',
            'status' => 'active',
            'starts_at' => now()->subDay()->toDateString(),
        ]);

        app(PediatricsModule::class)->seedCatalog($company);

        $schedule = $doctor->doctorSchedule()->create([
            'start_time' => '09:00:00',
            'end_time' => '17:00:00',
            'slot_minutes' => 30,
        ]);
        foreach (['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'] as $day) {
            $schedule->workingDays()->create(['weekday' => $day]);
        }

        return $doctor;
    }

    protected function makeClient(int $companyId): Client
    {
        return Client::create([
            'company_id' => $companyId,
            'client_code' => 'CL-'.fake()->unique()->numberBetween(1000, 9999),
            'name' => 'Test Patient',
            'phone' => fake()->unique()->e164PhoneNumber(),
            'status' => 'new',
        ]);
    }

    public function test_the_catalog_is_seeded_with_four_items(): void
    {
        $company = Company::factory()->create();
        app(PediatricsModule::class)->seedCatalog($company);

        $specialty = Specialty::query()->where('key', Specialty::PEDIATRICS)->firstOrFail();
        $items = TreatmentCatalog::query()->where('company_id', $company->id)->where('specialty_id', $specialty->id)->get();

        $this->assertCount(4, $items);
        foreach (['ped_well_child_visit', 'ped_vaccination_visit', 'ped_growth_assessment', 'ped_sick_visit'] as $code) {
            $this->assertTrue($items->contains('code', $code));
        }
    }

    public function test_confirming_generates_every_milestone_appointment_with_charges(): void
    {
        $doctor = $this->doctorWithFullWeekSchedule();
        $client = $this->makeClient($doctor->company_id);
        Sanctum::actingAs($doctor);

        $plan = app(WellChildCarePlanService::class)->confirmPlan($client, $doctor, 'Routine follow-up', '2026-01-01', '10:00', $doctor->id);

        $this->assertCount(8, $plan->sessions);
        $this->assertStringContainsString('Routine follow-up', $plan->summary);
        $first = $plan->sessions->firstWhere('session_index', 0);
        $this->assertSame('2026-01-01', $first->appointment->date->format('Y-m-d'));
        $this->assertSame('Routine follow-up', $first->clinical_data['notes']);
        $last = $plan->sessions->firstWhere('session_index', 7);
        $this->assertSame('2027-11-22', $last->appointment->date->format('Y-m-d'));
        $this->assertGreaterThan(0, $client->treatmentCharges()->sum('amount'));
    }

    public function test_the_confirm_endpoint_creates_a_care_plan_for_the_acting_doctor(): void
    {
        $doctor = $this->doctorWithFullWeekSchedule();
        $client = $this->makeClient($doctor->company_id);
        Sanctum::actingAs($doctor);

        $response = $this->postJson("/api/clients/{$client->id}/well-child-care-plan/confirm", [
            'notes' => 'Routine follow-up',
            'start_date' => '2026-01-01',
            'preferred_start_time' => '10:00',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.specialty_key', Specialty::PEDIATRICS);
        $response->assertJsonCount(8, 'data.sessions');
    }

    public function test_a_system_manager_must_pick_a_treating_doctor(): void
    {
        $doctor = $this->doctorWithFullWeekSchedule();
        $client = $this->makeClient($doctor->company_id);
        $manager = User::factory()->create(['company_id' => $doctor->company_id, 'is_doctor' => false]);
        Sanctum::actingAs($manager);

        $response = $this->postJson("/api/clients/{$client->id}/well-child-care-plan/confirm", [
            'notes' => 'Routine follow-up',
            'start_date' => '2026-01-01',
            'preferred_start_time' => '10:00',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('doctor_id');
    }
}
```

- [ ] **Step 2:** `php artisan test tests/Feature/Pediatrics` → FAIL (sınıflar yok).
- [ ] **Step 3: Module** — Pediatri örneği (diğerleri referans tablodaki katalogla aynı gövde, `Specialty::X` ve brand değişir):

```php
<?php

namespace App\Specialties\Pediatrics;

use App\Models\Company;
use App\Models\Specialty;
use App\Models\TreatmentCatalog;
use App\Specialties\SpecialtyModule;

/**
 * Pediavaria's v1 (2026-09-27): a small flat treatment catalog and a
 * WellChildCarePlanService (well-child / vaccination follow-up timeline via
 * the generic MilestoneCarePlanService). Codes are ped_-prefixed because
 * treatment_catalog codes are unique per company across every specialty.
 */
class PediatricsModule implements SpecialtyModule
{
    public function key(): string
    {
        return Specialty::PEDIATRICS;
    }

    public function brandName(): string
    {
        return 'Pediavaria';
    }

    public function isBuilt(): bool
    {
        return true;
    }

    protected function catalogItems(): array
    {
        return [
            ['code' => 'ped_well_child_visit', 'name_ar' => 'فحص متابعة الطفل', 'name_en' => 'Well-Child Visit', 'name_tr' => 'Çocuk İzlem Muayenesi', 'default_price' => 400],
            ['code' => 'ped_vaccination_visit', 'name_ar' => 'زيارة تطعيم', 'name_en' => 'Vaccination Visit', 'name_tr' => 'Aşı Uygulaması', 'default_price' => 250],
            ['code' => 'ped_growth_assessment', 'name_ar' => 'تقييم النمو والتطور', 'name_en' => 'Growth & Development Assessment', 'name_tr' => 'Büyüme-Gelişim Değerlendirmesi', 'default_price' => 300],
            ['code' => 'ped_sick_visit', 'name_ar' => 'فحص طفل مريض', 'name_en' => 'Sick Child Visit', 'name_tr' => 'Hasta Çocuk Muayenesi', 'default_price' => 450],
        ];
    }

    public function seedCatalog(Company $company): void
    {
        $specialtyId = Specialty::query()->where('key', Specialty::PEDIATRICS)->value('id');

        foreach ($this->catalogItems() as $index => $item) {
            TreatmentCatalog::query()->updateOrCreate(
                ['company_id' => $company->id, 'code' => $item['code']],
                [
                    ...$item,
                    'company_id' => $company->id,
                    'specialty_id' => $specialtyId,
                    'scope' => TreatmentCatalog::SCOPE_COMPANY,
                    'sort_order' => $index + 1,
                    'is_active' => true,
                ]
            );
        }
    }
}
```

- [ ] **Step 4: CarePlanService** — Pediatri örneği (diğerleri: kilometre taşları referans tablodan, `clinical_data` anahtarı = field, plan başlığı: "Physiotherapy Session Plan" / "Blood Count Follow-up Plan" / "Perioperative Care Plan" / "General Follow-up Plan", summary öneki: "Diagnosis:" / "Diagnosis:" / "Planned operation:" / "Chief complaint:"):

```php
<?php

namespace App\Specialties\Pediatrics;

use App\Models\CarePlan;
use App\Models\Client;
use App\Models\Specialty;
use App\Models\User;
use App\Services\MilestoneCarePlanService;

/**
 * Pediavaria's well-child follow-up timeline, anchored to the child's
 * 1st-month visit date (not the birth date -- anchoring to birth would book
 * past-dated appointments for any older child). v1 prototype cadence
 * loosely following the Turkish childhood vaccination calendar; needs
 * review by a pediatrician before being treated as clinical guidance.
 */
class WellChildCarePlanService
{
    public function __construct(protected MilestoneCarePlanService $milestonePlans) {}

    protected function milestones(): array
    {
        return [
            ['day_offset' => 0, 'title' => '1st Month Well-Child Visit', 'catalog_code' => 'ped_well_child_visit'],
            ['day_offset' => 30, 'title' => '2nd Month Vaccination', 'catalog_code' => 'ped_vaccination_visit'],
            ['day_offset' => 90, 'title' => '4th Month Vaccination', 'catalog_code' => 'ped_vaccination_visit'],
            ['day_offset' => 150, 'title' => '6th Month Vaccination', 'catalog_code' => 'ped_vaccination_visit'],
            ['day_offset' => 240, 'title' => '9th Month Well-Child Visit', 'catalog_code' => 'ped_well_child_visit'],
            ['day_offset' => 330, 'title' => '12th Month Vaccination', 'catalog_code' => 'ped_vaccination_visit'],
            ['day_offset' => 510, 'title' => '18th Month Vaccination', 'catalog_code' => 'ped_vaccination_visit'],
            ['day_offset' => 690, 'title' => '24th Month Growth Assessment', 'catalog_code' => 'ped_growth_assessment'],
        ];
    }

    public function confirmPlan(Client $client, User $doctor, string $notes, string $startDate, string $preferredStartTime, int $userId): CarePlan
    {
        $specialty = Specialty::query()->where('key', Specialty::PEDIATRICS)->firstOrFail();

        $milestones = collect($this->milestones())->map(fn (array $milestone) => [
            ...$milestone,
            'clinical_data' => ['notes' => $notes],
        ])->all();

        return $this->milestonePlans->confirmPlan(
            $client,
            $doctor,
            $specialty,
            'Well-Child Follow-up Plan',
            $startDate,
            $preferredStartTime,
            $milestones,
            $userId,
            "Notes: {$notes}",
        );
    }
}
```

Klonlanan `{PlanPascal}Controller.php`'de `$request->validated('{field}')` doğru; mesajı düzelt: `'Select which doctor this plan is booked under.'` ve `'Care plan confirmed and appointments created.'`. Service import'unu `App\Specialties\{Pascal}\{PlanPascal}Service` yap (script `App\Specialties\Pediatrics\WellChildCarePlanService` üretir — kontrol et).

- [ ] **Step 5: Registry** — `SpecialtyModuleRegistry` constructor'ına 5 yeni parametre (`PediatricsModule $pediatrics, PhysiotherapyModule $physiotherapy, HematologyModule $hematology, GeneralSurgeryModule $generalSurgery, GeneralPracticeModule $generalPractice`), `use` satırları ve `$this->modules` girdileri; docblock "six" → "eleven".
- [ ] **Step 6: routes/api.php** — `use` satırları (5 controller), `rehab-care-plan` satırının altına:

```php
    Route::post('clients/{client}/well-child-care-plan/confirm', [WellChildCarePlanController::class, 'confirm']);
    Route::post('clients/{client}/physio-session-care-plan/confirm', [PhysioSessionCarePlanController::class, 'confirm']);
    Route::post('clients/{client}/blood-count-care-plan/confirm', [BloodCountCarePlanController::class, 'confirm']);
    Route::post('clients/{client}/perioperative-care-plan/confirm', [PerioperativeCarePlanController::class, 'confirm']);
    Route::post('clients/{client}/general-followup-care-plan/confirm', [GeneralFollowupCarePlanController::class, 'confirm']);
```
ve dosya sonundaki `require __DIR__.'/api/orthopedics.php';` hizasına 5 yeni `require`.

- [ ] **Step 7:** `php artisan test tests/Feature/Pediatrics tests/Feature/Physiotherapy tests/Feature/Hematology tests/Feature/GeneralSurgery tests/Feature/GeneralPractice` → AiConversationTest hariç PASS (AI sözlüğü Task 4'te).

### Task 4: AI profilleri

**Files:** Modify `app/Services/SpecialtyAiProfiles.php`

- [ ] **Step 1:** `procedureVocabulary()` match'ine 5 kol (`Specialty::PEDIATRICS => [['code' => 'ped_well_child_visit', 'name_en' => 'Well-Child Visit'], ...]` — referans kataloğun 4 kodu + EN adı). `domainLabel()`'a 5 kol (referans AI domain etiketleri). Docblock "4 non-dental" → "non-dental (except nutrition extras)".
- [ ] **Step 2:** Duplicate key kontrolü: `grep -c "Specialty::PEDIATRICS =>" app/Services/SpecialtyAiProfiles.php` → 2 (biri vocab, biri domain).
- [ ] **Step 3:** 5 uzmanlığın tüm feature testleri → PASS.

### Task 5: Landing, API docs, admin, logolar

**Files:**
- Modify: `app/Models/LandingPageContent.php` (SPECIALTIES, SPECIALTY_SLUGS, SPECIALTY_ACCENTS, `specialtyDefaultsFor` match, hub products ×3 dil, hub eyebrow "six" → "eleven", 15 yeni `{camel}{En,Ar,Tr}Defaults()`)
- Modify: `app/Support/ApiDocumentation.php::specialtyBrandName`
- Modify: `resources/views/landing.blade.php` (logo map + meta_description), `landing-specialty.blade.php`, `api-docs.blade.php`, `admin/landing-page/edit.blade.php` (brand map)
- Create: `public/brand/{slug}_logo.png` ×5 (kopya: `public/brand/doctovaria_logo.png`)
- Test: `tests/Feature/SpecialtyLandingPageTest.php`, `LandingPageContentSmokeTest.php`, `AdminLandingPageTest.php`, `ApiDocsPageTest.php`

- [ ] **Step 1: Test** — `SpecialtyLandingPageTest`'e:

```php
    public function test_every_new_specialty_page_renders_in_all_three_locales(): void
    {
        foreach (['pediavaria' => 'Pediavaria', 'physiovaria' => 'Physiovaria', 'hemavaria' => 'Hemavaria', 'surgivaria' => 'Surgivaria', 'genervaria' => 'Genervaria'] as $slug => $brand) {
            foreach (['en', 'ar', 'tr'] as $locale) {
                $this->get("/{$locale}/{$slug}")->assertOk()->assertSee($brand);
            }
        }
    }
```
(Önce dosyadaki mevcut bir testin gerçek URL şeklini oku; `/{slug}` + locale deseni farklıysa ona uydur.)
- [ ] **Step 2:** FAIL doğrula.
- [ ] **Step 3: Sabitler** — üç sabite referans tablodan key/slug/accent ekle; `specialtyDefaultsFor` match'ine 5 kol (`'pediatrics' => match ($locale) { 'ar' => static::pediatricsArDefaults(), 'tr' => static::pediatricsTrDefaults(), default => static::pediatricsEnDefaults() }`, camel: pediatrics, physiotherapy, hematology, generalSurgery, generalPractice).
- [ ] **Step 4: Defaults metodları** — `orthopedicsEnDefaults/ArDefaults/TrDefaults` gövdelerini birebir kopyala; ardından her kopyada: brand/e-posta (`hello@{slug}.com`), headline/footer tagline'daki branş adı, "rehab" geçen tüm feature/how_it_works/pricing feature satırları uzmanlığın bakım planına göre (Pediavaria: "well-child & vaccination follow-up plans"; Physiovaria: "physiotherapy session plans"; Hemavaria: "blood count follow-up plans"; Surgivaria: "perioperative care plans"; Genervaria: "general follow-up plans" — AR/TR karşılıklarıyla), testimonial klinik adları branşa uygun kurgusal isimlerle değiştirilir. Kontrol: `grep -n "rehab\|Orthovaria\|ortoped\|العظام" ` yeni metodların satır aralığında boş dönmeli.
- [ ] **Step 5: Hub** — 3 dilin `products` listesine 5 kart (tagline = branş adı, body = 1 cümle: bakım planı + öne çıkan araç), `eyebrow` "six/altı/ست" → "eleven/on bir/إحدى عشرة".
- [ ] **Step 6: Görünümler** — dört Blade dosyasındaki logo/brand match/map'lerine 5 satır (`'pediatrics' => 'pediavaria_logo.png'` vb.; admin edit'te `'pediatrics' => 'Pediavaria'`), landing meta_description listesine 5 branş. `ApiDocumentation::specialtyBrandName` match'ine 5 kol.
- [ ] **Step 7: Logolar**

```bash
for s in pediavaria physiovaria hemavaria surgivaria genervaria; do cp public/brand/doctovaria_logo.png public/brand/${s}_logo.png; done
```
- [ ] **Step 8:** Landing/admin/api-docs testleri → PASS.

### Task 6: Satış sayfaları

**Files:** Create `public/proposal-{slug}.html` ×5, `public/pitch-{slug}.html` ×5; Modify `public/proposal-doctovaria.html`, `public/pitch-doctovaria.html`

- [ ] **Step 1:** `proposal-orthovaria.html` ve `pitch-orthovaria.html`'yi 5 slug için kopyala; her kopyada brand, accent (referans tablo), logo yolu (`/brand/{slug}_logo.png`), branş adı ve bakım planı/klinik iş akışı metinleri (3 dil, sayfadaki mevcut dil yapısı neyse) uzmanlığa göre yeniden yazılır. Kontrol: `grep -ci "orthovaria\|rehab\|ortoped" public/{proposal,pitch}-{pediavaria,physiovaria,hemavaria,surgivaria,genervaria}.html` → hepsi 0.
- [ ] **Step 2:** Doctovaria genel proposal/pitch'teki ürün listesine/sayısına 5 ürün ekle ("six"/"6" → "eleven"/"11").
- [ ] **Step 3:** Her dosyayı tarayıcıda (file://) açıp görsel kırılma olmadığını kontrol et.

### Task 7: Frontend bağlantıları

**Files (Dental_FrontEnd/app/frontend):**
- Create: `src/specialties/{key}/theme.js` ×5 (`export default { accent: "<accent>" };`)
- Modify: `src/App.jsx`, `src/lib/api.js`, `src/context/AppStateApiContext.jsx`, `src/components/Sidebar.jsx`, `src/pages/LauncherPage.jsx`
- Modify: `../translations/{en,ar,tr}.json`
- Create: `public/brand/{slug}.png` ×5 (kopya: `public/brand/doctovaria.png`)

- [ ] **Step 1: api.js** — `orthopedics:` bloğunun altına her uzmanlık için (pediatri örneği):

```js
  pediatrics: {
    confirmWellChildPlan: (clientId, payload, token) =>
      request(`/clients/${clientId}/well-child-care-plan/confirm`, {
        method: "POST",
        body: payload,
        token,
      }),
    clients: {
      listPaginated: (query, token) =>
        request("/pediatrics/clients", { token, query }),
      create: (payload, token) =>
        request("/pediatrics/clients", { method: "POST", body: payload, token }),
    },
    appointments: {
      listPaginated: (query, token) =>
        request("/pediatrics/appointments", { token, query, raw: true }),
    },
    dashboard: {
      stats: (query, token) =>
        request("/pediatrics/dashboard/stats", { token, query }),
    },
    ai: buildSpecialtyAiApi("pediatrics"),
  },
```
- [ ] **Step 2: AppStateApiContext.jsx** — AI map'e 5 satır (`pediatrics: api.pediatrics.ai,`), `canAccessCosmetic` altına `const canAccessPediatrics = canAccessSpecialty("pediatrics");` ×5 ve context value'ya 5 export.
- [ ] **Step 3: App.jsx** — 25 import + 25 `<Route>` (`/{key}/patients|dashboard|appointments|client-details/:clientId|client-edit/:clientId?`), cosmetic route bloğunun altına.
- [ ] **Step 4: Sidebar.jsx / LauncherPage.jsx** — theme import'ları, `SPECIALTY_ACCENTS` ve `SPECIALTY_LOGOS` / launcher logo map'ine 5 satır (`/app/brand/{slug}.png`).
- [ ] **Step 5: Logolar** — `for s in pediavaria physiovaria hemavaria surgivaria genervaria; do cp public/brand/doctovaria.png public/brand/$s.png; done` (Dental_FrontEnd/app/frontend içinde; `public/brand/doctovaria.png` yoksa yolunu `find` ile bul).
- [ ] **Step 6: Çeviriler** — her dil dosyasına 20 anahtar (5 × `{planCamel}`, `{planCamel}Description`, `create{PlanShort}Plan`, `{fieldLabelKey}`). TR değerleri:
  - wellChildCarePlan "Çocuk İzlem Planı" / Description "1. ay izlem vizitinin tarihini girin; aşı ve izlem randevuları otomatik oluşturulur." / createWellChildPlan "İzlem Planı Oluştur" / wellChildNotes "Notlar"
  - physioSessionCarePlan "Fizyoterapi Seans Planı" / "Tanıyı ve başlangıç tarihini girin; değerlendirme ve seans takvimi oluşturulur." / createPhysioSessionPlan "Seans Planı Oluştur" / physioDiagnosis "Tanı"
  - bloodCountCarePlan "Kan Sayımı Takip Planı" / "Tanıyı ve başlangıç tarihini girin; hemogram kontrolleri planlanır." / createBloodCountPlan "Takip Planı Oluştur" / hematologyDiagnosis "Hematolojik Tanı"
  - perioperativeCarePlan "Perioperatif Bakım Planı" / "Planlanan ameliyatı ve ameliyat öncesi değerlendirme tarihini girin." / createPerioperativePlan "Perioperatif Plan Oluştur" / plannedOperation "Planlanan Ameliyat"
  - generalFollowupCarePlan "Genel Takip Planı" / "Şikayeti ve başlangıç tarihini girin; kontrol randevuları oluşturulur." / createGeneralFollowupPlan "Takip Planı Oluştur" / chiefComplaint "Başvuru Şikayeti"
  EN ve AR karşılıkları aynı anlamla yazılır.
- [ ] **Step 7: Kalıntı kontrolü** — `grep -rniE "orthoped|orthovaria|rehab|injury" src/specialties/{pediatrics,physiotherapy,hematology,general_surgery,general_practice}` → yalnızca yorum satırı kalmışsa düzelt, sonuç boş olmalı.
- [ ] **Step 8: Build** — `npm run lint` (yeni hata yok) ve `npm run build`; ardından `rm -rf /c/Users/MK/Desktop/Dental_Backend/public/app/assets && cp -r dist/* /c/Users/MK/Desktop/Dental_Backend/public/app/` (mevcut rutin; `public/app/brand` kaynağı Dental_FrontEnd `public/brand` olduğu için yeni logolar dist'e dahil olur — kopyadan sonra `ls public/app/brand` ile 5 yeni dosyayı doğrula).

### Task 8: Tam doğrulama

- [ ] **Step 1:** `composer run test` — tüm suite. "six"/6 sayısına bağlı kırılan testleri (ör. launcher/specialty listesi, `SubscriptionControllerTest`, `DemoDataSeederTest`) 11'e göre güncelle; dental/nutrition davranış testleri değiştirilmez.
- [ ] **Step 2:** `./vendor/bin/pint --dirty`.
- [ ] **Step 3:** Yerelde `php artisan serve` + `/app` üzerinden: bir test şirketine 5 yeni uzmanlık aboneliği ver (admin panel), launcher'da 5 kutucuk + Doctovaria logosu, her birinde hasta ekle → bakım planı oluştur → randevular listede. `/{locale}/{slug}` landing sayfaları ve admin landing editöründe 5 yeni sekme.
- [ ] **Step 4:** Kullanıcıya rapor: yapılanlar, test çıktısı, canlıya çıkış için `migrate.php` (SpecialtySeeder'ı çalıştırır) + WinSCP senkronu gerektiği; commit/push onayı sor.
