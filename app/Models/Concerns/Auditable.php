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
        $meta = null;

        if ($changedFields !== null) {
            // updated_at is never itself the story; last_login_at is bumped
            // on every single login (User::forceFill(...)->save() in both
            // AuthControllers) and would otherwise drown the activity log in
            // one row per login -- neither is a "changed field" worth
            // reporting on its own, though either still counts once paired
            // with a real field change below.
            $fields = array_values(array_diff(array_keys($changedFields), ['updated_at', 'last_login_at']));

            // A password change is common enough (and sensitive enough) to
            // deserve its own action label instead of getting buried in a
            // generic "updated" -- the activity log filters/displays on this.
            // The field NAME is never a secret; only its value would be, and
            // that never enters $fields (array_keys, not the changes array).
            if ($action === 'updated' && in_array('password', $fields, true)) {
                $action = 'password_changed';
                $fields = array_values(array_diff($fields, ['password']));
            }

            // A plain touch() (or a save() that only bumped updated_at) isn't
            // a meaningful activity-log entry.
            if ($action === 'updated' && $fields === []) {
                return;
            }

            $meta = ['changed_fields' => $fields];
        }

        // Opt-in per model (see e.g. User::auditSubjectLabel()) -- a short,
        // human string identifying WHAT was acted on beyond the model's own
        // type, for models the activity log can't otherwise label via a
        // client_id join (see AuditLog::resolveClientId()).
        if (method_exists($model, 'auditSubjectLabel') && ($label = $model->auditSubjectLabel())) {
            $meta = ($meta ?? []) + ['subject_label' => $label];
        }

        AuditLog::record($action, $model, $actor, $meta);
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
