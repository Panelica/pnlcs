<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/*
 * What the customer buys is hosting - memory, CPU, disk. Apps run inside it.
 *
 * The first wording read like the apps were the product ("1 app", "choose your
 * app" as a required step), which left people asking whether they were buying
 * an application or a plan. They are buying the plan; the app is what they do
 * with it, and how many they can run is a question of resources.
 */
return new class extends Migration
{
    private function rows(): array
    {
        return [
            // Store card
            'store.res_apps' => '{1} Run 1 app|[2,*] Run up to :count apps',
            'store.res_shared_note' => 'Apps run inside these limits and share them. How many you can run at once is really a question of memory: small apps need a few hundred MB, larger ones a gigabyte or more.',

            // Order form
            'cart.choose_app' => 'Start with an app',
            'cart.app_optional' => 'optional',
            'cart.app_intro' => 'You are buying the hosting above. If you already know what you want to run, pick it here and it will be installed and pointed at your domain when your account is created - otherwise skip this and install whatever you like from your control panel afterwards.',
            'cart.app_clear' => 'clear selection',
            'cart.app_not_available' => 'That app is not available on this plan.',
        ];
    }

    public function up(): void
    {
        $now = now();
        foreach ($this->rows() as $key => $value) {
            $row = DB::table('dynamic_translations')->where('language', 'en')->where('group', 'client')->where('key', $key);
            $row->exists()
                ? $row->update(['value' => $value, 'updated_at' => $now])
                : DB::table('dynamic_translations')->insert(['language' => 'en', 'group' => 'client', 'key' => $key, 'value' => $value, 'is_auto_translated' => false, 'is_reviewed' => true, 'created_at' => $now, 'updated_at' => $now]);
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
        DB::table('dynamic_translations')->where('language', 'en')->where('group', 'client')
            ->whereIn('key', ['cart.app_optional', 'cart.app_intro', 'cart.app_clear', 'cart.app_not_available'])->delete();
    }
};
