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
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('transaction_id')->unique(); // External payment ID
            $table->string('provider'); // stripe, paypal, razorpay
            $table->decimal('amount', 10, 2);
            $table->string('currency', 3)->default('USD');
            $table->enum('type', ['credit', 'debit', 'refund']);
            $table->enum('status', ['pending', 'completed', 'failed', 'cancelled']);
            $table->text('description')->nullable();
            $table->json('provider_data')->nullable(); // Raw webhook data
            $table->json('metadata')->nullable();
            $table->timestamps();

            // Foreign key constraint
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');

            // Performance indexes
            $table->index('user_id');
            $table->index('transaction_id');
            $table->index('provider');
            $table->index('status');
            $table->index('type');
            $table->index('created_at');
            $table->index(['user_id', 'status']);
            $table->index(['provider', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};