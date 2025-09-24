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
        Schema::create('api_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('api_client_id')->nullable();
            $table->string('endpoint');
            $table->string('method');
            $table->ipAddress('ip_address');
            $table->string('user_agent')->nullable();
            $table->json('request_data')->nullable();
            $table->json('response_data')->nullable();
            $table->integer('response_status');
            $table->decimal('response_time', 8, 3); // Response time in milliseconds
            $table->string('nonce')->nullable();
            $table->timestamp('request_timestamp');
            $table->timestamps();

            // Foreign key constraint
            $table->foreign('api_client_id')->references('id')->on('api_clients')->onDelete('set null');

            // Performance indexes
            $table->index('api_client_id');
            $table->index('endpoint');
            $table->index('ip_address');
            $table->index('request_timestamp');
            $table->index(['api_client_id', 'request_timestamp']);
            $table->index(['endpoint', 'request_timestamp']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('api_requests');
    }
};
