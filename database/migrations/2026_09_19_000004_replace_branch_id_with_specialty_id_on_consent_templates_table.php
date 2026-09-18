<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Consent templates turned out to scope by specialty, not by branch
    // (a template applies to one specialty's whole app, regardless of
    // branch) -- replacing the branch_id column added a day earlier.
    public function up(): void
    {
        if (Schema::hasColumn('consent_templates', 'branch_id')) {
            if (Schema::getConnection()->getDriverName() === 'sqlite') {
                // SQLite can't drop a column that's part of an inline FK
                // definition without also dropping the FK in the same
                // rebuild -- dropConstrainedForeignId() does exactly that
                // (a full table rebuild), so it's the right call only here.
                Schema::table('consent_templates', function (Blueprint $table) {
                    $table->dropConstrainedForeignId('branch_id');
                });
            } else {
                // MySQL: don't assume the FK's name follows Laravel's
                // default naming convention (dropConstrainedForeignId()
                // does, and that guess was wrong for this table on
                // production) -- look up its real name first.
                $this->dropForeignKeyOnColumn('consent_templates', 'branch_id');

                Schema::table('consent_templates', function (Blueprint $table) {
                    $table->dropColumn('branch_id');
                });
            }
        }

        if (! Schema::hasColumn('consent_templates', 'specialty_id')) {
            Schema::table('consent_templates', function (Blueprint $table) {
                $table->foreignId('specialty_id')->nullable()->after('company_id')->constrained()->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::table('consent_templates', function (Blueprint $table) {
            $table->dropConstrainedForeignId('specialty_id');
        });

        Schema::table('consent_templates', function (Blueprint $table) {
            $table->foreignId('branch_id')->nullable()->after('company_id')->constrained()->nullOnDelete();
        });
    }

    protected function dropForeignKeyOnColumn(string $table, string $column): void
    {
        $foreignKey = collect(Schema::getForeignKeys($table))
            ->first(fn (array $foreign) => in_array($column, $foreign['columns'], true));

        if (! $foreignKey) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($foreignKey) {
            $blueprint->dropForeign($foreignKey['name']);
        });
    }
};
