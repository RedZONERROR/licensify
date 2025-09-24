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
        Schema::create('license_activations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('license_id');
            $table->string('device_hash'); // Cryptographic hash of device fingerprint
            $table->string('device_name')->nullable(); // Human-readable device name
            $table->json('device_info')->nullable(); // OS, hardware info, etc.
            $table->string('ip_address')->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamp('activated_at');
            $table->timestamp('last_seen_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Foreign key constraint
            $table->foreign('license_id')->references('id')->on('licenses')->onDelete('cascade');

            // Performance indexes
            $table->index('license_id');
            $table->index('device_hash');
            $table->index('is_active');
            $table->index(['license_id', 'is_active']);
            $table->index(['license_id', 'device_hash']);
            $table->index('last_seen_at');
            $table->index('activated_at');

            // Unique constraint to prevent duplicate device activations
            $table->unique(['license_id', 'device_hash']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('license_activations');
    }
};