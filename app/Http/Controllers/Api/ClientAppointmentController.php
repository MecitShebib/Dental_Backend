<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\AppointmentResource;
use App\Models\Client;

class ClientAppointmentController extends Controller
{
    public function index(Client $client)
    {
        $appointments = $client->appointments()->with(['client', 'doctor'])->orderByDesc('date')->orderByDesc('start_time')->paginate();

        return $this->success(AppointmentResource::collection($appointments));
    }
}
