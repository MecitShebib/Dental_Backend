<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Who touched (viewed/created/updated/deleted/exported) which personal-data
 * record and when -- the access trail KVKK m.12 ("veri güvenliğine ilişkin
 * yükümlülükler") expects a data controller/processor to be able to produce.
 * Written by the Auditable trait (see app/Models/Concerns/Auditable.php)
 * plus a few explicit calls for read/export actions that have no Eloquent
 * event of their own.
 */
class AuditLog extends Model
{
    use HasUuid;

    public $timestamps = false;

    /**
     * auditable_type => category slug, for the activity log's category
     * filter and display column. Every model that uses the Auditable trait
     * must appear here (see ActivityLogController); an unmapped type falls
     * back to 'other' rather than breaking the list.
     */
    public const CATEGORY_MODELS = [
        'patient' => [Client::class],
        'appointment' => [Appointment::class],
        'visit' => [Visit::class],
        'payment' => [Payment::class],
        'prescription' => [Prescription::class],
        'lab' => [LabCase::class, LabPayment::class, PatientLabResult::class],
        'xray' => [XrayImage::class],
        'consent' => [ClientConsent::class],
        'accounting' => [Expense::class, CapitalTransaction::class, SalaryPayment::class, SalaryAdvance::class],
        'user' => [User::class],
        // Every specialty's per-visit clinical record/profile model (odontogram
        // notes, vitals, growth measurements, ultrasound exams, ...) -- bucketed
        // together since the activity log's category filter doesn't need to
        // distinguish between them, only between "clinical" and everything else.
        'clinical' => [
            TreatmentRecord::class,
            NutritionClientProfile::class, NutritionBodyMetric::class,
            GynecologyClientProfile::class, GynecologyUltrasoundExam::class,
            InternalMedicineClientProfile::class, InternalMedicineVital::class,
            OrthopedicsClientProfile::class, OrthopedicsAssessment::class,
            CosmeticClientProfile::class, CosmeticProcedureLog::class,
            PediatricsClientProfile::class, PediatricsGrowthMeasurement::class, PediatricsVaccination::class,
            PhysiotherapyClientProfile::class, PhysiotherapySession::class,
            HematologyClientProfile::class, HematologyBloodCount::class, HematologyTransfusion::class,
            GeneralSurgeryClientProfile::class, GeneralSurgeryOperation::class, GeneralSurgeryFollowup::class,
            GeneralPracticeClientProfile::class, GeneralPracticeVital::class, GeneralPracticeReferral::class,
        ],
    ];

    protected $fillable = [
        'uuid',
        'company_id',
        'user_id',
        'action',
        'auditable_type',
        'auditable_id',
        'client_id',
        'ip_address',
        'user_agent',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'meta' => 'array',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (AuditLog $log) {
            $log->created_at ??= now();
        });
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Not a real Eloquent relation constraint (no FK -- see the migration),
     * just a plain belongsTo on the denormalized client_id column.
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public static function record(string $action, $auditable, ?User $actor = null, ?array $meta = null): self
    {
        $request = request();

        return static::create([
            'company_id' => $actor?->company_id ?? $auditable->company_id ?? null,
            'user_id' => $actor?->id,
            'action' => $action,
            'auditable_type' => $auditable::class,
            'auditable_id' => $auditable->id,
            'client_id' => static::resolveClientId($auditable),
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
            'meta' => $meta,
        ]);
    }

    public static function categoryFor(string $auditableType): string
    {
        foreach (static::CATEGORY_MODELS as $category => $types) {
            if (in_array($auditableType, $types, true)) {
                return $category;
            }
        }

        return 'other';
    }

    /**
     * A model gets a free client_id resolution if it IS the Client, or has
     * a client_id attribute of its own; anything needing an extra hop (e.g.
     * LabPayment -> its LabCase -> client_id) defines auditClientId() itself.
     */
    protected static function resolveClientId($auditable): ?int
    {
        if ($auditable instanceof Client) {
            return $auditable->id;
        }

        if (method_exists($auditable, 'auditClientId')) {
            return $auditable->auditClientId();
        }

        return $auditable->client_id ?? null;
    }
}
