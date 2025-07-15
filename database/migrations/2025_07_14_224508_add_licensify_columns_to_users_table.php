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
            $table->enum('role', ['owner', 'developer', 'vendor', 'sub-vendor', 'reseller', 'user'])->after('password');
            $table->foreignId('parent_id')->nullable()->after('role')->constrained('users')->onDelete('set null');
            $table->decimal('wallet_balance', 15, 2)->default(0.00)->after('parent_id');
            $table->string('currency', 10)->default('USD')->after('wallet_balance');
            $table->enum('status', ['active', 'suspended'])->default('active')->after('currency');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['parent_id']);
            $table->dropColumn(['role', 'parent_id', 'wallet_balance', 'currency', 'status']);
        });
    }
};
