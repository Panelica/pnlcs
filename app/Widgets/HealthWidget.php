<?php

namespace App\Widgets;

use App\Contracts\WidgetModuleInterface;
use Illuminate\Support\Facades\DB;

class HealthWidget implements WidgetModuleInterface
{
    public function getTitle(): string { return 'System Health'; }
    public function getDescription(): string { return 'Server & application status'; }
    public function getColumns(): int { return 1; }
    public function getWeight(): int { return 80; }
    public function getPermission(): ?string { return null; }
    public function getCacheTtl(): int { return 300; }

    public function getData(): array
    {
        return [
            "php" => (string) PHP_VERSION,
            "laravel" => app()->version(),
            "db_size" => DB::selectOne("SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 1) AS size FROM information_schema.tables WHERE table_schema = DATABASE()")->size ?? 0,
            "disk_free" => round(disk_free_space("/") / 1024 / 1024 / 1024, 1),
            "uptime" => trim(shell_exec("uptime -p") ?? "unknown"),
        ];
    }

    public function render(array $data): string
    {
        $items = [
            ["PHP", $data["php"], "#337ab7"],
            ["Laravel", $data["laravel"], "#c43c35"],
            ["DB Size", $data["db_size"] . " MB", "#46a546"],
            ["Disk Free", $data["disk_free"] . " GB", "#f89406"],
            ["Uptime", $data["uptime"], "#008b8b"],
        ];
        $html = "";
        foreach ($items as [$label, $value, $color]) { $html .= '<div style="display:flex;justify-content:space-between;padding:10px 16px;border-bottom:1px solid var(--pn-border);font-size:13px;"><span>'.$label.'</span><span style="font-weight:600;color:'.$color.';">'.$value.'</span></div>'; }
        return $html;
    }
}