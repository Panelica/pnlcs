<?php

namespace App\Widgets;

use App\Constants\Permissions;
use App\Contracts\WidgetModuleInterface;
use Illuminate\Support\Facades\DB;

class HealthWidget implements WidgetModuleInterface
{
    public function getTitle(): string { return 'System Health'; }
    public function getDescription(): string { return 'Server & application status'; }
    public function getColumns(): int { return 1; }
    public function getWeight(): int { return 80; }
    public function getPermission(): ?string { return Permissions::VIEW_SYSTEM; }
    public function getCacheTtl(): int { return 0; }

    public function getData(): array
    {
        $dbSize = 0;
        try {
            $row = DB::selectOne("SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 1) AS size FROM information_schema.tables WHERE table_schema = DATABASE()");
            $dbSize = $row->size ?? 0;
        } catch (\Throwable $e) {}

        $diskFree = 0;
        try {
            $diskFree = round(disk_free_space('/') / 1024 / 1024 / 1024, 1);
        } catch (\Throwable $e) {}

        $uptime = 'N/A';
        try {
            if (is_readable('/proc/uptime')) {
                $secs = (int) explode(' ', file_get_contents('/proc/uptime'))[0];
                $days = intdiv($secs, 86400);
                $hours = intdiv($secs % 86400, 3600);
                $uptime = $days > 0 ? "{$days}d {$hours}h" : "{$hours}h";
            }
        } catch (\Throwable $e) {}

        return [
            'php_version' => (string) PHP_VERSION,
            'laravel_version' => app()->version(),
            'db_size_mb' => $dbSize,
            'disk_free_gb' => $diskFree,
            'uptime_str' => $uptime,
        ];
    }

    public function render(array $data): string
    {
        $items = [
            ['PHP', $data['php_version'] ?? PHP_VERSION, '#337ab7'],
            ['Laravel', $data['laravel_version'] ?? '-', '#c43c35'],
            ['DB Size', ($data['db_size_mb'] ?? 0) . ' MB', '#46a546'],
            ['Disk Free', ($data['disk_free_gb'] ?? 0) . ' GB', '#f89406'],
            ['Uptime', $data['uptime_str'] ?? 'N/A', '#008b8b'],
        ];
        $html = '';
        foreach ($items as $item) {
            $html .= '<div style="display:flex;justify-content:space-between;padding:10px 16px;border-bottom:1px solid var(--pn-border);font-size:13px;"><span>' . $item[0] . '</span><span style="font-weight:600;color:' . $item[2] . ';">' . $item[1] . '</span></div>';
        }
        return $html;
    }
}
