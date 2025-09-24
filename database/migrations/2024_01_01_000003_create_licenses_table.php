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
        Schema::create('licenses', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('owner_id'); // The reseller/admin who owns this license
            $table->unsignedBigInteger('user_id')->nullable(); // The end user assigned to this license
            $table->string('license_key')->unique(); // UUID-based license key
            $table->enum('status', ['active', 'expired', 'suspended', 'reset'])->default('active');
            $table->string('device_type')->nullable();
            $table->integer('max_devices')->default(1);
            $table->timestamp('expires_at')->nullable();
            $table->json('metadata')->nullable(); // Additional license metadata
            $table->timestamps();
            $table->softDeletes();

            // Foreign key constraints
            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
            $table->foreign('owner_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');

            // Performance indexes
            $table->index('license_key');
            $table->index('owner_id');
            $table->index('user_id');
            $table->index('status');
            $table->index('expires_at');
            $table->index(['owner_id', 'status']);
            $table->index(['user_id', 'status']);
            $table->index(['status', 'expires_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('licenses');
    }
};