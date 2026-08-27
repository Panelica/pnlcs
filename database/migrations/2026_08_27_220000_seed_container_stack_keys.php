<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

// A template deploys one app as several containers (WordPress + its database +
// its cache). The page now draws them as one app with components, so it needs
// words for the component row, the grouped delete confirmation, and the refusal
// a customer gets when a raw request targets a component on its own.
return new class extends Migration
{
    /** @return array<string, array<string, string>> language => key => value */
    private function rows(): array
    {
        return [
            'en' => [
                'hosting.containers.components' => 'Components',
                'hosting.containers.delete_confirm_stack' => 'Remove this app and its :count component(s)? All of its data will be deleted and cannot be recovered.',
                'hosting.containers.component_delete_refused' => 'That is a component of an app (its database or cache). Remove the app itself; its components are removed with it.',
            ],
            'tr' => [
                'hosting.containers.components' => 'Bileşenler',
                'hosting.containers.delete_confirm_stack' => 'Bu uygulama ve :count bileşeni kaldırılsın mı? Tüm verileri silinir ve geri getirilemez.',
                'hosting.containers.component_delete_refused' => 'Bu, bir uygulamanın bileşenidir (veritabanı veya önbelleği). Uygulamanın kendisini kaldırın; bileşenleri onunla birlikte kaldırılır.',
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
