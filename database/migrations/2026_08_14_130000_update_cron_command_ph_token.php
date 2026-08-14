<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
return new class extends Migration
{
    // The command example must be realistic for the client's OWN domain (the
    // JS swaps {domain} for the selected one), not a fake example.com path the
    // account has no access to.
    private string $key = 'hosting.cron.command_ph';
    private string $new = '/usr/local/bin/php ~/{domain}/public_html/artisan schedule:run';
    private string $old = '/usr/local/bin/php ~/example.com/public_html/artisan schedule:run';
    public function up(): void
    {
        DB::table('dynamic_translations')->where('language','en')->where('group','client')->where('key',$this->key)->update(['value'=>$this->new,'updated_at'=>now()]);
        try { foreach (DB::table('dynamic_translations')->distinct()->pluck('language') as $lang) { Cache::forget("translations:{$lang}:client"); } } catch (\Throwable $e) {}
    }
    public function down(): void
    {
        DB::table('dynamic_translations')->where('language','en')->where('group','client')->where('key',$this->key)->update(['value'=>$this->old,'updated_at'=>now()]);
    }
};
