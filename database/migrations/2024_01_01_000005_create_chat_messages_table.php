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
        Schema::create('chat_messages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sender_id')->nullable();
            $table->unsignedBigInteger('receiver_id');
            $table->text('body');
            $table->string('message_type')->default('text'); // text, file, system
            $table->json('metadata')->nullable(); // Attachments, formatting, etc.
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Foreign key constraints
            $table->foreign('sender_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('receiver_id')->references('id')->on('users')->onDelete('cascade');

            // Performance indexes
            $table->index('sender_id');
            $table->index('receiver_id');
            $table->index('message_type');
            $table->index('created_at');
            $table->index(['sender_id', 'receiver_id']);
            $table->index(['receiver_id', 'read_at']);
            $table->index(['sender_id', 'receiver_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('chat_messages');
    }
};