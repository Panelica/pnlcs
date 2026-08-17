<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
return new class extends Migration
{
    private function rows(): array
    {
        return [
            'hosting.containers.title' => 'Apps',
            'hosting.containers.subtitle' => 'Install and run applications on your hosting.',
            'hosting.containers.plan_disabled' => 'Apps are not included in your current plan.',
            'hosting.containers.limit_reached' => 'You have reached your plan\'s app limit.',
            'hosting.containers.install_title' => 'Install an App',
            'hosting.containers.no_apps' => 'No apps are available on your plan yet.',
            'hosting.containers.name' => 'Name (optional)',
            'hosting.containers.name_ph' => 'my-blog',
            'hosting.containers.install' => 'Install',
            'hosting.containers.install_hint' => 'The app runs inside your account: its CPU and memory come out of your plan, and its files are stored in your home directory and count towards your disk quota.',
            'hosting.containers.running_title' => 'Your Apps',
            'hosting.containers.empty' => 'No apps installed yet.',
            'hosting.containers.app' => 'App',
            'hosting.containers.resources' => 'Resources',
            'hosting.containers.ports' => 'Ports',
            'hosting.containers.running' => 'Running',
            'hosting.containers.stopped' => 'Stopped',
            'hosting.containers.start' => 'Start',
            'hosting.containers.stop' => 'Stop',
            'hosting.containers.restart' => 'Restart',
            'hosting.containers.delete' => 'Remove',
            'hosting.containers.delete_confirm' => 'Remove this app? Its data will be deleted and cannot be recovered.',
            'hosting.containers.panel_hint' => 'For logs, a console or advanced settings, open your hosting panel.',
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
