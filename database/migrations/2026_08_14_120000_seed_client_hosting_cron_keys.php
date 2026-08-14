<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
return new class extends Migration
{
    private function rows(): array
    {
        return [
            'hosting.cron.title' => 'Cron Jobs',
            'hosting.cron.subtitle' => 'Schedule commands to run automatically.',
            'hosting.cron.output' => 'Output',
            'hosting.cron.no_output' => '(no output)',
            'hosting.cron.no_domains' => 'No domains on this service yet.',
            'hosting.cron.plan_disabled' => 'Cron jobs are not included in your current plan.',
            'hosting.cron.create_title' => 'Create Cron Job',
            'hosting.cron.limit_reached' => 'You have reached your plan\'s cron job limit.',
            'hosting.cron.task_name' => 'Task name',
            'hosting.cron.task_name_ph' => 'Nightly backup',
            'hosting.cron.domain' => 'Domain',
            'hosting.cron.command' => 'Command',
            'hosting.cron.command_ph' => '/usr/local/bin/php ~/example.com/public_html/artisan schedule:run',
            'hosting.cron.command_hint' => 'Runs as your account user, isolated to your home directory.',
            'hosting.cron.schedule' => 'Schedule',
            'hosting.cron.basic' => 'Common',
            'hosting.cron.advanced' => 'Advanced',
            'hosting.cron.p.everyMinute' => 'Every minute',
            'hosting.cron.p.every5' => 'Every 5 minutes',
            'hosting.cron.p.every15' => 'Every 15 minutes',
            'hosting.cron.p.every30' => 'Every 30 minutes',
            'hosting.cron.p.hourly' => 'Hourly',
            'hosting.cron.p.daily' => 'Daily (midnight)',
            'hosting.cron.p.weekly' => 'Weekly (Sunday)',
            'hosting.cron.p.monthly' => 'Monthly (1st)',
            'hosting.cron.min' => 'Min',
            'hosting.cron.hr' => 'Hour',
            'hosting.cron.dom' => 'Day',
            'hosting.cron.mon' => 'Month',
            'hosting.cron.dow' => 'Weekday',
            'hosting.cron.email_on_error' => 'Email me on error',
            'hosting.cron.email_ph' => 'you@example.com (optional)',
            'hosting.cron.create' => 'Create',
            'hosting.cron.empty' => 'No cron jobs yet.',
            'hosting.cron.task' => 'Task',
            'hosting.cron.enabled' => 'Active',
            'hosting.cron.disabled' => 'Paused',
            'hosting.cron.run_now' => 'Run now',
            'hosting.cron.enable' => 'Enable',
            'hosting.cron.disable' => 'Pause',
            'hosting.cron.delete' => 'Delete',
            'hosting.cron.delete_confirm' => 'Delete this cron job?',
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
