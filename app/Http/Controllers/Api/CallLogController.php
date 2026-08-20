<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CallLog\StoreCallLogRequest;
use App\Http\Resources\CallLogResource;
use App\Models\CallLog;
use App\Services\CallLogService;
use Illuminate\Http\Request;

class CallLogController extends Controller
{
    public function index(Request $request)
    {
        $logs = CallLog::query()
            ->when($request->filled('direction'), fn ($query) => $query->where('direction', $request->string('direction')))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->when($request->filled('client_id'), fn ($query) => $query->where('client_id', $request->integer('client_id')))
            ->when($request->filled('from'), fn ($query) => $query->whereDate('occurred_at', '>=', $request->date('from')))
            ->when($request->filled('to'), fn ($query) => $query->whereDate('occurred_at', '<=', $request->date('to')))
            ->latest('occurred_at')
            ->paginate($request->has('per_page') ? (int) $request->integer('per_page') : 25);

        return $this->success(CallLogResource::collection($logs)->response()->getData(true));
    }

    public function store(StoreCallLogRequest $request, CallLogService $callLogs)
    {
        $log = $callLogs->log($request->user()->company, $request->validated(), $request->user()->id);

        return $this->success(CallLogResource::make($log->load('client')), 'Call logged successfully.', 201);
    }

    public function destroy(CallLog $callLog)
    {
        $callLog->delete();

        return $this->success(null, 'Call log deleted successfully.');
    }

    public function markFollowedUp(CallLog $callLog, CallLogService $callLogs)
    {
        return $this->success(CallLogResource::make($callLogs->markFollowedUp($callLog)->load('client')), 'Call marked as followed up.');
    }

    public function summary(Request $request)
    {
        $from = $request->filled('from') ? $request->date('from') : now()->subDays(30);
        $to = $request->filled('to') ? $request->date('to') : now();

        $logs = CallLog::query()->whereBetween('occurred_at', [$from->startOfDay(), $to->endOfDay()])->get();

        return $this->success([
            'total' => $logs->count(),
            'inbound' => $logs->where('direction', 'inbound')->count(),
            'outbound' => $logs->where('direction', 'outbound')->count(),
            'missed' => $logs->where('status', 'missed')->count(),
            'answered' => $logs->where('status', 'answered')->count(),
            'missed_needing_follow_up' => CallLog::query()->where('status', 'missed')->whereNull('followed_up_at')->count(),
        ]);
    }
}
