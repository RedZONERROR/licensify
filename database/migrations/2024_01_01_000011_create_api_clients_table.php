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
        Schema::create('api_clients', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('api_key_hash'); // Hashed API key
            $table->string('secret_hash'); // Hashed secret for HMAC
            $table->unsignedBigInteger('user_id'); // Owner of the API client
            $table->json('scopes')->nullable(); // API permissions
            $table->boolean('is_active')->default(true);
            $table->integer('rate_limit')->default(1000); // Requests per hour
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Foreign key constraint
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');

            // Performance indexes
            $table->index('api_key_hash');
            $table->index('user_id');
            $table->index('is_active');
            $table->index('last_used_at');
            $table->index(['user_id', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('api_clients');
    }
};