<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Turkish, Polish and Chinese for the runtime-app tabs (the three fully
 * maintained locales). The English keys ship in the seed before this; the
 * other locales fall back to English until an operator runs the admin
 * OpenAI auto-translate with their own key.
 */
return new class extends Migration
{
    private function rows(): array
    {
        return [
            'tr' => [
                'hosting.laravel.title'       => 'Laravel Uygulamaları',
                'hosting.laravel.subtitle'    => 'Bu hostingdeki Laravel uygulamalarınız.',
                'hosting.nodejs.title'        => 'Node.js Uygulamaları',
                'hosting.nodejs.subtitle'     => 'Bu hostingdeki Node.js uygulamalarınız.',
                'hosting.python.title'        => 'Python Uygulamaları',
                'hosting.python.subtitle'     => 'Bu hostingdeki Python uygulamalarınız.',
                'hosting.runtime.domain'      => 'Alan Adı',
                'hosting.runtime.version'     => 'Sürüm',
                'hosting.runtime.open'        => 'Aç',
                'hosting.runtime.empty_title' => 'Henüz uygulama yok',
                'hosting.runtime.empty_hint'  => 'Uygulamaları hosting kontrol panelinizden oluşturup dağıtabilirsiniz.',
            ],
            'pl' => [
                'hosting.laravel.title'       => 'Aplikacje Laravel',
                'hosting.laravel.subtitle'    => 'Twoje aplikacje Laravel na tym hostingu.',
                'hosting.nodejs.title'        => 'Aplikacje Node.js',
                'hosting.nodejs.subtitle'     => 'Twoje aplikacje Node.js na tym hostingu.',
                'hosting.python.title'        => 'Aplikacje Python',
                'hosting.python.subtitle'     => 'Twoje aplikacje Python na tym hostingu.',
                'hosting.runtime.domain'      => 'Domena',
                'hosting.runtime.version'     => 'Wersja',
                'hosting.runtime.open'        => 'Otwórz',
                'hosting.runtime.empty_title' => 'Brak aplikacji',
                'hosting.runtime.empty_hint'  => 'Twórz i wdrażaj aplikacje z panelu sterowania hostingiem.',
            ],
            'zh' => [
                'hosting.laravel.title'       => 'Laravel 应用',
                'hosting.laravel.subtitle'    => '您在此主机上的 Laravel 应用。',
                'hosting.nodejs.title'        => 'Node.js 应用',
                'hosting.nodejs.subtitle'     => '您在此主机上的 Node.js 应用。',
                'hosting.python.title'        => 'Python 应用',
                'hosting.python.subtitle'     => '您在此主机上的 Python 应用。',
                'hosting.runtime.domain'      => '域名',
                'hosting.runtime.version'     => '版本',
                'hosting.runtime.open'        => '打开',
                'hosting.runtime.empty_title' => '暂无应用',
                'hosting.runtime.empty_hint'  => '请从主机控制面板创建和部署应用。',
            ],
        ];
    }

    public function up(): void
    {
        $now = now();
        foreach ($this->rows() as $lang => $pairs) {
            foreach ($pairs as $key => $value) {
                if (! DB::table('dynamic_translations')->where('language', $lang)->where('group', 'client')->where('key', $key)->exists()) {
                    DB::table('dynamic_translations')->insert(['language' => $lang, 'group' => 'client', 'key' => $key, 'value' => $value, 'is_auto_translated' => false, 'is_reviewed' => true, 'created_at' => $now, 'updated_at' => $now]);
                }
            }
            Cache::forget("translations:{$lang}:client");
        }
    }

    public function down(): void
    {
        foreach ($this->rows() as $lang => $pairs) {
            DB::table('dynamic_translations')->where('language', $lang)->where('group', 'client')->whereIn('key', array_keys($pairs))->delete();
        }
    }
};
