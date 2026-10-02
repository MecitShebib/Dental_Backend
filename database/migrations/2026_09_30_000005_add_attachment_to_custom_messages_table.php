<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A custom WhatsApp message can carry an image or PDF (sent to the
     * patient as a link), so its text becomes optional -- a title plus
     * either text or an attachment is required (enforced in the controller).
     * The file stays on the public disk until the message (or its group) is
     * deleted or the attachment is replaced/removed.
     */
    public function up(): void
    {
        Schema::table('custom_messages', function (Blueprint $table) {
            $table->text('body')->nullable()->change();
            $table->string('attachment_path')->nullable()->after('body');
            $table->string('attachment_name')->nullable()->after('attachment_path');
            $table->string('attachment_mime', 100)->nullable()->after('attachment_name');
        });
    }

    public function down(): void
    {
        Schema::table('custom_messages', function (Blueprint $table) {
            $table->dropColumn(['attachment_path', 'attachment_name', 'attachment_mime']);
        });
    }
};
