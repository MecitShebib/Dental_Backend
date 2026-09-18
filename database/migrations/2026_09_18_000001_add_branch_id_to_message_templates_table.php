<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // MySQL's default identifier limit (64 chars) rejects the auto-generated
    // name for a 5-column unique index on this table's already-long name --
    // give it an explicit short one instead of letting the grammar compute it.
    protected string $uniqueIndexName = 'message_templates_branch_scope_unique';

    protected string $oldUniqueIndexName = 'message_templates_company_id_key_channel_language_unique';

    public function up(): void
    {
        // Guarded so this migration can finish cleanly even if it partially
        // ran before (e.g. the branch_id column landed but the oversized
        // default index name made the unique-index step fail).
        if (! Schema::hasColumn('message_templates', 'branch_id')) {
            Schema::table('message_templates', function (Blueprint $table) {
                $table->foreignId('branch_id')->nullable()->after('company_id')->constrained()->nullOnDelete();
            });
        }

        $indexNames = collect(Schema::getIndexes('message_templates'))->pluck('name');

        if ($indexNames->contains($this->oldUniqueIndexName)) {
            Schema::table('message_templates', function (Blueprint $table) {
                $table->dropUnique($this->oldUniqueIndexName);
            });
        }

        if (! $indexNames->contains($this->uniqueIndexName)) {
            Schema::table('message_templates', function (Blueprint $table) {
                $table->unique(['company_id', 'branch_id', 'key', 'channel', 'language'], $this->uniqueIndexName);
            });
        }
    }

    public function down(): void
    {
        Schema::table('message_templates', function (Blueprint $table) {
            $table->dropUnique($this->uniqueIndexName);
        });

        Schema::table('message_templates', function (Blueprint $table) {
            $table->dropConstrainedForeignId('branch_id');
        });

        Schema::table('message_templates', function (Blueprint $table) {
            $table->unique(['company_id', 'key', 'channel', 'language'], $this->oldUniqueIndexName);
        });
    }
};
