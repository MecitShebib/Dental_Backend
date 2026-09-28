<?php

namespace App\Models;

use App\Enums\AppointmentStatus;
use App\Enums\AppointmentType;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\HasUuid;
use App\Services\TreatmentChargeService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Appointment extends Model
{
    use Auditable, BelongsToCompany, HasFactory, HasUuid, SoftDeletes;

    protected $fillable = [
        'uuid',
        'company_id',
        'client_id',
        'doctor_id',
        'type',
        'booked_online',
        'status',
        'reminder_sent_at',
        'reminder_claimed_at',
        'date',
        'start_time',
        'duration_minutes',
        'end_time',
        'notes',
        'planned_summary',
        'planned_notes',
        'planned_image_path',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'type' => AppointmentType::class,
            'booked_online' => 'boolean',
            'status' => AppointmentStatus::class,
            'date' => 'date',
            'duration_minutes' => 'integer',
            'reminder_sent_at' => 'datetime',
            'reminder_claimed_at' => 'datetime',
        ];
    }

    /**
     * Cancelling an appointment has to take its money with it: nothing was
     * performed, so the treatment_charges rows it owns must not keep sitting
     * on the patient's balance. destroy() and ClientVisitController::noShow()
     * already did this explicitly, but a plain `status = cancelled` update
     * did not -- and there are six per-specialty AppointmentControllers with
     * an identical update() action, so fixing it per-controller would mean
     * six copies of the same rule with six chances to miss the next one.
     * Doing it as a model event instead means every current and future code
     * path that flips the status is covered, whatever authorization or
     * validation the controller layers on top (those run before the model is
     * ever saved, so they can't be bypassed by this).
     *
     * Its mirror image lives in TreatmentChargeService::syncItems(), which
     * refuses to (re-)create charges for an already-cancelled appointment --
     * needed because an update() request can carry both `status=cancelled`
     * and a `charge_items` payload, and the controller syncs those items
     * after the save that fires this event.
     */
    protected static function booted(): void
    {
        static::updated(function (Appointment $appointment): void {
            if (! $appointment->wasChanged('status')) {
                return;
            }

            if ($appointment->status !== AppointmentStatus::Cancelled) {
                return;
            }

            app(TreatmentChargeService::class)->deleteAllForAppointment(
                $appointment->id,
                $appointment->company_id,
            );
        });
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'doctor_id');
    }

    public function visit(): HasOne
    {
        return $this->hasOne(Visit::class);
    }

    public function labCase(): HasOne
    {
        return $this->hasOne(LabCase::class);
    }
}
