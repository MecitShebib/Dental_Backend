<?php

namespace App\Models\Concerns;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

/**
 * Writes an AuditLog row on create/update/delete for any personal-data
 * model that uses this trait (Client, ClientConsent, XrayImage,
 * TreatmentRecord, PatientLabResult -- see the KVKK compliance plan,
 * docs/superpowers/plans/2026-09-05-kvkk-uyumlulugu.md, Görev 0.3).
 * "Viewed" events have no Eloquent event to hook into and are logged
 * explicitly at the controller call site instead (see
 * ClientController::show()).
 */
trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(fn ($model) => static::writeAuditLog('created', $model));
        static::updated(fn ($model) => static::writeAuditLog('updated', $model, $model->getChanges()));
        static::deleted(fn ($model) => static::writeAuditLog('deleted', $model));
    }

    protected static function writeAuditLog(string $action, $model, ?array $changedFields = null): void
    {
        $actor = static::resolveAuditActor();

        AuditLog::record($action, $model, $actor, $changedFields ? ['changed_fields' => array_keys($changedFields)] : null);
    }

    protected static function resolveAuditActor(): ?User
    {
        // Checks both guards since this trait covers models touched from
        // the token-authenticated mobile/SPA API (sanctum) and the
        // session-authenticated admin panel (web). Console commands/seeders
        // have no request-bound guard at all, so both simply return null.
        return Auth::guard('sanctum')->user() ?? Auth::guard('web')->user();
    }
}
