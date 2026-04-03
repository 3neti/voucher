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
        Schema::table('vouchers', function (Blueprint $table) {
            $table->string('voucher_type', 20)->default('redeemable')->after('code');
            $table->string('state', 20)->default('active')->after('voucher_type');
            $table->json('rules')->nullable()->after('state');
            $table->bigInteger('target_amount')->nullable()->comment('Target amount in minor units (centavos)');
            $table->timestamp('locked_at')->nullable();
            $table->timestamp('closed_at')->nullable();

            // Add indexes for frequent queries
            $table->index('voucher_type');
            $table->index('state');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vouchers', function (Blueprint $table) {
            $table->dropIndex(['voucher_type']);
            $table->dropIndex(['state']);
            $table->dropColumn([
                'voucher_type',
                'state',
                'rules',
                'target_amount',
                'locked_at',
                'closed_at',
            ]);
        });
    }
};
