<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Same pivot as consent_templates: message templates scope by specialty,
    // not by branch -- replacing yesterday's branch_id column and its
    // unique index with a specialty_id one.
    protected string $oldUniqueIndexName = 'message_templates_branch_scope_unique';

    protected string $newUniqueIndexName = 'message_templates_specialty_scope_unique';

    public function up(): void
    {
        $indexNames = collect(Schema::getIndexes('message_templates'))->pluck('name');

        if ($indexNames->contains($this->oldUniqueIndexName)) {
            Schema::table('message_templates', function (Blueprint $table) {
                $table->dropUnique($this->oldUniqueIndexName);
            });
        }

        if (Schema::hasColumn('message_templates', 'branch_id')) {
            if (Schema::getConnection()->getDriverName() === 'sqlite') {
                // SQLite can't drop a column that's part of an inline FK
                // definition without also dropping the FK in the same
                // rebuild -- dropConstrainedForeignId() does exactly that
                // (a full table rebuild), so it's the right call only here.
                Schema::table('message_templates', function (Blueprint $table) {
                    $table->dropConstrainedForeignId('branch_id');
                });
            } else {
                // MySQL: don't assume the FK's name follows Laravel's
                // default naming convention (dropConstrainedForeignId()
                // does, and that guess was wrong for the sibling
                // consent_templates migration) -- look up its real name first.
                $this->dropForeignKeyOnColumn('message_templates', 'branch_id');

                Schema::table('message_templates', function (Blueprint $table) {
                    $table->dropColumn('branch_id');
                });
            }
        }

        if (! Schema::hasColumn('message_templates', 'specialty_id')) {
            Schema::table('message_templates', function (Blueprint $table) {
                $table->foreignId('specialty_id')->nullable()->after('company_id')->constrained()->nullOnDelete();
            });
        }

        $indexNames = collect(Schema::getIndexes('message_templates'))->pluck('name');
        if (! $indexNames->contains($this->newUniqueIndexName)) {
            Schema::table('message_templates', function (Blueprint $table) {
                $table->unique(['company_id', 'specialty_id', 'key', 'channel', 'language'], $this->newUniqueIndexName);
            });
        }
    }

    public function down(): void
    {
        Schema::table('message_templates', function (Blueprint $table) {
            $table->dropUnique($this->newUniqueIndexName);
        });

        Schema::table('message_templates', function (Blueprint $table) {
            $table->dropConstrainedForeignId('specialty_id');
        });

        Schema::table('message_templates', function (Blueprint $table) {
            $table->foreignId('branch_id')->nullable()->after('company_id')->constrained()->nullOnDelete();
        });

        Schema::table('message_templates', function (Blueprint $table) {
            $table->unique(['company_id', 'branch_id', 'key', 'channel', 'language'], $this->oldUniqueIndexName);
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
