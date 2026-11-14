<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Menambahkan kolom PayPal Webhook ID ke tab Payment.
 *
 * Nilai ini sudah dibaca oleh PaypalGateway::verifikasiTandaTangan(), tetapi
 * tidak pernah punya baris pengaturan — artinya admin tidak bisa mengisinya
 * dari panel dan setiap webhook PayPal selalu ditolak. Baris ini membuat
 * nilainya dapat diisi, sekaligus dapat diperiksa oleh peringatan dashboard.
 */
return new class extends Migration
{
    public function up(): void
    {
        $sudahAda = DB::table('settings')->where('key', 'paypal_webhook_id')->exists();

        if ($sudahAda) {
            return;
        }

        DB::table('settings')->insert([
            'key' => 'paypal_webhook_id',
            'value' => null,
            'group_name' => 'payment',
            'type' => 'text',
            'label' => 'PayPal Webhook ID',
            'options_json' => null,
            'is_encrypted' => false,
            'is_public' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('settings')->where('key', 'paypal_webhook_id')->delete();
    }
};
