<?php

namespace App\Widgets;

use App\Constants\Permissions;
use App\Contracts\WidgetModuleInterface;
use Illuminate\Support\Facades\DB;

class HealthWidget implements WidgetModuleInterface
{
    public function getTitle(): string { return __('admin.dashboard.w_health'); }
    public function getDescription(): string { return __('admin.dashboard.w_health_desc'); }
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

        $diskFree = self::freeDiskGb(base_path());

        $uptime = null;
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

    /**
     * Free space, in GB, on the disk a path is on, or null when it cannot be
     * read.
     *
     * The path is the installation's own. The filesystem root is outside
     * open_basedir on most shared and panel-managed hosts, so asking about "/"
     * failed there and the box said 0 GB on a disk with room to spare.
     */
    public static function freeDiskGb(string $path): ?float
    {
        try {
            $bytes = @disk_free_space($path);
        } catch (\Throwable $e) {
            return null;
        }

        return $bytes === false ? null : round($bytes / 1024 / 1024 / 1024, 1);
    }

    public function render(array $data): string
    {
        $items = [
            [e(__('admin.dashboard.php')), $data['php_version'] ?? PHP_VERSION, '#337ab7'],
            [e(__('admin.dashboard.laravel')), $data['laravel_version'] ?? '-', '#c43c35'],
            [e(__('admin.dashboard.db_size')), ($data['db_size_mb'] ?? 0) . ' MB', '#46a546'],
            [e(__('admin.dashboard.disk_free')), isset($data['disk_free_gb']) ? $data['disk_free_gb'] . ' GB' : e(__('admin.dashboard.na')), '#f89406'],
            [e(__('admin.dashboard.uptime')), $data['uptime_str'] ?? e(__('admin.dashboard.na')), '#008b8b'],
        ];
        $html = '';
        foreach ($items as $item) {
            $html .= '<div style="display:flex;justify-content:space-between;padding:10px 16px;border-bottom:1px solid var(--pn-border);font-size:13px;"><span>' . $item[0] . '</span><span style="font-weight:600;color:' . $item[2] . ';">' . $item[1] . '</span></div>';
        }
        return $html;
    }
}
