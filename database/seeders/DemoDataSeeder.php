<?php

namespace Database\Seeders;

use App\Enums\AppointmentStatus;
use App\Enums\AppointmentType;
use App\Enums\AttendanceStatus;
use App\Enums\CapitalTransactionType;
use App\Enums\CariCurrency;
use App\Enums\CariPartyType;
use App\Enums\CariTransactionType;
use App\Enums\ExpenseCategory;
use App\Enums\LabCaseStatus;
use App\Enums\LabCaseWorkType;
use App\Enums\PaymentMethod;
use App\Models\Appointment;
use App\Models\Branch;
use App\Models\CapitalTransaction;
use App\Models\CariParty;
use App\Models\Client;
use App\Models\ClientConsent;
use App\Models\Company;
use App\Models\ConsentTemplate;
use App\Models\DoctorSchedule;
use App\Models\Expense;
use App\Models\FundTransaction;
use App\Models\InventoryItem;
use App\Models\LabCase;
use App\Models\LabPartner;
use App\Models\PatientLabResult;
use App\Models\Payment;
use App\Models\Role;
use App\Models\SalaryPayment;
use App\Models\Specialty;
use App\Models\Subscription;
use App\Models\TreatmentCatalog;
use App\Models\TreatmentCharge;
use App\Models\User;
use App\Models\Visit;
use App\Services\CarePlanService;
use App\Services\CariLedgerService;
use App\Services\ClientSpecialtyEnrollmentService;
use App\Services\ConsentService;
use App\Services\FundTransactionService;
use App\Services\InvoiceService;
use App\Services\TreatmentChargeService;
use App\Specialties\Cosmetic\CosmeticCarePlanService;
use App\Specialties\Gynecology\PrenatalCarePlanService;
use App\Specialties\InternalMedicine\ChronicCarePlanService;
use App\Specialties\Orthopedics\RehabCarePlanService;
use App\Specialties\SpecialtyModuleRegistry;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Local-dev-only convenience seeder: gives every one of the 5 specialties
 * (dental + gynecology/internal_medicine/orthopedics/cosmetic) at least one
 * real record in every section of the app -- a patient, an upcoming
 * appointment, a past visit with charges, a payment + invoice, a care plan,
 * a lab record, plus one shared set of company-wide accounting records
 * (expense, capital movement, payroll, cari ledger) and settings data
 * (consent template, inventory item, branch). Built so a developer can spin
 * up a fresh local DB and immediately click through every screen this
 * session's Doctovaria work touched, instead of hitting empty states.
 *
 * DELIBERATELY NOT called from DatabaseSeeder::run() -- that seeder is
 * invoked on every production deploy via public/migrate.php, and this data
 * (fake patients, fake payments, fake payroll) must never reach the live
 * customer-facing database. Run it explicitly and only locally:
 *
 *   php artisan db:seed --class=DemoDataSeeder
 *
 * Idempotent: every top-level record is looked up by a stable natural key
 * first, and the expensive "create a full clinical history" block per
 * specialty only runs the first time that specialty's demo client is
 * actually created (via Client::wasRecentlyCreated) -- safe to run this
 * seeder repeatedly without piling up duplicate patients/appointments/visits.
 */
class DemoDataSeeder extends Seeder
{
    public function __construct(
        protected ClientSpecialtyEnrollmentService $enrollment,
        protected TreatmentChargeService $treatmentCharges,
        protected FundTransactionService $fundTransactions,
        protected InvoiceService $invoices,
        protected CariLedgerService $cariLedger,
        protected ConsentService $consents,
        protected SpecialtyModuleRegistry $specialtyModules,
        protected CarePlanService $carePlans,
        protected PrenatalCarePlanService $prenatalCarePlans,
        protected ChronicCarePlanService $chronicCarePlans,
        protected RehabCarePlanService $rehabCarePlans,
        protected CosmeticCarePlanService $cosmeticCarePlans,
    ) {}

    /**
     * A 1x1 transparent PNG -- a syntactically real signature image (so
     * ConsentService's storeSignature() writes a genuine, loadable file
     * instead of a fake path a frontend <img> would show broken), not a
     * placeholder string.
     */
    protected const BLANK_SIGNATURE_DATA_URL = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=';

    public function run(): void
    {
        // Ensures roles/specialties/dental catalog/the demo company/admin/
        // doctor already exist -- everything below builds on top of that.
        $this->call([DatabaseSeeder::class]);

        $company = Company::where('code', 'DENTAL-HQ')->firstOrFail();
        $systemManager = User::where('email', 'admin@clinic.com')->firstOrFail();
        $doctorRole = Role::where('slug', 'doctor')->firstOrFail();

        // BelongsToCompany-trait models (Client/Appointment/CarePlan/Expense/
        // CapitalTransaction/SalaryPayment/CariParty/CariTransaction/
        // ConsentTemplate/InventoryItem/LabPartner/Branch -- see each
        // model's own trait) auto-fill company_id from auth('sanctum')->user()
        // on create. There's no HTTP request/token here to resolve that
        // from, so every one of those creates below would fail its NOT NULL
        // company_id constraint without this -- fake the guard's resolved
        // user for the rest of this console process.
        Auth::guard('sanctum')->setUser($systemManager);

        $specialtyConfigs = [
            Specialty::DENTAL => [
                'doctor_email' => 'doctor@clinic.com',
                'doctor_name' => null, // already seeded by DatabaseSeeder
                'doctor_phone' => null,
                'client_code' => 'DEMO-DEN-001',
                'client_name' => 'Yasmin Al-Ahmad',
                'client_phone' => '+963900030001',
                'client_gender' => 'female',
                'catalog_codes' => ['consultation', 'filling'],
            ],
            Specialty::GYNECOLOGY => [
                'doctor_email' => 'doctor.gynecology@clinic.com',
                'doctor_name' => 'Dr. Sara Nasser',
                'doctor_phone' => '+963900020001',
                'client_code' => 'DEMO-GYN-001',
                'client_name' => 'Rana Kassem',
                'client_phone' => '+963900030002',
                'client_gender' => 'female',
                'catalog_codes' => ['prenatal_checkup', 'ultrasound'],
            ],
            Specialty::INTERNAL_MEDICINE => [
                'doctor_email' => 'doctor.internalmedicine@clinic.com',
                'doctor_name' => 'Dr. Omar Haddad',
                'doctor_phone' => '+963900020002',
                'client_code' => 'DEMO-IM-001',
                'client_name' => 'Samir Aziz',
                'client_phone' => '+963900030003',
                'client_gender' => 'male',
                'catalog_codes' => ['chronic_initial_assessment', 'lab_panel'],
            ],
            Specialty::ORTHOPEDICS => [
                'doctor_email' => 'doctor.orthopedics@clinic.com',
                'doctor_name' => 'Dr. Khaled Youssef',
                'doctor_phone' => '+963900020003',
                'client_code' => 'DEMO-ORTHO-001',
                'client_name' => 'Tarek Fares',
                'client_phone' => '+963900030004',
                'client_gender' => 'male',
                'catalog_codes' => ['ortho_assessment', 'followup_xray'],
            ],
            Specialty::COSMETIC => [
                'doctor_email' => 'doctor.cosmetic@clinic.com',
                'doctor_name' => 'Dr. Lina Saab',
                'doctor_phone' => '+963900020004',
                'client_code' => 'DEMO-COS-001',
                'client_name' => 'Maya Chami',
                'client_phone' => '+963900030005',
                'client_gender' => 'female',
                'catalog_codes' => ['cosmetic_consultation', 'botox_session'],
            ],
        ];

        // Doctors + schedules first (care-plan/appointment creation below
        // needs every doctor's schedule to already exist).
        $doctors = [];
        foreach ($specialtyConfigs as $specialtyKey => $config) {
            $specialty = Specialty::where('key', $specialtyKey)->firstOrFail();
            $doctors[$specialtyKey] = $this->ensureDoctor($company, $specialty, $doctorRole, $config);
            $this->ensureSchedule($doctors[$specialtyKey]);
        }

        // Dental's own subscription + catalog already exist (DatabaseSeeder
        // -> TreatmentCatalogSeeder); the other 4 specialties need both.
        foreach ([Specialty::GYNECOLOGY, Specialty::INTERNAL_MEDICINE, Specialty::ORTHOPEDICS, Specialty::COSMETIC] as $specialtyKey) {
            $specialty = Specialty::where('key', $specialtyKey)->firstOrFail();
            $this->ensureSubscription($company, $specialty);
            $this->specialtyModules->get($specialtyKey)?->seedCatalog($company);
        }

        $consentTemplate = $this->ensureConsentTemplate($company);

        foreach ($specialtyConfigs as $specialtyKey => $config) {
            $specialty = Specialty::where('key', $specialtyKey)->firstOrFail();
            $doctor = $doctors[$specialtyKey];
            $client = $this->ensureClient($company, $config);

            if (! $client->wasRecentlyCreated) {
                continue;
            }

            $this->enrollment->ensureEnrolled($client, $doctor);
            $visit = $this->seedVisitAndPayment($company, $client, $doctor, $specialty, $config, $systemManager->id);
            $this->seedAppointment($client, $doctor, $specialty, $config, $systemManager->id);
            $this->seedCarePlan($specialtyKey, $client, $doctor, $specialty, $systemManager->id);
            $this->seedLab($specialtyKey, $company, $client, $doctor, $specialty, $systemManager->id);
            $this->seedConsent($client, $consentTemplate, $visit, $doctor);
        }

        $this->seedAccounting($company, $doctors[Specialty::DENTAL], $systemManager->id);
        $this->seedInventoryAndBranch($company);

        // forgetGuards() (not setUser(null), which the guard's type signature
        // rejects) forces a fresh re-resolution next time anything calls
        // auth('sanctum') -- required for real idempotency: without this, a
        // second invocation within the same process (e.g. this seeder's own
        // idempotency test calling it twice, or a queue worker) would still
        // see this faked user, and DatabaseSeeder::run()'s project-admin
        // user (deliberately company_id=null) would then get filtered out by
        // BelongsToCompany's scope and look like it needs re-creating --
        // colliding with its own still-there row on the email unique index.
        Auth::forgetGuards();
    }

    protected function ensureDoctor(Company $company, Specialty $specialty, Role $doctorRole, array $config): User
    {
        $doctor = User::where('email', $config['doctor_email'])->first();

        if (! $doctor) {
            $doctor = User::create([
                'company_id' => $company->id,
                'uuid' => (string) Str::uuid(),
                'name' => $config['doctor_name'],
                'email' => $config['doctor_email'],
                'phone' => $config['doctor_phone'],
                'password' => Hash::make('secret'),
                'job_title' => 'Doctor',
                'branch_name' => 'Damascus',
                'status' => 'active',
                'is_project_admin' => false,
                'is_doctor' => true,
                'specialty_id' => $specialty->id,
                'monthly_salary' => 15000,
            ]);
        } elseif (! $doctor->specialty_id) {
            // Covers dental's "Dr. Layan" (seeded by DatabaseSeeder before
            // specialty_id existed on doctors) -- was never backfilled.
            $doctor->update(['specialty_id' => $specialty->id]);
        }

        $doctor->roles()->syncWithoutDetaching([$doctorRole->id]);

        return $doctor;
    }

    protected function ensureSchedule(User $doctor): void
    {
        $schedule = DoctorSchedule::firstOrCreate(
            ['doctor_id' => $doctor->id],
            ['start_time' => '09:00:00', 'end_time' => '17:00:00', 'slot_minutes' => 30],
        );

        foreach (['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'] as $weekday) {
            $schedule->workingDays()->firstOrCreate(['weekday' => $weekday]);
        }
    }

    protected function ensureSubscription(Company $company, Specialty $specialty): Subscription
    {
        return Subscription::firstOrCreate(
            ['company_id' => $company->id, 'specialty_id' => $specialty->id],
            [
                'plan_name' => "{$specialty->brand_name} Plan",
                'status' => 'active',
                'starts_at' => now()->subMonth()->toDateString(),
                'ends_at' => now()->addYear()->toDateString(),
                'max_users' => 10,
                'active_users' => 1,
                'price' => 0,
                'notes' => 'Seeded demo subscription',
            ],
        );
    }

    protected function ensureClient(Company $company, array $config): Client
    {
        return Client::firstOrCreate(
            ['client_code' => $config['client_code']],
            [
                'company_id' => $company->id,
                'uuid' => (string) Str::uuid(),
                'name' => $config['client_name'],
                'phone' => $config['client_phone'],
                'gender' => $config['client_gender'],
                'status' => 'under_treatment',
                'city' => 'Damascus',
            ],
        );
    }

    /**
     * @return array<int, array{description: string, amount: float, treatment_catalog_id: int}>
     */
    protected function chargeItemsFor(Company $company, Specialty $specialty, array $codes): array
    {
        return TreatmentCatalog::query()
            ->where('company_id', $company->id)
            ->where('specialty_id', $specialty->id)
            ->whereIn('code', $codes)
            ->get()
            ->map(fn (TreatmentCatalog $item) => [
                'description' => $item->name_en ?? $item->name_ar ?? $item->name_tr,
                'amount' => (float) $item->default_price,
                'treatment_catalog_id' => $item->id,
            ])
            ->values()
            ->all();
    }

    /**
     * A completed, already-paid(-partially) visit -- the "clinical history"
     * every specialty's Payments/Financial Summary screens need at least one
     * of. Not linked to any appointment (a walk-in), independent from the
     * separate upcoming Appointment seedAppointment() creates below.
     */
    protected function seedVisitAndPayment(Company $company, Client $client, User $doctor, Specialty $specialty, array $config, int $userId): Visit
    {
        $chargeItems = $this->chargeItemsFor($company, $specialty, $config['catalog_codes']);
        $totalAmount = array_sum(array_column($chargeItems, 'amount'));

        $visit = Visit::create([
            'uuid' => (string) Str::uuid(),
            'client_id' => $client->id,
            'doctor_id' => $doctor->id,
            'appointment_id' => null,
            'visit_date' => now()->subDay()->toDateString(),
            'start_time' => '10:00:00',
            'duration_minutes' => 30,
            'notes' => 'Seeded demo visit.',
            'attendance_status' => AttendanceStatus::WalkIn->value,
            'created_by' => $userId,
            'updated_by' => $userId,
        ]);

        $this->treatmentCharges->syncItems($client, TreatmentCharge::SOURCE_VISIT, $visit->id, $chargeItems);

        // Deliberately a partial payment (half the total) so the
        // remaining-balance/financial-summary screens have something to show
        // too, not just a fully-settled zero-balance client.
        $paymentAmount = round($totalAmount / 2, 2);

        if ($paymentAmount > 0) {
            $payment = Payment::create([
                'uuid' => (string) Str::uuid(),
                'client_id' => $client->id,
                'visit_id' => $visit->id,
                'payment_date' => now()->subDay()->toDateString(),
                'amount' => $paymentAmount,
                'payment_method' => PaymentMethod::Cash->value,
                'notes' => 'Seeded demo payment.',
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);

            $this->fundTransactions->post(
                $company,
                FundTransaction::SOURCE_PAYMENT,
                $payment->id,
                (float) $payment->amount,
                "Payment from {$client->name}",
                $payment->payment_date,
                $userId,
            );

            $this->invoices->createForPayment($payment);
        }

        return $visit;
    }

    /**
     * A separate, still-upcoming Appointment (distinct from any care-plan-
     * generated ones) so the Appointments board/tab has a real "scheduled"
     * row to check in, not just past history.
     */
    protected function seedAppointment(Client $client, User $doctor, Specialty $specialty, array $config, int $userId): Appointment
    {
        $chargeItems = $this->chargeItemsFor($client->company, $specialty, $config['catalog_codes']);

        $appointment = Appointment::create([
            'uuid' => (string) Str::uuid(),
            'client_id' => $client->id,
            'doctor_id' => $doctor->id,
            'type' => AppointmentType::Booked->value,
            'status' => AppointmentStatus::Scheduled->value,
            'date' => now()->addDays(3)->toDateString(),
            'start_time' => '09:00:00',
            'duration_minutes' => 30,
            'end_time' => '09:30:00',
            'notes' => 'Seeded demo appointment.',
            'created_by' => $userId,
            'updated_by' => $userId,
        ]);

        $this->treatmentCharges->syncItems($client, TreatmentCharge::SOURCE_APPOINTMENT, $appointment->id, $chargeItems);

        return $appointment;
    }

    /**
     * All 4 non-dental sessions land at 11:00 (vs. seedAppointment()'s
     * 09:00) so they never collide with that separate manual appointment
     * regardless of which calendar day they land on.
     */
    protected function seedCarePlan(string $specialtyKey, Client $client, User $doctor, Specialty $specialty, int $userId): void
    {
        match ($specialtyKey) {
            Specialty::GYNECOLOGY => $this->prenatalCarePlans->confirmPlan(
                $client,
                $doctor,
                now()->toDateString(),
                '11:00',
                $userId,
            ),
            Specialty::INTERNAL_MEDICINE => $this->chronicCarePlans->confirmPlan(
                $client,
                $doctor,
                'Type 2 Diabetes Mellitus',
                now()->addDay()->toDateString(),
                '11:00',
                $userId,
            ),
            Specialty::ORTHOPEDICS => $this->rehabCarePlans->confirmPlan(
                $client,
                $doctor,
                'Left ACL Reconstruction Rehab',
                now()->addDay()->toDateString(),
                '11:00',
                $userId,
            ),
            Specialty::COSMETIC => $this->cosmeticCarePlans->confirmPlan(
                $client,
                $doctor,
                'botox_session',
                3,
                30,
                now()->addDay()->toDateString(),
                '11:00',
                $userId,
            ),
            Specialty::DENTAL => $this->carePlans->confirmPlan(
                $client,
                $doctor,
                $specialty,
                'Whitening Follow-up Plan',
                [
                    [
                        'date' => now()->addDays(7)->toDateString(),
                        'start_time' => '11:00',
                        'duration_minutes' => 30,
                        'title' => 'Consultation',
                        'charge_items' => $this->chargeItemsFor($client->company, $specialty, ['consultation']),
                    ],
                    [
                        'date' => now()->addDays(14)->toDateString(),
                        'start_time' => '11:00',
                        'duration_minutes' => 30,
                        'title' => 'Filling',
                        'charge_items' => $this->chargeItemsFor($client->company, $specialty, ['filling']),
                    ],
                ],
                $userId,
                'Seeded demo dental care plan.',
            ),
            default => null,
        };
    }

    /**
     * Dental gets a real LabCase (its own outsourced-prosthetics workflow --
     * see LabCase's docblock); the 4 other specialties get a PatientLabResult
     * instead (the generic test/analysis record built for them -- see
     * PatientLabResultController's docblock for why these are separate).
     */
    protected function seedLab(string $specialtyKey, Company $company, Client $client, User $doctor, Specialty $specialty, int $userId): void
    {
        if ($specialtyKey === Specialty::DENTAL) {
            $labPartner = LabPartner::firstOrCreate(
                ['company_id' => $company->id, 'name' => 'Istanbul Dental Lab'],
                ['phone' => '+905551234567', 'email' => 'lab@istanbuldentallab.example', 'is_active' => true, 'created_by' => $userId],
            );

            LabCase::create([
                'uuid' => (string) Str::uuid(),
                'client_id' => $client->id,
                'doctor_id' => $doctor->id,
                'lab_partner_id' => $labPartner->id,
                'work_type' => LabCaseWorkType::Crown->value,
                'teeth' => ['16'],
                'material' => 'Zirconia',
                'shade' => 'A2',
                'status' => LabCaseStatus::Sent->value,
                'sent_date' => now()->toDateString(),
                'expected_return_date' => now()->addDays(10)->toDateString(),
                'lab_cost' => 6000,
                'notes' => 'Seeded demo lab case.',
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);

            return;
        }

        [$testName, $resultValue, $unit, $referenceRange, $isAbnormal] = match ($specialtyKey) {
            Specialty::GYNECOLOGY => ['Hemoglobin', '11.5', 'g/dL', '12-16', true],
            Specialty::INTERNAL_MEDICINE => ['HbA1c', '7.2', '%', '4.0-5.6', true],
            Specialty::ORTHOPEDICS => ['Knee X-Ray', 'Mild joint space narrowing', null, null, true],
            Specialty::COSMETIC => ['Skin Allergy Patch Test', 'Negative', null, null, false],
        };

        PatientLabResult::create([
            'uuid' => (string) Str::uuid(),
            'client_id' => $client->id,
            'specialty_id' => $specialty->id,
            'doctor_id' => $doctor->id,
            'test_name' => $testName,
            'result_value' => $resultValue,
            'unit' => $unit,
            'reference_range' => $referenceRange,
            'is_abnormal' => $isAbnormal,
            'test_date' => now()->subDay()->toDateString(),
            'notes' => 'Seeded demo lab result.',
            'created_by' => $userId,
            'updated_by' => $userId,
        ]);
    }

    protected function ensureConsentTemplate(Company $company): ConsentTemplate
    {
        return ConsentTemplate::firstOrCreate(
            ['company_id' => $company->id, 'title' => 'General Treatment Consent'],
            [
                'body' => 'I, {client_name}, consent to receive treatment at {company_name} on {date}.',
                'sections' => [
                    ['heading' => 'Risks', 'body' => 'I understand every procedure carries some risk, explained to me by my doctor.'],
                    ['heading' => 'Confirmation', 'body' => 'I confirm the information I provided is accurate to the best of my knowledge.'],
                ],
                'language' => 'en',
                'is_active' => true,
            ],
        );
    }

    protected function seedConsent(Client $client, ConsentTemplate $template, Visit $visit, User $doctor): void
    {
        if (ClientConsent::where('client_id', $client->id)->exists()) {
            return;
        }

        $this->consents->sign($client, $template, self::BLANK_SIGNATURE_DATA_URL, $visit, $doctor, '127.0.0.1');
    }

    /**
     * Company-wide (not per-specialty) accounting records -- Expense/
     * CapitalTransaction/SalaryPayment/CariParty+CariTransaction aren't
     * specialty-scoped in this app, so "at least one" only needs one of each
     * for the whole company, not one per specialty.
     */
    protected function seedAccounting(Company $company, User $dentalDoctor, int $userId): void
    {
        if (! Expense::where('company_id', $company->id)->exists()) {
            $expense = Expense::create([
                'uuid' => (string) Str::uuid(),
                'category' => ExpenseCategory::DentalSupplies->value,
                'vendor_name' => 'Demo Dental Supply Co.',
                'amount' => 1200,
                'expense_date' => now()->subDays(2)->toDateString(),
                'description' => 'Seeded demo expense.',
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);

            $this->fundTransactions->post(
                $company,
                FundTransaction::SOURCE_EXPENSE,
                $expense->id,
                -1 * (float) $expense->amount,
                $expense->description,
                $expense->expense_date,
                $userId,
            );
        }

        if (! CapitalTransaction::where('company_id', $company->id)->exists()) {
            $capital = CapitalTransaction::create([
                'uuid' => (string) Str::uuid(),
                'type' => CapitalTransactionType::Injection->value,
                'amount' => 50000,
                'party_name' => 'Company Owner',
                'transaction_date' => now()->subMonth()->toDateString(),
                'description' => 'Seeded demo capital injection.',
                'created_by' => $userId,
            ]);

            $this->fundTransactions->post(
                $company,
                FundTransaction::SOURCE_CAPITAL,
                $capital->id,
                (float) $capital->amount,
                $capital->description,
                $capital->transaction_date,
                $userId,
            );
        }

        if (! SalaryPayment::where('company_id', $company->id)->exists()) {
            $now = now();
            $salaryPayment = SalaryPayment::create([
                'uuid' => (string) Str::uuid(),
                'user_id' => $dentalDoctor->id,
                'period_year' => $now->year,
                'period_month' => $now->month,
                'base_salary' => (float) $dentalDoctor->monthly_salary,
                'net_amount' => (float) $dentalDoctor->monthly_salary,
                'paid_at' => $now->toDateString(),
                'created_by' => $userId,
            ]);

            $this->fundTransactions->post(
                $company,
                FundTransaction::SOURCE_SALARY_PAYMENT,
                $salaryPayment->id,
                -1 * (float) $salaryPayment->net_amount,
                "Salary payment for {$dentalDoctor->name}",
                $salaryPayment->paid_at,
                $userId,
            );
        }

        if (! CariParty::where('company_id', $company->id)->exists()) {
            $party = CariParty::create([
                'uuid' => (string) Str::uuid(),
                'type' => CariPartyType::Supplier->value,
                'name' => 'Demo Medical Supplies Trading',
                'is_active' => true,
                'created_by' => $userId,
            ]);

            $this->cariLedger->post(
                $company,
                $party,
                1000,
                0,
                CariCurrency::TRY->value,
                1,
                CariTransactionType::Invoice->value,
                'Seeded demo cari invoice.',
                now()->subDays(3)->toDateString(),
                null,
                null,
                null,
                null,
                $userId,
            );
        }
    }

    protected function seedInventoryAndBranch(Company $company): void
    {
        InventoryItem::firstOrCreate(
            ['company_id' => $company->id, 'name' => 'Disposable Gloves (Box)'],
            ['unit' => 'box', 'quantity_on_hand' => 50, 'reorder_threshold' => 10, 'unit_cost' => 25, 'status' => 'active'],
        );

        Branch::firstOrCreate(
            ['company_id' => $company->id, 'name' => 'Damascus Main Branch'],
            ['address' => 'Damascus, Syria', 'phone' => '+963110000001', 'status' => 'active'],
        );
    }
}
