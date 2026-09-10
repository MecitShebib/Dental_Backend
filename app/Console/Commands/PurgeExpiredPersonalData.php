<?php

namespace App\Console\Commands;

use App\Models\Client;
use App\Models\Company;
use App\Models\PublicBookingOtp;
use App\Models\UserOtp;
use App\Services\ClientErasureService;
use Illuminate\Console\Command;

/**
 * The periodic-destruction side of KVKK's Silme/Yok Etme/Anonim Hale
 * Getirme Yönetmeliği: data whose processing purpose has ended gets
 * deleted or anonymized rather than kept indefinitely. See the KVKK
 * compliance plan, docs/superpowers/plans/2026-09-05-kvkk-uyumlulugu.md,
 * Faz 4, and docs/kvkk-saklama-ve-imha-politikasi.md for the human-readable
 * policy this command implements.
 *
 * Registered in routes/console.php as a daily schedule -- but this host has
 * no cron running `schedule:run` (see the "Shared hosting deploy gotchas"
 * note in CLAUDE.md-adjacent project memory), so it will not actually fire
 * in production until a cPanel cron is added. Safe to run manually via
 * `php artisan kvkk:purge` in the meantime.
 */
class PurgeExpiredPersonalData extends Command
{
    protected $signature = 'kvkk:purge';

    protected $description = 'Delete expired OTP challenges and anonymize patients of long-lapsed companies';

    /**
     * A company with no active subscription for this long has had its
     * "processing purpose" (running a clinic on this platform) end -- KVKK
     * m.7 requires the data be deleted/anonymized once that happens, not
     * kept indefinitely on the chance the company resubscribes.
     */
    protected const LAPSED_COMPANY_DAYS = 365;

    public function handle(ClientErasureService $erasure): int
    {
        $this->purgeOtps();
        $this->anonymizeLapsedCompanyClients($erasure);

        return self::SUCCESS;
    }

    protected function purgeOtps(): void
    {
        // A verified/expired OTP has nothing left to prove once the login
        // or booking attempt it was for is long over -- one day is well
        // past every OTP's own expires_at (see MobileOtpService).
        $cutoff = now()->subDay();

        $userOtps = UserOtp::query()->where('created_at', '<', $cutoff)->delete();
        $bookingOtps = PublicBookingOtp::query()->where('created_at', '<', $cutoff)->delete();

        $this->line("OTP challenges purged: {$userOtps} user, {$bookingOtps} public-booking.");
    }

    protected function anonymizeLapsedCompanyClients(ClientErasureService $erasure): void
    {
        $cutoff = now()->subDays(self::LAPSED_COMPANY_DAYS);

        $lapsedCompanyIds = Company::query()
            ->whereDoesntHave('subscriptions', function ($query) use ($cutoff) {
                $query->where('status', 'active')
                    ->where(function ($q) use ($cutoff) {
                        $q->whereNull('ends_at')->orWhereDate('ends_at', '>=', $cutoff->toDateString());
                    });
            })
            ->whereHas('subscriptions', function ($query) use ($cutoff) {
                // Only companies that *had* a subscription which lapsed a
                // year ago -- never one that simply never subscribed at all
                // (e.g. a brand-new admin-created company mid-onboarding).
                $query->whereNotNull('ends_at')->whereDate('ends_at', '<', $cutoff->toDateString());
            })
            ->pluck('id');

        $anonymized = 0;

        foreach ($lapsedCompanyIds as $companyId) {
            $clients = Client::withoutGlobalScopes()
                ->where('company_id', $companyId)
                ->whereNull('anonymized_at')
                ->get();

            foreach ($clients as $client) {
                $erasure->anonymize($client);
                $anonymized++;
            }
        }

        $this->line('Patients anonymized (company lapsed >'.self::LAPSED_COMPANY_DAYS." days ago): {$anonymized}.");
    }
}
