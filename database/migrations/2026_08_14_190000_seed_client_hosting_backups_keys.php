<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
return new class extends Migration
{
    private function rows(): array
    {
        return [
            'hosting.backups.title' => 'Backups',
            'hosting.backups.subtitle' => 'Restore points for your sites.',
            'hosting.backups.points' => 'restore points',
            'hosting.backups.plan_disabled' => 'Backups are not included in your current plan.',
            'hosting.backups.no_domains' => 'No domains on this service yet.',
            'hosting.backups.create_title' => 'Create Backup',
            'hosting.backups.scope' => 'What to back up',
            'hosting.backups.all_domains' => 'All my domains',
            'hosting.backups.name' => 'Label (optional)',
            'hosting.backups.name_ph' => 'Before theme update',
            'hosting.backups.create' => 'Create backup',
            'hosting.backups.create_hint' => 'A backup can take a few minutes depending on the size of your sites. It will appear in the list below once it finishes.',
            'hosting.backups.restore_points' => 'Restore points',
            'hosting.backups.empty' => 'No backups yet.',
            'hosting.backups.created' => 'Created',
            'hosting.backups.contents' => 'Contents',
            'hosting.backups.size' => 'Size',
            'hosting.backups.full' => 'Full',
            'hosting.backups.incremental' => 'Incremental',
            'hosting.backups.encrypted' => 'Encrypted',
            'hosting.backups.delete' => 'Delete',
            'hosting.backups.delete_confirm' => 'Delete this backup? This cannot be undone.',
            'hosting.backups.restore_hint' => 'To restore a backup, open your hosting panel — restoring overwrites current files and databases, so it is done there where you can review exactly what will be replaced.',
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
        try { foreach (DB::table('dynamic_translations')->distinct()->pluck('language') as $lang) { Cache::forget("translations:{$lang}:client"); } } catch (\Throwable $e) {}
    }
    public function down(): void { DB::table('dynamic_translations')->where('language','en')->where('group','client')->whereIn('key',array_keys($this->rows()))->delete(); }
};
