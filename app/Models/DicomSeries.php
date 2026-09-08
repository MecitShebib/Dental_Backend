<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DicomSeries extends Model
{
    use HasFactory, HasUuid;

    protected $fillable = [
        'uuid',
        'dicom_study_id',
        'series_uid',
        'rows',
        'columns',
        'slice_count',
        'frame_count',
        'bits_allocated',
        'bits_stored',
        'high_bit',
        'pixel_representation',
        'samples_per_pixel',
        'photometric_interpretation',
        'sop_class_uid',
        'transfer_syntax_uid',
        'pixel_data_offset',
        'is_frame_extractable',
        'pixel_spacing_x',
        'pixel_spacing_y',
        'slice_thickness',
        'orientation',
        'storage_path',
    ];

    protected $casts = [
        'is_frame_extractable' => 'boolean',
    ];

    public function study(): BelongsTo
    {
        return $this->belongsTo(DicomStudy::class, 'dicom_study_id');
    }
}
