<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** Search, sections and resource labels for the app catalogue. */
    private function rows(): array
    {
        return [
            'hosting.containers.search_ph' => 'Search apps - try wordpress, database, backup...',
            'hosting.containers.search_clear' => 'Clear search',
            'hosting.containers.search_none' => 'No app matches that search.',
            'hosting.containers.showing' => ':count apps',
            'hosting.containers.popular' => 'Popular choice',
            'hosting.containers.needs_ram' => 'Memory this app needs',
            'hosting.containers.needs_cpu' => 'CPU this app needs',
            'hosting.containers.needs_light' => 'Light',
            'hosting.containers.over_plan' => 'Needs more memory than your plan',
            'hosting.containers.plan_ceiling' => 'Every app runs inside your plan: up to :ram of memory and :cpu CPU, shared by all your apps.',
            'hosting.containers.unlimited' => 'no limit',
            'hosting.containers.cancel' => 'Cancel',

            'hosting.containers.group_websites' => 'Websites & CMS',
            'hosting.containers.group_websites_hint' => 'Publish a site, blog or shop',
            'hosting.containers.group_databases' => 'Databases & Search',
            'hosting.containers.group_databases_hint' => 'Store and query your data',
            'hosting.containers.group_ai' => 'AI & Automation',
            'hosting.containers.group_ai_hint' => 'Run models and automate work',
            'hosting.containers.group_devtools' => 'Developer Tools',
            'hosting.containers.group_devtools_hint' => 'Code, build and run your own projects',
            'hosting.containers.group_monitoring' => 'Monitoring & Analytics',
            'hosting.containers.group_monitoring_hint' => 'Watch traffic, uptime and usage',
            'hosting.containers.group_network' => 'Network & Security',
            'hosting.containers.group_network_hint' => 'Proxies, VPNs and protection',
            'hosting.containers.group_desktops' => 'Desktops & Browsers',
            'hosting.containers.group_desktops_hint' => 'A full desktop or browser in your account',
            'hosting.containers.group_files' => 'Files & Media',
            'hosting.containers.group_files_hint' => 'Store, share and stream files',
            'hosting.containers.group_team' => 'Team & Communication',
            'hosting.containers.group_team_hint' => 'Chat, wikis, notes and support',
            'hosting.containers.group_other' => 'Other Apps',
            'hosting.containers.group_other_hint' => 'Everything else in the catalogue',
        ];
    }

    public function up(): void
    {
        $now = now();
        foreach ($this->rows() as $key => $value) {
            if (! DB::table('dynamic_translations')->where('language', 'en')->where('group', 'client')->where('key', $key)->exists()) {
                DB::table('dynamic_translations')->insert(['language' => 'en', 'group' => 'client', 'key' => $key, 'value' => $value, 'is_auto_translated' => false, 'is_reviewed' => true, 'created_at' => $now, 'updated_at' => $now]);
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
        DB::table('dynamic_translations')->where('language', 'en')->where('group', 'client')->whereIn('key', array_keys($this->rows()))->delete();
    }
};
