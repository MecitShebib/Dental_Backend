<?php

namespace App\Models;

use App\Enums\UserStatus;
use App\Models\Concerns\HasUuid;
// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasUuid, Notifiable, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'uuid',
        'company_id',
        'name',
        'email',
        'phone',
        'password',
        'job_title',
        'branch_name',
        'status',
        'is_project_admin',
        'is_doctor',
        'notes',
        'last_login_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'status' => UserStatus::class,
            'is_project_admin' => 'boolean',
            'is_doctor' => 'boolean',
            'company_id' => 'integer',
        ];
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class);
    }

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'permission_user');
    }

    public function doctorSchedule(): HasOne
    {
        return $this->hasOne(DoctorSchedule::class, 'doctor_id');
    }

    public function clientsCreated(): HasMany
    {
        return $this->hasMany(Client::class, 'created_by');
    }

    public function clientsUpdated(): HasMany
    {
        return $this->hasMany(Client::class, 'updated_by');
    }

    public function otps(): HasMany
    {
        return $this->hasMany(UserOtp::class);
    }

    public function appointmentsAsDoctor(): HasMany
    {
        return $this->hasMany(Appointment::class, 'doctor_id');
    }

    public function visitsAsDoctor(): HasMany
    {
        return $this->hasMany(Visit::class, 'doctor_id');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function currentSubscription(): ?Subscription
    {
        return $this->company?->currentSubscription;
    }

    public function isActive(): bool
    {
        return ($this->status?->value ?? $this->status) === 'active';
    }

    public function isProjectAdmin(): bool
    {
        return $this->is_project_admin === true;
    }

    public function isSystemManager(): bool
    {
        return $this->relationLoaded('roles')
            ? $this->roles->contains('slug', 'system_manager')
            : $this->roles()->where('slug', 'system_manager')->exists();
    }
}
