<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ActivityLogResource;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * The clinic system manager's company-wide "who did what, when" screen --
 * every AuditLog row across every specialty and every model the Auditable
 * trait covers (patients, appointments, visits, payments, accounting,
 * password changes, ...), never filtered by activeSpecialtyKey since a
 * System Manager oversees the whole company, not one specialty's app.
 */
class ActivityLogController extends Controller
{
    public function index(Request $request)
    {
        $this->assertIsSystemManager($request);

        $validated = $request->validate([
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'role' => ['nullable', Rule::in(['doctor', 'staff'])],
            'client_id' => ['nullable', 'integer', 'exists:clients,id'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
            'category' => ['nullable', Rule::in(array_keys(AuditLog::CATEGORY_MODELS))],
            'action' => ['nullable', Rule::in(['created', 'updated', 'deleted', 'password_changed'])],
        ]);

        $logs = AuditLog::query()
            ->with([
                // withTrashed: the actor or patient may have been deleted
                // (staff turnover, KVKK erasure) since the action happened,
                // but the log entry -- and whoever it names -- must still
                // display correctly.
                'user' => fn ($query) => $query->withTrashed(),
                'user.roles',
                'client' => fn ($query) => $query->withTrashed(),
            ])
            ->where('company_id', $request->user()->company_id)
            ->when($validated['user_id'] ?? null, fn ($query, $userId) => $query->where('user_id', $userId))
            ->when($validated['role'] ?? null, fn ($query, $role) => $query->whereHas(
                'user',
                fn ($q) => $q->withTrashed()->where('is_doctor', $role === 'doctor'),
            ))
            ->when($validated['client_id'] ?? null, fn ($query, $clientId) => $query->where('client_id', $clientId))
            ->when($validated['date_from'] ?? null, fn ($query, $date) => $query->whereDate('created_at', '>=', $date))
            ->when($validated['date_to'] ?? null, fn ($query, $date) => $query->whereDate('created_at', '<=', $date))
            ->when(
                $validated['category'] ?? null,
                fn ($query, $category) => $query->whereIn('auditable_type', AuditLog::CATEGORY_MODELS[$category] ?? []),
            )
            ->when($validated['action'] ?? null, fn ($query, $action) => $query->where('action', $action))
            ->orderByDesc('created_at')
            ->paginate(100);

        // ->response()->getData(true) (not the bare resource collection) is
        // what actually produces the {data, links, meta} paginator envelope
        // -- see ClientController::index() for the same pattern.
        return $this->success(ActivityLogResource::collection($logs)->response()->getData(true));
    }

    protected function assertIsSystemManager(Request $request): void
    {
        if ($request->user()->isSystemManager() || $request->user()->isProjectAdmin()) {
            return;
        }

        throw ValidationException::withMessages([
            'user' => ['Only the clinic system manager can view the activity log.'],
        ]);
    }
}
