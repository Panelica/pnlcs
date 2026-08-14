<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
return new class extends Migration
{
    private string $key = 'hosting.subdomains.ssl_hint';
    private string $val = 'If the parent domain has SSL, the subdomain inherits it even when this is off.';
    public function up(): void
    {
        if (! DB::table('dynamic_translations')->where('language','en')->where('group','client')->where('key',$this->key)->exists()) {
            DB::table('dynamic_translations')->insert(['language'=>'en','group'=>'client','key'=>$this->key,'value'=>$this->val,'is_auto_translated'=>false,'is_reviewed'=>true,'created_at'=>now(),'updated_at'=>now()]);
        }
        try { foreach (DB::table('dynamic_translations')->distinct()->pluck('language') as $lang) { Cache::forget("translations:{$lang}:client"); } } catch (\Throwable $e) {}
    }
    public function down(): void { DB::table('dynamic_translations')->where('language','en')->where('group','client')->where('key',$this->key)->delete(); }
};
