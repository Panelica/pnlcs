<?php

namespace App\Widgets;

use App\Contracts\WidgetModuleInterface;
use Illuminate\Support\Facades\DB;

class ToDoWidget implements WidgetModuleInterface
{
    public function getTitle(): string { return 'To-Do List'; }
    public function getDescription(): string { return 'Admin tasks'; }
    public function getColumns(): int { return 1; }
    public function getWeight(): int { return 70; }
    public function getPermission(): ?string { return null; }
    public function getCacheTtl(): int { return 0; }

    public function getData(): array
    {
        return DB::table("todo_items")->select("id", "title", "status", "due_date")->where("status", "!=", "completed")->orderBy("due_date")->limit(8)->get()->toArray();
    }

    public function render(array $data): string
    {
        if (empty($data)) return '<div style="padding:24px;text-align:center;color:var(--pn-muted);font-size:13px;">All caught up!</div>';
        $html = "";
        foreach ($data as $t) { $t = (object)$t; $html .= '<div style="padding:8px 16px;border-bottom:1px solid var(--pn-border);font-size:13px;display:flex;align-items:center;gap:8px;"><span style="width:8px;height:8px;border-radius:50;background:'.($t->status==="in-progress"?"#f89406":"#337ab7").'"></span>'.$t->title.($t->due_date ? '<span style="margin-left:auto;font-size:11px;color:var(--pn-muted);">'.$t->due_date.'</span>' : '').'</div>'; }
        return $html;
    }
}