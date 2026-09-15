<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * A doctor's own signature/stamp image, pasted onto their generated
 * prescription printouts (see PrescriptionResource's
 * doctor_signature_url/doctor_stamp_url and the frontend's
 * prescriptionDocument.js). Upload/delete are self-only -- a doctor manages
 * only their own, same ownership rule as every other doctor-owned record in
 * this app. Viewing is a signed, unauthenticated file route (see
 * XrayImageController::file()) since a prescription printout embeds a plain
 * <img src> that can't carry a bearer token.
 */
class DoctorSignatureController extends Controller
{
    public function uploadSignature(Request $request)
    {
        $user = $this->storeImage($request, 'signature_path', 'signatures');

        return $this->success(UserResource::make($user), 'Signature saved successfully.');
    }

    public function uploadStamp(Request $request)
    {
        $user = $this->storeImage($request, 'stamp_path', 'stamps');

        return $this->success(UserResource::make($user), 'Stamp saved successfully.');
    }

    public function destroySignature(Request $request)
    {
        $this->deleteImage($request, 'signature_path');

        return $this->success(null, 'Signature removed successfully.');
    }

    public function destroyStamp(Request $request)
    {
        $this->deleteImage($request, 'stamp_path');

        return $this->success(null, 'Stamp removed successfully.');
    }

    public function signatureFile(User $user)
    {
        abort_unless($user->signature_path, 404);

        return Storage::disk('local')->response($user->signature_path);
    }

    public function stampFile(User $user)
    {
        abort_unless($user->stamp_path, 404);

        return Storage::disk('local')->response($user->stamp_path);
    }

    protected function storeImage(Request $request, string $column, string $directory): User
    {
        $data = $request->validate([
            'image' => ['required', 'image', 'max:4096'],
        ]);

        $user = $request->user();

        if ($user->{$column}) {
            Storage::disk('local')->delete($user->{$column});
        }

        $user->{$column} = $data['image']->store($directory, 'local');
        $user->save();

        return $user;
    }

    protected function deleteImage(Request $request, string $column): void
    {
        $user = $request->user();

        if ($user->{$column}) {
            Storage::disk('local')->delete($user->{$column});
            $user->{$column} = null;
            $user->save();
        }
    }
}
