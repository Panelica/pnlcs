<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
return new class extends Migration
{
    private array $rows = [
        'hosting.backups.download_hint' => 'Download from your hosting panel',
    ];
    public function up(): void
    {
        foreach ($this->rows as $key => $value) {
            if (! DB::table('dynamic_translations')->where('language','en')->where('group','client')->where('key',$key)->exists()) {
                DB::table('dynamic_translations')->insert(['language'=>'en','group'=>'client','key'=>$key,'value'=>$value,'is_auto_translated'=>false,'is_reviewed'=>true,'created_at'=>now(),'updated_at'=>now()]);
            }
        }
        // The note under the list now covers downloading as well as restoring.
        DB::table('dynamic_translations')->where('language','en')->where('group','client')->where('key','hosting.backups.restore_hint')
            ->update(['value' => 'To download or restore a backup, open your hosting panel — archives can be several gigabytes, and restoring overwrites current files and databases, so both are done there.', 'updated_at' => now()]);
        try { foreach (DB::table('dynamic_translations')->distinct()->pluck('language') as $lang) { Cache::forget("translations:{$lang}:client"); } } catch (\Throwable $e) {}
    }
    public function down(): void { DB::table('dynamic_translations')->where('language','en')->where('group','client')->whereIn('key',array_keys($this->rows))->delete(); }
};
