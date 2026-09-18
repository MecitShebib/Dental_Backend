<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class XrayImage extends Model
{
    use Auditable, BelongsToCompany, HasFactory, HasUuid;

    protected $fillable = [
        'uuid',
        'company_id',
        'client_id',
        'branch_id',
        'specialty_id',
        'image_path',
        'original_filename',
        'notes',
        'uploaded_by',
        'ai_odontogram_status',
        'ai_analyzed_at',
    ];

    protected $casts = [
        'ai_odontogram_status' => 'array',
        'ai_analyzed_at' => 'datetime',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function specialty(): BelongsTo
    {
        return $this->belongsTo(Specialty::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
