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
            $table->integer('failed_login_attempts')->default(0)->after('remember_token');
            $table->timestamp('locked_until')->nullable()->after('failed_login_attempts');
            $table->timestamp('last_failed_login_at')->nullable()->after('locked_until');
            $table->string('last_failed_login_ip', 45)->nullable()->after('last_failed_login_at');
            
            // Indexes for performance
            $table->index('locked_until');
            $table->index('last_failed_login_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['locked_until']);
            $table->dropIndex(['last_failed_login_at']);
            $table->dropColumn([
                'failed_login_attempts',
                'locked_until',
                'last_failed_login_at',
                'last_failed_login_ip'
            ]);
        });
    }
};
