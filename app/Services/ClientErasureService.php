<?php

namespace App\Services;

use App\Models\Client;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Fulfils a KVKK m.7 erasure request: irreversibly masks the patient's
 * identifying fields and deletes their files, without touching financial
 * records (Payment/TreatmentCharge/Invoice), which VUK (Vergi Usul Kanunu)
 * requires keeping for 5 years -- KVKK m.7 itself gives way to a longer
 * retention period set by another law, so those rows are kept but no
 * longer carry anything that identifies who they belonged to beyond the
 * now-anonymized Client row they still point at.
 *
 * Deliberately does not forceDelete(): the Client row survives (masked) so
 * every foreign key into it (visits, payments, treatment_charges...) stays
 * valid. See the KVKK compliance plan,
 * docs/superpowers/plans/2026-09-05-kvkk-uyumlulugu.md, Görev 3.2.
 */
class ClientErasureService
{
    public function anonymize(Client $client): void
    {
        DB::transaction(function () use ($client) {
            $client->xrayImages->each(function ($image) {
                Storage::disk('local')->delete($image->image_path);
                $image->delete();
            });

            $client->consents->each(function ($consent) {
                Storage::disk('local')->delete($consent->signature_path);
            });
            $client->consents()->delete();

            $client->update([
                'name' => 'Silinmiş Hasta #'.$client->id,
                'email' => null,
                'phone' => Str::random(20),
                'date_of_birth' => null,
                'city' => null,
                'address' => null,
                'medical_notes' => null,
                'anonymized_at' => now(),
            ]);

            $client->delete();
        });
    }
}
