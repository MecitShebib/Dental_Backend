<?php

namespace App\Models;

use App\Enums\NutritionActivityLevel;
use App\Enums\NutritionDietaryType;
use App\Enums\NutritionGoal;
use App\Enums\NutritionSubstanceUseStatus;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToCompanyViaClient;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NutritionClientProfile extends Model
{
    use Auditable, BelongsToCompanyViaClient, HasUuid;

    protected $fillable = [
        'uuid',
        'client_id',
        'height_cm',
        'dietary_type',
        'allergies',
        'chronic_conditions',
        'medications_affecting_diet',
        'smoking_status',
        'alcohol_status',
        'activity_level',
        'goal',
        'target_weight_kg',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'height_cm' => 'decimal:1',
            'target_weight_kg' => 'decimal:1',
            'allergies' => 'array',
            'chronic_conditions' => 'array',
            'dietary_type' => NutritionDietaryType::class,
            'smoking_status' => NutritionSubstanceUseStatus::class,
            'alcohol_status' => NutritionSubstanceUseStatus::class,
            'activity_level' => NutritionActivityLevel::class,
            'goal' => NutritionGoal::class,
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }
}
