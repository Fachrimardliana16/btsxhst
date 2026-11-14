<?php

namespace Database\Seeders;

use App\Enums\GrupPengaturan;
use App\Models\Setting;
use App\Support\Pengaturan;
use Illuminate\Database\Seeder;

/**
 * Seluruh kunci pengaturan pada PRD Bagian 7.4.
 *
 * Idempoten: memakai updateOrCreate berdasarkan key, jadi dapat dijalankan
 * berulang tanpa menggandakan baris.
 */
class SettingSeeder extends Seeder
{
    public function run(): void
    {
        $G = fn (GrupPengaturan $g) => $g->value;

        $baris = [
            // ── Tab 1: Identity ───────────────────────────────────────
            ['site_name', 'Bites Exhaust', $G(GrupPengaturan::Identitas), 'text', 'Store name', false, true],
            ['site_tagline', 'Precision exhausts, measured before they are cut.', $G(GrupPengaturan::Identitas), 'text', 'Tagline', false, true],
            ['site_logo_light', null, $G(GrupPengaturan::Identitas), 'image', 'Light theme logo', false, true],
            ['site_logo_dark', null, $G(GrupPengaturan::Identitas), 'image', 'Dark theme logo', false, true],
            ['site_favicon', null, $G(GrupPengaturan::Identitas), 'image', 'Favicon', false, true],
            ['site_description', 'Custom and racing exhaust shop in Yogyakarta. Full systems, slip-ons, headers, and mufflers for daily bikes and competition.', $G(GrupPengaturan::Identitas), 'textarea', 'Meta description', false, true],

            // ── Tab 2: Page Content ───────────────────────────────────
            // About page content and the three Workshop Service cards on the homepage.
            // Values start empty: sections stay hidden until an admin fills them in.
            ['about_judul', null, $G(GrupPengaturan::Konten), 'text', 'About page heading', false, true],
            ['about_proses', null, $G(GrupPengaturan::Konten), 'textarea', 'Process paragraph (About)', false, true],
            ['about_langkah_1_judul', null, $G(GrupPengaturan::Konten), 'text', 'Step 1 — heading', false, true],
            ['about_langkah_1_isi', null, $G(GrupPengaturan::Konten), 'textarea', 'Step 1 — body', false, true],
            ['about_langkah_2_judul', null, $G(GrupPengaturan::Konten), 'text', 'Step 2 — heading', false, true],
            ['about_langkah_2_isi', null, $G(GrupPengaturan::Konten), 'textarea', 'Step 2 — body', false, true],
            ['about_langkah_3_judul', null, $G(GrupPengaturan::Konten), 'text', 'Step 3 — heading', false, true],
            ['about_langkah_3_isi', null, $G(GrupPengaturan::Konten), 'textarea', 'Step 3 — body', false, true],
            ['cara_pesan_judul', null, $G(GrupPengaturan::Konten), 'text', 'Ordering steps section heading', false, true],
            ['cara_pesan_langkah_1', null, $G(GrupPengaturan::Konten), 'textarea', 'Ordering step 1', false, true],
            ['cara_pesan_langkah_2', null, $G(GrupPengaturan::Konten), 'textarea', 'Ordering step 2', false, true],
            ['cara_pesan_langkah_3', null, $G(GrupPengaturan::Konten), 'textarea', 'Ordering step 3', false, true],
            ['cara_pesan_langkah_4', null, $G(GrupPengaturan::Konten), 'textarea', 'Ordering step 4', false, true],
            ['layanan_judul', null, $G(GrupPengaturan::Konten), 'text', 'Services section heading', false, true],
            ['layanan_1_judul', null, $G(GrupPengaturan::Konten), 'text', 'Service 1 — heading', false, true],
            ['layanan_1_isi', null, $G(GrupPengaturan::Konten), 'textarea', 'Service 1 — body', false, true],
            ['layanan_2_judul', null, $G(GrupPengaturan::Konten), 'text', 'Service 2 — heading', false, true],
            ['layanan_2_isi', null, $G(GrupPengaturan::Konten), 'textarea', 'Service 2 — body', false, true],
            ['layanan_3_judul', null, $G(GrupPengaturan::Konten), 'text', 'Service 3 — heading', false, true],
            ['layanan_3_isi', null, $G(GrupPengaturan::Konten), 'textarea', 'Service 3 — body', false, true],
            ['cta_judul', null, $G(GrupPengaturan::Konten), 'text', 'Homepage CTA panel heading', false, true],
            ['cta_isi', null, $G(GrupPengaturan::Konten), 'textarea', 'Homepage CTA panel paragraph', false, true],

            // ── Tab 3: Contact and Location ───────────────────────────
            ['whatsapp_number', '628123456789', $G(GrupPengaturan::Kontak), 'text', 'WhatsApp number', false, true],
            ['whatsapp_message_template', 'Hi Bites Exhaust, I would like to ask about an exhaust.', $G(GrupPengaturan::Kontak), 'textarea', 'WhatsApp greeting template', false, true],
            ['contact_email', 'halo@bitesexhaust.test', $G(GrupPengaturan::Kontak), 'email', 'Public email', false, true],
            ['contact_email_cc', null, $G(GrupPengaturan::Kontak), 'email', 'Notification copy email', false, true],
            ['phone_number', '(0274) 123456', $G(GrupPengaturan::Kontak), 'text', 'Office phone', false, true],
            ['operational_hours', 'Monday - Saturday, 08.00 - 17.00 WIB', $G(GrupPengaturan::Kontak), 'text', 'Operating hours', false, true],
            ['address_full', 'Jl. Ringroad Utara No. 128, Condongcatur, Depok, Sleman, DI Yogyakarta 55283', $G(GrupPengaturan::Kontak), 'textarea', 'Full address', false, true],
            ['maps_embed', null, $G(GrupPengaturan::Kontak), 'textarea', 'Google Maps embed', false, true],
            ['maps_lat', '-7.7626', $G(GrupPengaturan::Kontak), 'number', 'Latitude', false, true],
            ['maps_lng', '110.3942', $G(GrupPengaturan::Kontak), 'number', 'Longitude', false, true],

            // ── Tab 3: Social Media ───────────────────────────────────
            ['social_instagram', 'https://instagram.com/bitesexhaust', $G(GrupPengaturan::Sosial), 'url', 'Instagram', false, true],
            ['social_instagram_visible', '1', $G(GrupPengaturan::Sosial), 'boolean', 'Show Instagram', false, true],
            ['social_facebook', null, $G(GrupPengaturan::Sosial), 'url', 'Facebook', false, true],
            ['social_facebook_visible', '0', $G(GrupPengaturan::Sosial), 'boolean', 'Show Facebook', false, true],
            ['social_tiktok', 'https://tiktok.com/@bitesexhaust', $G(GrupPengaturan::Sosial), 'url', 'TikTok', false, true],
            ['social_tiktok_visible', '1', $G(GrupPengaturan::Sosial), 'boolean', 'Show TikTok', false, true],
            ['social_youtube', null, $G(GrupPengaturan::Sosial), 'url', 'YouTube', false, true],
            ['social_youtube_visible', '0', $G(GrupPengaturan::Sosial), 'boolean', 'Show YouTube', false, true],
            ['social_shopee', 'https://shopee.co.id/bitesexhaust', $G(GrupPengaturan::Sosial), 'url', 'Shopee', false, true],
            ['social_shopee_visible', '1', $G(GrupPengaturan::Sosial), 'boolean', 'Show Shopee', false, true],
            ['social_tokopedia', null, $G(GrupPengaturan::Sosial), 'url', 'Tokopedia', false, true],
            ['social_tokopedia_visible', '0', $G(GrupPengaturan::Sosial), 'boolean', 'Show Tokopedia', false, true],

            // ── Tab 4: Payment ────────────────────────────────────────
            ['payment_mode', 'manual', $G(GrupPengaturan::Pembayaran), 'select', 'Payment mode', false, false, ['paypal', 'manual', 'both']],
            ['paypal_client_id', 'SANDBOX-CLIENT-ID-CONTOH', $G(GrupPengaturan::Pembayaran), 'text', 'PayPal Client ID', true, false],
            ['paypal_client_secret', 'SANDBOX-SECRET-CONTOH', $G(GrupPengaturan::Pembayaran), 'text', 'PayPal Client Secret', true, false],
            ['paypal_mode', 'sandbox', $G(GrupPengaturan::Pembayaran), 'select', 'PayPal mode', false, false, ['sandbox', 'live']],
            ['paypal_webhook_id', null, $G(GrupPengaturan::Pembayaran), 'text', 'PayPal Webhook ID', false, false],
            ['manual_bank_accounts', json_encode([
                ['bank' => 'BCA', 'number' => '1234567890', 'holder' => 'Bites Exhaust'],
                ['bank' => 'Mandiri', 'number' => '0987654321', 'holder' => 'Bites Exhaust'],
            ]), $G(GrupPengaturan::Pembayaran), 'json', 'Manual transfer accounts', true, false],
            ['tax_percent', '0', $G(GrupPengaturan::Pembayaran), 'number', 'Tax (%)', false, false],
            ['shipping_flat_rate', '0', $G(GrupPengaturan::Pembayaran), 'number', 'Flat shipping rate', false, false],
            ['shipping_couriers', json_encode(['JNE', 'J&T Express', 'SiCepat', 'Pos Indonesia']), $G(GrupPengaturan::Pembayaran), 'json', 'Courier list', false, true],
            ['shipping_courier_default', 'JNE', $G(GrupPengaturan::Pembayaran), 'text', 'Default courier', false, true],
            // ── Tab: Email (SMTP) ─────────────────────────────────────
            //
            // Konfigurasi pengiriman email diisi DARI PANEL, bukan dari .env.
            // Alasannya praktis: pemilik toko yang memegang akun emailnya
            // sendiri, dan meminta ia menyunting berkas .env di server lalu
            // menjalankan `config:clear` bukan hal yang wajar.
            //
            // Nilai di sini MENIMPA .env ketika terisi. Bila dibiarkan kosong,
            // konfigurasi .env yang dipakai — sehingga pemasangan lama tetap
            // berjalan tanpa perubahan.
            ['mail_mailer', 'smtp', $G(GrupPengaturan::Email), 'select', 'Delivery method', false, false, ['smtp', 'log']],
            ['mail_host', '', $G(GrupPengaturan::Email), 'text', 'SMTP Host', false, false],
            ['mail_port', '587', $G(GrupPengaturan::Email), 'number', 'SMTP Port', false, false],
            ['mail_username', '', $G(GrupPengaturan::Email), 'text', 'SMTP Username', false, false],
            ['mail_password', '', $G(GrupPengaturan::Email), 'text', 'SMTP Password', true, false],
            ['mail_encryption', 'tls', $G(GrupPengaturan::Email), 'select', 'Encryption', false, false, ['tls', 'ssl', 'none']],
            ['mail_from_address', '', $G(GrupPengaturan::Email), 'text', 'Sender email', false, false],
            ['mail_from_name', '', $G(GrupPengaturan::Email), 'text', 'Sender name', false, false],
            ['admin_email', '', $G(GrupPengaturan::Email), 'text', 'Admin email (order recipient)', false, false],



            // ── Tab 5: Notifications ──────────────────────────────────
            // Panel bell item switches. Default 1 matches the behaviour before this
            // tab existed, so running stores do not lose notifications just
            // because the rows were created later.
            ['notif_item_stok_habis', '1', $G(GrupPengaturan::Notifikasi), 'boolean', 'Out of stock', false, false],
            ['notif_item_stok_menipis', '1', $G(GrupPengaturan::Notifikasi), 'boolean', 'Low stock', false, false],
            ['notif_item_pesanan_siap_kirim', '1', $G(GrupPengaturan::Notifikasi), 'boolean', 'Order ready to ship', false, false],
            ['notif_item_hampir_batal', '1', $G(GrupPengaturan::Notifikasi), 'boolean', 'Order about to be cancelled automatically', false, false],
            ['notif_item_masuk_keranjang', '1', $G(GrupPengaturan::Notifikasi), 'boolean', 'Product added to cart', false, false],

            // Delivery switches. Values follow the old (env) config so existing
            // deployments using IKK_NOTIF_* keep working unchanged.
            ['notif_kirim_dibuat', '1', $G(GrupPengaturan::Notifikasi), 'boolean', 'Email/WhatsApp when an order is created', false, false],
            ['notif_kirim_dibayar', '1', $G(GrupPengaturan::Notifikasi), 'boolean', 'Email/WhatsApp when an order is paid', false, false],
            ['notif_kirim_dikirim', '1', $G(GrupPengaturan::Notifikasi), 'boolean', 'Email/WhatsApp when an order is shipped', false, false],
            ['notif_push_perangkat', '1', $G(GrupPengaturan::Notifikasi), 'boolean', 'Push to admin devices', false, false],
            ['notif_push_surel_cadangan', '1', $G(GrupPengaturan::Notifikasi), 'boolean', 'Backup email when push fails', false, false],

            // ── Tab 6: Advanced ───────────────────────────────────────
            ['maintenance_mode', '0', $G(GrupPengaturan::Lanjutan), 'boolean', 'Maintenance mode', false, true],
            ['maintenance_message', 'The site is down for brief maintenance.', $G(GrupPengaturan::Lanjutan), 'textarea', 'Maintenance message', false, true],
            ['analytics_id', null, $G(GrupPengaturan::Lanjutan), 'text', 'ID Analytics', false, true],
            ['meta_pixel_id', null, $G(GrupPengaturan::Lanjutan), 'text', 'ID Meta Pixel', false, true],
            ['order_prefix', 'IK', $G(GrupPengaturan::Lanjutan), 'text', 'Order number prefix', false, true],
            ['low_stock_threshold', '3', $G(GrupPengaturan::Lanjutan), 'number', 'Low stock threshold', false, true],
            ['enable_pwa', '0', $G(GrupPengaturan::Lanjutan), 'boolean', 'Enable PWA', false, true],
            ['date_format', 'd M Y', $G(GrupPengaturan::Lanjutan), 'text', 'Date format', false, true],
        ];

        foreach ($baris as $b) {
            [$key, $value, $group, $type, $label, $encrypted, $public] = $b;
            $options = $b[7] ?? null;

            // is_encrypted HARUS diisi sebelum value.
            //
            // Cast NilaiPengaturan memutuskan enkripsi dengan membaca
            // $attributes['is_encrypted']. Laravel mengisi atribut sesuai urutan
            // larik, jadi bila `value` lebih dulu, cast belum melihat tanda
            // enkripsi dan nilainya tersimpan sebagai teks biasa — sementara
            // `is_encrypted` tetap tercatat true. Akibatnya baris itu tidak
            // dapat dibaca lagi: cast mencoba mendekripsi teks biasa, gagal,
            // lalu mengembalikan null. Rekening transfer tampak kosong padahal
            // isinya ada di basis data.
            $setting = Setting::query()->firstOrNew(['key' => $key]);

            $setting->fill([
                'group_name' => $group,
                'type' => $type,
                'label' => $label,
                'options_json' => $options,
                'is_encrypted' => $encrypted,
                'is_public' => $public,
            ]);

            $setting->value = $value;
            $setting->save();
        }

        Pengaturan::lupakan();

        $this->command?->info('SettingSeeder: '.count($baris).' kunci pengaturan.');
    }
}
