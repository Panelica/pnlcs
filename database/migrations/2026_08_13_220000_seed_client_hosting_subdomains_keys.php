<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
return new class extends Migration
{
    private function rows(): array
    {
        return [
            'hosting.subdomains.title' => 'Subdomains',
            'hosting.subdomains.subtitle' => 'Create subdomains under your domains.',
            'hosting.subdomains.create_title' => 'Create Subdomain',
            'hosting.subdomains.name' => 'Subdomain',
            'hosting.subdomains.domain' => 'Domain',
            'hosting.subdomains.document_root' => 'Document root',
            'hosting.subdomains.php' => 'PHP',
            'hosting.subdomains.ssl' => 'Enable SSL',
            'hosting.subdomains.create' => 'Create',
            'hosting.subdomains.empty' => 'No subdomains yet.',
            'hosting.subdomains.full_name' => 'Subdomain',
            'hosting.subdomains.delete' => 'Delete',
            'hosting.subdomains.delete_confirm' => 'Delete this subdomain? Its files and configuration will be removed.',
            'hosting.subdomains.limit_reached' => 'You have reached your plan\'s subdomain limit.',
            'hosting.subdomains.no_domains' => 'No domains on this service yet.',
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
