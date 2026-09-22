<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\AuthorizesOwnDoctorRecords;
use App\Http\Controllers\Controller;
use App\Http\Resources\AppointmentResource;
use App\Models\Client;
use Illuminate\Http\Request;

class ClientAppointmentController extends Controller
{
    use AuthorizesOwnDoctorRecords;

    public function index(Request $request, Client $client)
    {
        // Same gate ClientVisitController::index() already enforces -- without
        // it any doctor in the company could read a colleague's patient's whole
        // appointment history (notes, planned_summary, planned_notes) by client
        // id alone.
        $this->assertActingDoctorOwnsClient($request, $client);

        $appointments = $client->appointments()->with(['client', 'doctor'])->orderByDesc('date')->orderByDesc('start_time')->paginate();

        return $this->success(AppointmentResource::collection($appointments));
    }
}
