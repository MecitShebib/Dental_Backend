<?php

namespace App\Models;

use App\Enums\InquiryType;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LandingPageInquiry extends Model
{
    use HasFactory, HasUuid;

    protected $fillable = [
        'uuid',
        'type',
        'locale',
        'name',
        'email',
        'phone',
        'company',
        'message',
        'read_at',
    ];

    protected function casts(): array
    {
        return [
            'type' => InquiryType::class,
            'read_at' => 'datetime',
        ];
    }
}
