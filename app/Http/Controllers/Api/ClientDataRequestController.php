<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\AuthorizesOwnDoctorRecords;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Client;
use App\Services\ClientDataExportService;
use App\Services\ClientErasureService;
use Illuminate\Http\Request;

/**
 * Fulfils the two KVKK m.11 data-subject rights that need a dedicated
 * endpoint rather than the regular CRUD surface: access/portability
 * (export) and erasure. See the KVKK compliance plan,
 * docs/superpowers/plans/2026-09-05-kvkk-uyumlulugu.md, Faz 3.
 */
class ClientDataRequestController extends Controller
{
    use AuthorizesOwnDoctorRecords;

    public function export(Request $request, Client $client, ClientDataExportService $export)
    {
        $this->assertActingDoctorOwnsClient($request, $client);

        AuditLog::record('exported', $client, $request->user());

        return response()->json($export->export($client))
            ->header('Content-Disposition', 'attachment; filename="client-'.$client->uuid.'-data-export.json"');
    }

    public function destroy(Request $request, Client $client, ClientErasureService $erasure)
    {
        $this->assertActingDoctorOwnsClient($request, $client);

        AuditLog::record('erasure_requested', $client, $request->user());

        $erasure->anonymize($client);

        return $this->success(null, 'Client personal data anonymized successfully.');
    }
}
