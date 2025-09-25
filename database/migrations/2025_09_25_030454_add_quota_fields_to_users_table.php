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
        Schema::table('users', function (Blueprint $table) {
            // Add quota fields for resellers
            $table->integer('max_users_quota')->nullable()->after('reseller_id');
            $table->integer('max_licenses_quota')->nullable()->after('max_users_quota');
            $table->integer('current_users_count')->default(0)->after('max_licenses_quota');
            $table->integer('current_licenses_count')->default(0)->after('current_users_count');
            
            // Add indexes for performance
            $table->index(['role', 'max_users_quota']);
            $table->index(['role', 'max_licenses_quota']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['role', 'max_users_quota']);
            $table->dropIndex(['role', 'max_licenses_quota']);
            $table->dropColumn([
                'max_users_quota',
                'max_licenses_quota', 
                'current_users_count',
                'current_licenses_count'
            ]);
        });
    }
};
