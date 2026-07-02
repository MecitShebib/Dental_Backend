<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('client_code')->unique();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('phone')->index();
            $table->enum('gender', ['male', 'female']);
            $table->unsignedInteger('age')->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('city')->nullable();
            $table->text('address')->nullable();
            $table->longText('medical_notes')->nullable();
            $table->enum('status', ['new', 'under_treatment', 'completed', 'inactive'])->default('new')->index();
            $table->dateTime('last_visit_at')->nullable()->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clients');
    }
};
