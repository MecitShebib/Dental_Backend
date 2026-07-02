<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('plan_name');
            $table->enum('status', ['active', 'inactive'])->default('active')->index();
            $table->date('starts_at');
            $table->date('ends_at')->nullable()->index();
            $table->unsignedInteger('max_users')->default(1);
            $table->unsignedInteger('active_users')->default(0);
            $table->decimal('price', 12, 2)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
