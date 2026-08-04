<?php

namespace Modules\Addons\ProjectManagement;

use App\Contracts\AddonModuleInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ProjectManagementModule implements AddonModuleInterface
{
    public function getName(): string { return 'project_management'; }
    public function getDisplayName(): string { return 'Project Management'; }
    public function getDescription(): string { return 'Track projects, tasks, and time for client services. Assign staff, set milestones, and invoice from project hours.'; }
    public function getVersion(): string { return '1.0.0'; }
    public function getAuthor(): string { return 'PNLCS'; }

    public function activate(): array
    {
        // Projects table is already part of the core schema
        return ['success' => true, 'message' => 'Project Management activated'];
    }

    public function deactivate(): array
    {
        return ['success' => true, 'message' => 'Project Management deactivated'];
    }

    public function config(): array
    {
        return [
            ['name' => 'default_task_limit', 'label' => 'Default Task Limit per Project', 'type' => 'text', 'default' => '50'],
            ['name' => 'allow_client_view', 'label' => 'Allow Clients to View Projects', 'type' => 'checkbox', 'default' => '1'],
            ['name' => 'auto_invoice', 'label' => 'Auto-create Invoice on Project Completion', 'type' => 'checkbox', 'default' => '0'],
        ];
    }

    public function sidebar(): array
    {
        return [
            ['label' => 'Projects', 'icon' => 'briefcase', 'url' => '/admin/addons/project_management', 'children' => [
                ['label' => 'All Projects', 'url' => '/admin/addons/project_management'],
                ['label' => 'Create Project', 'url' => '/admin/addons/project_management?action=create'],
            ]],
        ];
    }

    public function upgrade(string $fromVersion): array
    {
        return ['success' => true, 'message' => "Upgraded from {$fromVersion}"];
    }

    public function output(Request $request): string
    {
        $action = $request->input('action', 'list');

        if ($action === 'create') {
            return $this->renderCreateForm();
        }

        $projects = DB::table('projects')
            ->leftJoin('clients', 'clients.id', '=', 'projects.client_id')
            ->where(function ($q) { $q->whereNull('clients.deleted_at')->orWhereNull('clients.id'); })
            ->leftJoin('admins', 'admins.id', '=', 'projects.admin_id')
            ->select('projects.*', DB::raw("CONCAT(clients.first_name, ' ', clients.last_name) as client_name"), DB::raw("CONCAT(admins.first_name, ' ', admins.last_name) as admin_name"))
            ->orderBy('projects.created_at', 'desc')
            ->limit(50)
            ->get();

        $html = '<div style="margin-bottom:16px;display:flex;justify-content:space-between;align-items:center;">';
        $html .= '<h5 style="margin:0;">All Projects (' . $projects->count() . ')</h5>';
        $html .= '<a href="/admin/addons/project_management?action=create" class="btn btn-sm btn-primary">+ New Project</a></div>';

        $html .= '<table class="table" style="width:100%;font-size:13px;">';
        $html .= '<thead><tr><th>ID</th><th>Title</th><th>Client</th><th>Assigned</th><th>Status</th><th>Progress</th><th>Due</th></tr></thead><tbody>';

        foreach ($projects as $p) {
            $statusColor = match($p->status) {
                'active' => '#46a546', 'completed' => '#337ab7', 'on-hold' => '#f89406', default => '#999'
            };
            $html .= "<tr><td>{$p->id}</td><td><b>{$p->title}</b></td><td>" . ($p->client_name ?: 'N/A') . "</td><td>" . ($p->admin_name ?: 'Unassigned') . "</td>";
            $html .= "<td><span style=\"color:{$statusColor};font-weight:600;text-transform:capitalize;\">{$p->status}</span></td>";
            $html .= "<td><div style=\"background:#e5e7eb;border-radius:4px;height:8px;width:80px;\"><div style=\"background:#46a546;border-radius:4px;height:8px;width:" . ($p->progress ?? 0) . "%;\"></div></div></td>";
            $html .= "<td>" . ($p->due_date ?: '-') . "</td></tr>";
        }

        $html .= '</tbody></table>';
        if ($projects->isEmpty()) {
            $html .= '<div style="text-align:center;padding:32px;color:var(--pn-muted);">No projects found. Create your first project!</div>';
        }
        return $html;
    }

    private function renderCreateForm(): string
    {
        $clients = DB::table('clients')->whereNull('deleted_at')->select('id', 'first_name', 'last_name')->orderBy('first_name')->get();
        $admins = DB::table('admins')->select('id', 'first_name', 'last_name')->get();

        $html = '<h5>Create New Project</h5>';
        $html .= '<form method="POST" action="/admin/addons/project_management/store">';
        $html .= '<input type="hidden" name="_token" value="' . csrf_token() . '">';
        $html .= '<div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">';
        $html .= '<div><label>Title</label><input type="text" name="title" class="form-input" required></div>';
        $html .= '<div><label>Client</label><select name="client_id" class="form-input"><option value="">Select client...</option>';
        foreach ($clients as $c) { $html .= "<option value=\"{$c->id}\">{$c->first_name} {$c->last_name}</option>"; }
        $html .= '</select></div>';
        $html .= '<div><label>Assigned Admin</label><select name="admin_id" class="form-input"><option value="">Unassigned</option>';
        foreach ($admins as $a) { $html .= "<option value=\"{$a->id}\">{$a->first_name} {$a->last_name}</option>"; }
        $html .= '</select></div>';
        $html .= '<div><label>Due Date</label><input type="date" name="due_date" class="form-input"></div>';
        $html .= '<div style="grid-column:span 2;"><label>Description</label><textarea name="description" class="form-input" rows="3"></textarea></div>';
        $html .= '</div><div style="margin-top:16px;"><button type="submit" class="btn btn-primary">Create Project</button> <a href="/admin/addons/project_management" class="btn btn-outline">Cancel</a></div></form>';
        return $html;
    }
}