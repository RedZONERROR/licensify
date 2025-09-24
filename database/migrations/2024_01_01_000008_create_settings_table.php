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
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique(); // Setting key (e.g., 'smtp.host')
            $table->text('value')->nullable(); // Encrypted value for sensitive settings
            $table->string('type')->default('string'); // string, integer, boolean, json, encrypted
            $table->string('category')->default('general'); // general, email, storage, integrations, devops
            $table->text('description')->nullable();
            $table->boolean('is_encrypted')->default(false);
            $table->boolean('is_public')->default(false); // Can be accessed by non-admin users
            $table->json('validation_rules')->nullable(); // Laravel validation rules
            $table->timestamps();

            // Performance indexes
            $table->index('key');
            $table->index('category');
            $table->index('is_public');
            $table->index(['category', 'is_public']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};