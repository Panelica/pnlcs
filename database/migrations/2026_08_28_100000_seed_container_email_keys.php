<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

// Words for mailing an app's connection details to the client on request, and
// for the folded notes block that used to sit on the page as a wall of text.
return new class extends Migration
{
    /** @return array<string, array<string, string>> language => key => value */
    private function rows(): array
    {
        return [
            'en' => [
                'hosting.containers.notes_title' => 'Good to know',
                'hosting.containers.email_details' => 'Email me these details',
                'hosting.containers.email_subject' => 'Your :app connection details',
                'hosting.containers.email_title' => 'Your :app is ready',
                'hosting.containers.email_intro' => 'Here are the connection details of the app you installed. Keep this message safe - it contains generated passwords.',
                'hosting.containers.email_footer' => 'You can view these details any time from your client area.',
                'hosting.containers.email_sent' => 'The connection details were sent to :email.',
                'hosting.containers.email_not_found' => 'There are no stored connection details for that app.',
            ],
            'tr' => [
                'hosting.containers.notes_title' => 'Bilmekte fayda var',
                'hosting.containers.email_details' => 'Bilgileri e-postama gönder',
                'hosting.containers.email_subject' => ':app bağlantı bilgileriniz',
                'hosting.containers.email_title' => ':app kullanıma hazır',
                'hosting.containers.email_intro' => 'Kurduğunuz uygulamanın bağlantı bilgileri aşağıdadır. Bu mesajı güvende tutun - üretilmiş parolalar içerir.',
                'hosting.containers.email_footer' => 'Bu bilgilere müşteri panelinizden istediğiniz zaman ulaşabilirsiniz.',
                'hosting.containers.email_sent' => 'Bağlantı bilgileri :email adresine gönderildi.',
                'hosting.containers.email_not_found' => 'Bu uygulama için kayıtlı bağlantı bilgisi yok.',
            ],
        ];
    }

    public function up(): void
    {
        $now = now();
        foreach ($this->rows() as $language => $keys) {
            foreach ($keys as $key => $value) {
                if (! DB::table('dynamic_translations')->where('language', $language)->where('group', 'client')->where('key', $key)->exists()) {
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
        $keys = [];
        foreach ($this->rows() as $languageKeys) {
            $keys = array_merge($keys, array_keys($languageKeys));
        }
        DB::table('dynamic_translations')->where('group', 'client')->whereIn('key', array_unique($keys))->delete();
        try {
            foreach (DB::table('dynamic_translations')->distinct()->pluck('language') as $lang) {
                Cache::forget("translations:{$lang}:client");
            }
        } catch (\Throwable $e) {
        }
    }
};
