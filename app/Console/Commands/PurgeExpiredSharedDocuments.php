<?php

namespace App\Console\Commands;

use App\Models\SharedDocument;
use Illuminate\Console\Command;

class PurgeExpiredSharedDocuments extends Command
{
    protected $signature = 'documents:purge-expired';

    protected $description = 'Delete patient document PDFs shared over WhatsApp once their link has expired.';

    public function handle(): int
    {
        $this->info('Deleted '.SharedDocument::purgeExpired().' expired shared document(s).');

        return self::SUCCESS;
    }
}
