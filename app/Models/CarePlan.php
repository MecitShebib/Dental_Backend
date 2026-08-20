<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Generalized counterpart to the dental-only AI treatment plan flow
 * (AiTreatmentPlanService/Appointment.planned_*): a specialty-agnostic
 * "plan → multi-session follow-up" record. Dental itself does NOT use this
 * yet -- its existing, already-tested flow was left untouched; this exists
 * so a future specialty module (gynecology's prenatal plan, orthopedics'
 * injury/rehab plan, etc.) has somewhere to plug in without inventing its
 * own parallel tables.
 */
class CarePlan extends Model
{
    use BelongsToCompany;
    use HasFactory;
    use HasUuid;

    public const STATUS_CONFIRMED = 'confirmed';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'company_id',
        'specialty_id',
        'client_id',
        'doctor_id',
        'created_by',
        'title',
        'summary',
        'status',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function specialty(): BelongsTo
    {
        return $this->belongsTo(Specialty::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'doctor_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(CarePlanSession::class)->orderBy('session_index');
    }
}
