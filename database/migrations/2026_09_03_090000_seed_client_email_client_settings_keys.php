<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Strings for the mail client settings card on the customer's email page.
 *
 * Seeds Turkish alongside English: the existing hosting.email.* keys were only
 * ever seeded in English, so a Turkish customer saw an English page. The new
 * card would have made that worse by sitting under English headings, so the
 * older keys are translated here too.
 *
 * Cached translation groups are flushed so the strings appear immediately
 * (see 2026_08_13_150000_flush_stale_translation_cache).
 */
return new class extends Migration
{
    /** [key => [en, tr]] */
    private function rows(): array
    {
        return [
            // New: the mail client settings card.
            'hosting.email.settings_title' => ['Mail Client Settings', 'Posta Programı Ayarları'],
            'hosting.email.settings_note' => [
                'Use these settings to add a mailbox to Outlook, your phone or any other mail program. Most programs find them on their own once you enter the address and password.',
                'Bir posta kutusunu Outlook\'a, telefonunuza veya başka bir posta programına eklemek için bu ayarları kullanın. Çoğu program adres ile parolayı yazdığınızda ayarları kendisi bulur.',
            ],
            'hosting.email.settings_purpose' => ['Purpose', 'Ne için'],
            'hosting.email.settings_server' => ['Server', 'Sunucu'],
            'hosting.email.settings_port' => ['Port', 'Port'],
            'hosting.email.settings_security' => ['Security', 'Güvenlik'],
            'hosting.email.settings_incoming_recommended' => ['incoming — recommended', 'gelen posta — önerilen'],
            'hosting.email.settings_incoming_alt' => ['incoming — alternative', 'gelen posta — alternatif'],
            'hosting.email.settings_outgoing' => ['outgoing', 'giden posta'],
            'hosting.email.settings_outgoing_alt' => ['outgoing — alternative', 'giden posta — alternatif'],
            'hosting.email.settings_username' => [
                'Username is the full email address; the password is the mailbox password. Outgoing mail requires authentication.',
                'Kullanıcı adı e-posta adresinin tamamıdır, parola ise kutu parolasıdır. Giden posta için kimlik doğrulama gereklidir.',
            ],
            'hosting.email.settings_guide' => ['Setup guide', 'Kurulum rehberi'],

            // Existing: these had only ever been seeded in English.
            'hosting.email.accounts_title' => ['Mailboxes', 'Posta Kutuları'],
            'hosting.email.address' => ['Address', 'Adres'],
            'hosting.email.change_password' => ['Password', 'Parola'],
            'hosting.email.create_button' => ['Create', 'Oluştur'],
            'hosting.email.create_title' => ['Create Mailbox', 'Posta Kutusu Oluştur'],
            'hosting.email.delete' => ['Delete', 'Sil'],
            'hosting.email.delete_confirm' => ['Delete this mailbox? This cannot be undone.', 'Bu posta kutusu silinsin mi? Bu işlem geri alınamaz.'],
            'hosting.email.domain' => ['Domain', 'Alan adı'],
            'hosting.email.empty' => ['No mailboxes yet.', 'Henüz posta kutusu yok.'],
            'hosting.email.mailbox_name' => ['Mailbox name', 'Kutu adı'],
            'hosting.email.mailboxes' => ['mailboxes', 'posta kutusu'],
            'hosting.email.new_password' => ['New password', 'Yeni parola'],
            'hosting.email.no_domains' => ['No domains are set up on this service yet.', 'Bu hizmette tanımlı alan adı yok.'],
            'hosting.email.password' => ['Password', 'Parola'],
            'hosting.email.quota_mb' => ['Quota (MB)', 'Kota (MB)'],
            'hosting.email.save' => ['Save', 'Kaydet'],
            'hosting.email.subtitle' => ['Create and manage mailboxes for your domains.', 'Alan adlarınız için posta kutusu oluşturun ve yönetin.'],
            'hosting.email.title' => ['Email Accounts', 'E-posta Hesapları'],
            'hosting.email.unlimited' => ['Unlimited', 'Sınırsız'],
            'hosting.email.usage' => ['Usage', 'Kullanım'],
            'hosting.email.webmail' => ['Open Webmail', 'Webmail\'i Aç'],
        ];
    }

    public function up(): void
    {
        $now = now();
        foreach ($this->rows() as $key => [$en, $tr]) {
            foreach (['en' => $en, 'tr' => $tr] as $language => $value) {
                $exists = DB::table('dynamic_translations')
                    ->where('language', $language)->where('group', 'client')->where('key', $key)->exists();
                if (! $exists) {
                    DB::table('dynamic_translations')->insert([
                        'language' => $language, 'group' => 'client', 'key' => $key, 'value' => $value,
                        'is_auto_translated' => false, 'is_reviewed' => true,
                        'created_at' => $now, 'updated_at' => $now,
                    ]);
                }
            }
        }
        try {
            foreach (DB::table('dynamic_translations')->distinct()->pluck('language') as $lang) {
                Cache::forget("translations:{$lang}:client");
            }
        } catch (\Throwable $e) {
        }
    }

    public function down(): void
    {
        // Only the rows this migration added: the English ones already existed.
        $new = array_filter(array_keys($this->rows()), fn ($k) => str_starts_with($k, 'hosting.email.settings_'));

        DB::table('dynamic_translations')
            ->where('group', 'client')->where('language', 'en')->whereIn('key', $new)->delete();

        DB::table('dynamic_translations')
            ->where('group', 'client')->where('language', 'tr')
            ->whereIn('key', array_keys($this->rows()))->delete();
    }
};
