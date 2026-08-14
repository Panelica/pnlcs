<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
return new class extends Migration
{
    private function rows(): array
    {
        return [
            'hosting.dns.title' => 'DNS Zone',
            'hosting.dns.subtitle' => 'Manage the DNS records for your domains.',
            'hosting.dns.records' => 'records',
            'hosting.dns.no_domains' => 'No domains on this service yet.',
            'hosting.dns.create_title' => 'Add DNS Record',
            'hosting.dns.domain' => 'Domain',
            'hosting.dns.type' => 'Type',
            'hosting.dns.name' => 'Name',
            'hosting.dns.value' => 'Value',
            'hosting.dns.ttl' => 'TTL',
            'hosting.dns.priority' => 'Priority',
            'hosting.dns.create' => 'Add',
            'hosting.dns.empty' => 'No DNS records yet.',
            'hosting.dns.delete' => 'Delete',
            'hosting.dns.delete_confirm' => 'Delete this DNS record? Changes can take time to propagate.',
            'hosting.dns.protected' => 'Managed',
            'hosting.dns.protected_hint' => 'This record keeps your site and mail reachable and is managed by the hosting platform.',
            'hosting.dns.name_hint' => 'Use @ for the domain itself, or a subdomain name such as www. DNS changes may take up to a few hours to propagate.',
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
