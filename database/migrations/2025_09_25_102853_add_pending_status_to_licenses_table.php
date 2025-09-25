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
        // For SQLite, we need to recreate the table to modify the enum
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            // Create temporary table with new enum values
            Schema::create('licenses_temp', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('product_id');
                $table->unsignedBigInteger('owner_id');
                $table->unsignedBigInteger('user_id')->nullable();
                $table->string('license_key')->unique();
                $table->enum('status', ['active', 'expired', 'suspended', 'reset', 'pending'])->default('active');
                $table->string('device_type')->nullable();
                $table->integer('max_devices')->default(1);
                $table->timestamp('expires_at')->nullable();
                $table->json('metadata')->nullable();
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

            // Copy data from original table
            DB::statement('INSERT INTO licenses_temp SELECT * FROM licenses');

            // Drop original table and rename temp table
            Schema::dropIfExists('licenses');
            Schema::rename('licenses_temp', 'licenses');
        } else {
            // For MySQL/PostgreSQL, we can modify the enum directly
            DB::statement("ALTER TABLE licenses MODIFY COLUMN status ENUM('active', 'expired', 'suspended', 'reset', 'pending') DEFAULT 'active'");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // For SQLite, we need to recreate the table to modify the enum
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            // Create temporary table with original enum values
            Schema::create('licenses_temp', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('product_id');
                $table->unsignedBigInteger('owner_id');
                $table->unsignedBigInteger('user_id')->nullable();
                $table->string('license_key')->unique();
                $table->enum('status', ['active', 'expired', 'suspended', 'reset'])->default('active');
                $table->string('device_type')->nullable();
                $table->integer('max_devices')->default(1);
                $table->timestamp('expires_at')->nullable();
                $table->json('metadata')->nullable();
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

            // Copy data from original table (excluding pending status)
            DB::statement("INSERT INTO licenses_temp SELECT * FROM licenses WHERE status != 'pending'");

            // Drop original table and rename temp table
            Schema::dropIfExists('licenses');
            Schema::rename('licenses_temp', 'licenses');
        } else {
            // For MySQL/PostgreSQL, we can modify the enum directly
            DB::statement("ALTER TABLE licenses MODIFY COLUMN status ENUM('active', 'expired', 'suspended', 'reset') DEFAULT 'active'");
        }
    }
};