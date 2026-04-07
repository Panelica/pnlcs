<?php

namespace Modules\Addons\StaffBoard;

use App\Contracts\AddonModuleInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StaffBoardModule implements AddonModuleInterface
{
    public function getName(): string { return 'staffboard'; }
    public function getDisplayName(): string { return 'Staff Board'; }
    public function getDescription(): string { return 'Internal message board for admin staff. Post announcements, notes, and team updates visible only to administrators.'; }
    public function getVersion(): string { return '1.0.0'; }
    public function getAuthor(): string { return 'PNLCS'; }

    public function activate(): array
    {
        // Uses notes table from core schema
        return ['success' => true, 'message' => 'Staff Board activated'];
    }

    public function deactivate(): array
    {
        return ['success' => true, 'message' => 'Staff Board deactivated'];
    }

    public function config(): array
    {
        return [
            ['name' => 'posts_per_page', 'label' => 'Posts Per Page', 'type' => 'text', 'default' => '20'],
            ['name' => 'allow_markdown', 'label' => 'Allow Markdown in Posts', 'type' => 'checkbox', 'default' => '1'],
        ];
    }

    public function sidebar(): array
    {
        return [
            ['label' => 'Staff Board', 'icon' => 'clipboard', 'url' => '/admin/addons/staffboard'],
        ];
    }

    public function upgrade(string $fromVersion): array
    {
        return ['success' => true, 'message' => "Upgraded from {$fromVersion}"];
    }

    public function output(Request $request): string
    {
        // Use notes table as internal message board
        $notes = DB::table('notes')
            ->leftJoin('admins', 'admins.id', '=', 'notes.admin_id')
            ->select('notes.*', DB::raw("CONCAT(admins.first_name, ' ', admins.last_name) as author"))
            ->where('notes.sticky', true)
            ->orWhereNull('notes.client_id')
            ->orderByDesc('notes.sticky')
            ->orderByDesc('notes.created_at')
            ->limit(20)
            ->get();

        $html = '<div style="margin-bottom:16px;display:flex;justify-content:space-between;align-items:center;">';
        $html .= '<h5 style="margin:0;">Staff Board</h5>';
        $html .= '<span style="font-size:12px;color:var(--pn-muted);">Internal messages visible to admin staff only</span></div>';

        if ($notes->isEmpty()) {
            $html .= '<div style="text-align:center;padding:48px;color:var(--pn-muted);">';
            $html .= '<div style="font-size:32px;margin-bottom:8px;">📋</div>';
            $html .= '<p>No staff notes yet. Use the Notes feature to post internal messages.</p></div>';
            return $html;
        }

        foreach ($notes as $note) {
            $pinBadge = $note->sticky ? '<span style="background:#f89406;color:#fff;font-size:10px;padding:1px 6px;border-radius:3px;margin-left:6px;">PINNED</span>' : '';
            $html .= '<div class="card" style="margin-bottom:8px;">';
            $html .= '<div class="card-body" style="padding:12px 16px;">';
            $html .= '<div style="display:flex;justify-content:space-between;margin-bottom:6px;">';
            $html .= '<span style="font-weight:600;font-size:13px;">' . ($note->author ?: 'System') . $pinBadge . '</span>';
            $html .= '<span style="font-size:11px;color:var(--pn-muted);">' . \Carbon\Carbon::parse($note->created_at)->diffForHumans() . '</span>';
            $html .= '</div>';
            $html .= '<div style="font-size:13px;line-height:1.6;">' . nl2br(e($note->note ?? $note->message ?? '')) . '</div>';
            $html .= '</div></div>';
        }

        return $html;
    }
}