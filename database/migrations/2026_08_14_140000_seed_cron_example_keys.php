<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
return new class extends Migration
{
    private function rows(): array
    {
        return [
            'hosting.cron.examples' => 'Examples:',
            'hosting.cron.ex.laravel' => 'Laravel scheduler',
            'hosting.cron.ex.php' => 'PHP script',
            'hosting.cron.ex.phpver' => 'PHP 8.3 script',
            'hosting.cron.ex.wp' => 'WordPress cron',
            'hosting.cron.ex.url' => 'Fetch a URL',
        ];
    }
    public function up(): void
    {
        $now = now();
        foreach ($this->rows() as $key => $value) {
            if (! DB::table('dynamic_translations')->where('language','en')->where('group','client')->where('key',$key)->exists()) {
                DB::table('dynamic_translations')->insert(['language'=>'en','group'=>'client','key'=>$key,'value'=>$value,'is_auto_translated'=>false,'is_reviewed'=>true,'created_at'=>$now,'updated_at'=>$now]);
            }
        }
        // Sharpen the hint: name the real interpreter paths in our isolated system.
        DB::table('dynamic_translations')->where('language','en')->where('group','client')->where('key','hosting.cron.command_hint')
            ->update(['value' => 'Runs as your account user, isolated to your home. Use /usr/local/bin/php (or php81–php85) and ~ for your home directory.', 'updated_at' => $now]);
        try { foreach (DB::table('dynamic_translations')->distinct()->pluck('language') as $lang) { Cache::forget("translations:{$lang}:client"); } } catch (\Throwable $e) {}
    }
    public function down(): void { DB::table('dynamic_translations')->where('language','en')->where('group','client')->whereIn('key',array_keys($this->rows()))->delete(); }
};
