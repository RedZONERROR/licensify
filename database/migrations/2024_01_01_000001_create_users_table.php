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
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password')->nullable(); // Nullable for OAuth-only users
            $table->string('avatar')->nullable();
            $table->enum('role', ['admin', 'developer', 'reseller', 'user'])->default('user');
            $table->boolean('2fa_enabled')->default(false);
            $table->string('2fa_secret')->nullable();
            $table->json('2fa_recovery_codes')->nullable();
            $table->timestamp('privacy_policy_accepted_at')->nullable();
            $table->text('developer_notes')->nullable();
            $table->unsignedBigInteger('reseller_id')->nullable();
            $table->json('oauth_providers')->nullable(); // Store OAuth provider info
            $table->string('remember_token')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Indexes for performance
            $table->index('email');
            $table->index('role');
            $table->index('reseller_id');
            $table->index(['role', 'created_at']);
            $table->index('2fa_enabled');

            // Foreign key constraint
            $table->foreign('reseller_id')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};