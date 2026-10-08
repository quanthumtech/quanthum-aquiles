<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('quanthum_license_state', function (Blueprint $table) {
            $table->id();
            $table->uuid('installation_id')->unique();
            $table->string('server_instance_id')->nullable();
            $table->string('public_license_id')->nullable();
            $table->text('license_token')->nullable();
            $table->string('fingerprint_hash')->nullable();
            $table->json('modules')->nullable();
            $table->unsignedInteger('grace_period_hours')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('last_success_at')->nullable();
            $table->string('status')->default('not_activated');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('quanthum_license_state');
    }
};
