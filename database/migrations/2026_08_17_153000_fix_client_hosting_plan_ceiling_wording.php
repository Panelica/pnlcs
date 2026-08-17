<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/*
 * The first wording read "900% of one CPU core", which is wrong: 100% is one
 * core, so 900% is nine of them. Seeding only inserts missing keys, so the
 * value has to be corrected in place.
 */
return new class extends Migration
{
    private string $key = 'hosting.containers.plan_ceiling';

    private string $wrong = 'Each app runs within your plan: up to :ram of memory and :cpu of one CPU core.';

    private string $right = 'Every app runs inside your plan: up to :ram of memory and :cpu CPU, shared by all your apps.';

    public function up(): void
    {
        DB::table('dynamic_translations')->where('language', 'en')->where('group', 'client')
            ->where('key', $this->key)->where('value', $this->wrong)
            ->update(['value' => $this->right, 'updated_at' => now()]);
        $this->flush();
    }

    public function down(): void
    {
        DB::table('dynamic_translations')->where('language', 'en')->where('group', 'client')
            ->where('key', $this->key)->where('value', $this->right)
            ->update(['value' => $this->wrong, 'updated_at' => now()]);
        $this->flush();
    }

    private function flush(): void
    {
        try {
            foreach (DB::table('dynamic_translations')->distinct()->pluck('language') as $lang) {
                Cache::forget("translations:{$lang}:client");
            }
        } catch (\Throwable $e) {
        }
    }
};
