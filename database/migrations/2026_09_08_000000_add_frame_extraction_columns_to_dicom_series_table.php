<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dicom_series', function (Blueprint $table) {
            // A real CBCT/CT export is almost always ONE physical file
            // holding hundreds of frames in its PixelData, not one file per
            // slice -- previously that meant a single ~300MB download just
            // to render frame 0, since the viewer had no way to ask for
            // anything less than the whole file. These columns let
            // DicomStudyController::seriesFrame() compute a single frame's
            // exact byte range and stream just that, once at upload time.
            $table->unsignedInteger('frame_count')->default(1)->after('slice_count');
            $table->unsignedInteger('bits_allocated')->nullable()->after('frame_count');
            $table->unsignedInteger('bits_stored')->nullable()->after('bits_allocated');
            $table->unsignedInteger('high_bit')->nullable()->after('bits_stored');
            $table->unsignedTinyInteger('pixel_representation')->nullable()->after('high_bit');
            $table->unsignedTinyInteger('samples_per_pixel')->nullable()->after('pixel_representation');
            $table->string('photometric_interpretation')->nullable()->after('samples_per_pixel');
            $table->string('sop_class_uid')->nullable()->after('photometric_interpretation');
            $table->string('transfer_syntax_uid')->nullable()->after('sop_class_uid');
            // Byte offset of PixelData's VALUE (not the tag/VR/length header)
            // within the stored file -- frame N's bytes start at
            // pixel_data_offset + N * (rows*columns*samples_per_pixel*ceil(bits_allocated/8)).
            $table->unsignedBigInteger('pixel_data_offset')->nullable()->after('transfer_syntax_uid');
            // False whenever per-frame extraction isn't possible/safe: a
            // genuinely single-frame file (frame_count stays 1, nothing to
            // gain), or PixelData uses an undefined length (encapsulated /
            // compressed transfer syntax -- frames aren't at fixed byte
            // offsets, would need Basic Offset Table + fragment parsing this
            // reader doesn't do). seriesFile() (whole-file) stays the
            // fallback whenever this is false.
            $table->boolean('is_frame_extractable')->default(false)->after('pixel_data_offset');
        });
    }

    public function down(): void
    {
        Schema::table('dicom_series', function (Blueprint $table) {
            $table->dropColumn([
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
            ]);
        });
    }
};
