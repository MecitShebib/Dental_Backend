<?php

namespace App\Http\Requests\DicomStudy;

use Illuminate\Foundation\Http\FormRequest;

class StoreDicomStudyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // Real CBCT/DICOM exports routinely run 100-500MB+ (a full
        // volumetric scan is hundreds of slices) -- these caps just guard
        // against something absurd, not real scans. The actual limiting
        // factor is PHP's own upload_max_filesize/post_max_size (see
        // public/.user.ini), which rejects an oversized request before it
        // ever reaches this validation.
        return [
            'archive' => ['required_without:files', 'file', 'mimes:zip', 'max:1048576'],
            'files' => ['required_without:archive', 'array', 'min:1'],
            'files.*' => ['file', 'max:1048576'],
            'client_id' => ['nullable', 'integer', 'exists:clients,id'],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'specialty_id' => ['nullable', 'integer', 'exists:specialties,id'],
        ];
    }
}
