<?php

namespace App\Console\Commands;

use App\Models\ClientConsent;
use App\Models\Expense;
use App\Models\XrayImage;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/**
 * One-time cleanup for the KVKK private-disk migration (see
 * docs/superpowers/plans/2026-09-05-kvkk-uyumlulugu.md, Görev 0.1): moves
 * X-ray images, consent signatures, and expense attachments that were
 * already uploaded to the public disk (unauthenticated, guessable-by-URL)
 * onto the private disk, and deletes the now-stale public copy. New uploads
 * already go straight to the private disk as of this same change --
 * XrayImageController::store(), ConsentService::storeSignature(),
 * ExpenseController::store()/update() -- this command only backfills
 * whatever was uploaded before that change shipped.
 *
 * Safe to run more than once: skips any row whose file is already missing
 * from the public disk (already migrated).
 */
class MigratePrivateFiles extends Command
{
    protected $signature = 'kvkk:migrate-private-files';

    protected $description = 'Move existing X-ray/consent-signature/expense-attachment files from the public disk to the private disk';

    public function handle(): int
    {
        $this->migrateModel(XrayImage::query()->cursor(), 'image_path', 'X-ray images');
        $this->migrateModel(ClientConsent::query()->cursor(), 'signature_path', 'consent signatures');
        $this->migrateModel(Expense::query()->whereNotNull('attachment_path')->cursor(), 'attachment_path', 'expense attachments');

        $this->info('Done.');

        return self::SUCCESS;
    }

    /**
     * @param  iterable<Model>  $rows
     */
    protected function migrateModel(iterable $rows, string $pathColumn, string $label): void
    {
        $moved = 0;
        $skipped = 0;

        foreach ($rows as $row) {
            $path = $row->{$pathColumn};

            if (! $path) {
                $skipped++;

                continue;
            }

            if (! Storage::disk('public')->exists($path)) {
                // Already migrated (or never existed on the public disk).
                $skipped++;

                continue;
            }

            $binary = Storage::disk('public')->get($path);
            Storage::disk('local')->put($path, $binary);
            Storage::disk('public')->delete($path);
            $moved++;
        }

        $this->line("{$label}: moved {$moved}, skipped {$skipped} (already migrated or empty).");
    }
}
