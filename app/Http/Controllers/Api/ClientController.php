<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Client\StoreClientRequest;
use App\Http\Requests\Client\UpdateClientRequest;
use App\Http\Resources\ClientListResource;
use App\Http\Resources\ClientResource;
use App\Models\Client;
use Illuminate\Support\Str;

class ClientController extends Controller
{
    public function index()
    {
        $clients = Client::query()
            ->with(['appointments' => fn ($query) => $query->with(['client', 'doctor'])->where('status', 'scheduled')->whereDate('date', '>=', now()->toDateString())->orderBy('date')->orderBy('start_time')->limit(1)])
            ->when(request('name'), fn ($query) => $query->where('name', 'like', '%'.request('name').'%'))
            ->when(request('phone'), fn ($query) => $query->where('phone', 'like', '%'.request('phone').'%'))
            ->latest()
            ->paginate(request()->has('per_page') ? (int) request('per_page') : null);

        $resource = ClientListResource::collection($clients);

        return $this->success(
            request()->has('per_page') ? $resource->response()->getData(true) : $resource
        );
    }

    public function store(StoreClientRequest $request)
    {
        $data = $request->validated();
        $client = Client::create([
            ...$data,
            'client_code' => $data['client_code'] ?? 'CL-'.strtoupper(Str::random(8)),
            'created_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
            'status' => $data['status'] ?? 'new',
        ]);

        return $this->success(ClientResource::make($client->load(['appointments' => fn ($query) => $query->with(['client', 'doctor'])->where('status', 'scheduled')->whereDate('date', '>=', now()->toDateString())->orderBy('date')->orderBy('start_time')->limit(1)])), 'Client created successfully.', 201);
    }

    public function show(Client $client)
    {
        $client->load([
            'appointments' => fn ($query) => $query->with(['client', 'doctor'])->where('status', 'scheduled')->whereDate('date', '>=', now()->toDateString())->orderBy('date')->orderBy('start_time')->limit(1),
            'treatmentRecord',
        ]);

        return $this->success(ClientResource::make($client));
    }

    public function update(UpdateClientRequest $request, Client $client)
    {
        $client->update([
            ...$request->validated(),
            'updated_by' => $request->user()->id,
        ]);

        return $this->success(ClientResource::make($client->load(['appointments' => fn ($query) => $query->with(['client', 'doctor'])->where('status', 'scheduled')->whereDate('date', '>=', now()->toDateString())->orderBy('date')->orderBy('start_time')->limit(1)])), 'Client updated successfully.');
    }

    public function destroy(Client $client)
    {
        $client->delete();

        return $this->success(null, 'Client deleted successfully.');
    }
}
