<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/*
 * Plans were sold without saying what they contain: the store card showed a
 * name and a price, and the memory, CPU and app allowance - the things a
 * customer is actually choosing between - appeared nowhere.
 */
return new class extends Migration
{
    private function rows(): array
    {
        return [
            'store.res_memory' => ':value RAM',
            // 100% of a core is one core; a percentage reads like a mistake to
            // anyone who has not seen a cgroup.
            'store.res_cpu' => ':value vCPU',
            'store.res_disk' => ':value SSD storage',
            'store.res_bandwidth' => ':value bandwidth',
            'store.res_apps' => '{1} 1 app|[2,*] :count apps',
            'store.res_domains' => '{1} 1 website|[2,*] :count websites',
            'store.included_resources' => 'What this plan includes',
            'store.res_shared_note' => 'Apps you install run inside these limits and share them - the memory and CPU above are the total for everything in your account, not per app.',
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
