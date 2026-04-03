<?php

use FrittenKeeZ\Vouchers\Config;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Change metadata columns from TEXT to LONGTEXT.
 *
 * TEXT columns in MySQL have a 65,535 byte limit which is insufficient
 * when storing large base64-encoded images (signatures, selfies) in metadata.
 * LONGTEXT supports up to 4GB, which is more than sufficient.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Change metadata column in vouchers table
        Schema::table(Config::table('vouchers'), function (Blueprint $table) {
            $table->longText('metadata')->nullable()->change();
        });

        // Change metadata column in redeemers table
        Schema::table(Config::table('redeemers'), function (Blueprint $table) {
            $table->longText('metadata')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert to TEXT type
        Schema::table(Config::table('vouchers'), function (Blueprint $table) {
            $table->text('metadata')->nullable()->change();
        });

        Schema::table(Config::table('redeemers'), function (Blueprint $table) {
            $table->text('metadata')->nullable()->change();
        });
    }
};
