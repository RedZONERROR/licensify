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
        Schema::create('backups', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // Backup name
            $table->string('filename'); // Backup filename
            $table->string('path')->nullable(); // Storage path
            $table->bigInteger('size')->nullable(); // File size in bytes
            $table->string('checksum')->nullable(); // File integrity checksum
            $table->enum('status', ['pending', 'running', 'completed', 'failed', 'expired'])->default('pending');
            $table->enum('type', ['manual', 'scheduled', 'pre_restore'])->default('manual');
            $table->unsignedBigInteger('created_by')->nullable(); // User who created the backup
            $table->json('metadata')->nullable(); // Additional backup info
            $table->timestamp('expires_at')->nullable(); // Retention expiry
            $table->timestamp('completed_at')->nullable(); // When backup completed
            $table->text('error_message')->nullable(); // Error details if failed
            $table->timestamps();

            // Foreign key constraint
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');

            // Performance indexes
            $table->index('status');
            $table->index('type');
            $table->index('created_by');
            $table->index('expires_at');
            $table->index('created_at');
            $table->index(['status', 'type']);
            $table->index(['created_by', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('backups');
    }
};