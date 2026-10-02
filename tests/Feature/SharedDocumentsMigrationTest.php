<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SharedDocumentsMigrationTest extends TestCase
{
    use RefreshDatabase;

    protected function runMigration(): void
    {
        (require database_path('migrations/2026_09_30_000004_create_shared_documents_table.php'))->up();
    }

    public function test_an_old_html_snapshot_table_from_the_removed_migration_is_replaced(): void
    {
        // What production had from the removed 2026_09_27 migration.
        Schema::drop('shared_documents');
        Schema::create('shared_documents', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('title');
            $table->longText('html');
            $table->timestamp('expires_at');
            $table->timestamps();
        });

        $this->runMigration();

        $this->assertTrue(Schema::hasColumn('shared_documents', 'path'));
        $this->assertTrue(Schema::hasColumn('shared_documents', 'filename'));
        $this->assertFalse(Schema::hasColumn('shared_documents', 'html'));
    }

    public function test_a_table_that_already_has_the_new_shape_is_left_alone(): void
    {
        $this->runMigration();

        $this->assertTrue(Schema::hasColumn('shared_documents', 'path'));
    }
}
