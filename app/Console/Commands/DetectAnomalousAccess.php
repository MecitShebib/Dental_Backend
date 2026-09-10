<?php

namespace App\Console\Commands;

use App\Mail\AnomalousAccessAlertMail;
use App\Models\AuditLog;
use App\Models\Client;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

/**
 * The lightweight side of a KVKK m.12 veri ihlali (breach) response
 * capability: flags a user viewing an unusually large number of distinct
 * patient records in a short window -- the shape a bulk export or a
 * compromised account produces, not what a single doctor's clinic day
 * looks like. See docs/kvkk-veri-ihlali-mudahale-prosedueru.md and the
 * KVKK compliance plan, docs/superpowers/plans/2026-09-05-kvkk-uyumlulugu.md,
 * Görev 6.2.
 */
class DetectAnomalousAccess extends Command
{
    protected $signature = 'kvkk:detect-anomalous-access';

    protected $description = 'Alert a company when a user views an unusually high number of distinct patient records in one hour';

    /** More distinct patients than this in a single hour triggers an alert. */
    protected const THRESHOLD = 30;

    public function handle(): int
    {
        $rows = AuditLog::query()
            ->selectRaw('user_id, COUNT(DISTINCT auditable_id) as distinct_clients')
            ->where('action', 'viewed')
            ->where('auditable_type', Client::class)
            ->where('created_at', '>=', now()->subHour())
            ->whereNotNull('user_id')
            ->groupBy('user_id')
            ->having('distinct_clients', '>', self::THRESHOLD)
            ->get();

        foreach ($rows as $row) {
            $user = User::withoutGlobalScopes()->with('company')->find($row->user_id);

            if (! $user || ! $user->company?->email) {
                continue;
            }

            Mail::to($user->company->email)->send(new AnomalousAccessAlertMail($user, (int) $row->distinct_clients));
        }

        $this->line("Anomalous access alerts sent: {$rows->count()}.");

        return self::SUCCESS;
    }
}
