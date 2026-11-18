<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Batas pemakaian kupon per pelanggan.
 *
 * Kuota global saja tidak mencegah satu orang menghabiskan seluruh kuota
 * kupon promosi. Kolom ini opsional: NULL berarti kupon boleh dipakai
 * berkali-kali oleh orang yang sama selama kuota global masih ada.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('coupons', function (Blueprint $table): void {
            $table->unsignedInteger('limit_per_customer')->nullable()->after('quota');
        });
    }

    public function down(): void
    {
        Schema::table('coupons', function (Blueprint $table): void {
            $table->dropColumn('limit_per_customer');
        });
    }
};
