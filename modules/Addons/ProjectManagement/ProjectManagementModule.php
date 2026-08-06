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
            ['label' => 'Projects', 'icon' => 'briefcase', 'url' => '/admin/projects', 'children' => [
                ['label' => 'All Projects', 'url' => '/admin/projects'],
                ['label' => 'Create Project', 'url' => '/admin/projects/create'],
            ]],
        ];
    }

    public function upgrade(string $fromVersion): array
    {
        return ['success' => true, 'message' => "Upgraded from " . e($fromVersion) . ""];
    }

    public function output(Request $request): string
    {
        $projects = DB::table('projects')
            ->leftJoin('clients', 'clients.id', '=', 'projects.client_id')
            ->where(function ($q) { $q->whereNull('clients.deleted_at')->orWhereNull('clients.id'); })
            ->leftJoin('admins', 'admins.id', '=', 'projects.admin_id')
            ->select('projects.*', DB::raw("CONCAT(clients.first_name, ' ', clients.last_name) as client_name"), DB::raw("CONCAT(admins.first_name, ' ', admins.last_name) as admin_name"))
            ->orderBy('projects.created_at', 'desc')
            ->limit(50)
            ->get();

        // Projects are created and edited on the core screens at /admin/projects.
        // This module used to render its own button and form pointing at
        // /admin/addons/project_management, a path no route has ever answered:
        // the button 404'd and the form the operator filled in was thrown away
        // on submit.
        $html = '<div style="margin-bottom:16px;display:flex;justify-content:space-between;align-items:center;">';
        $html .= '<h5 style="margin:0;">All Projects (' . $projects->count() . ')</h5>';
        $html .= '<a href="/admin/projects/create" class="btn btn-sm btn-primary">+ New Project</a></div>';

        $html .= '<table class="table" style="width:100%;font-size:13px;">';
        $html .= '<thead><tr><th>ID</th><th>Title</th><th>Client</th><th>Assigned</th><th>Status</th><th>Due</th></tr></thead><tbody>';

        foreach ($projects as $p) {
            // The statuses a project can hold are the ones the core screens
            // validate against; 'active' and 'on-hold' were never among them,
            // so every project but a completed one came out grey.
            $statusColor = match ($p->status) {
                'in_progress' => '#337ab7',
                'completed' => '#46a546',
                'pending' => '#f89406',
                'cancelled' => '#c9302c',
                default => '#999'
            };
            $statusLabel = ucfirst(str_replace('_', ' ', (string) $p->status));
            $html .= "<tr><td>" . e($p->id) . "</td><td><a href=\"/admin/projects/" . e($p->id) . "\"><b>" . e($p->title) . "</b></a></td><td>" . e(($p->client_name ?: 'N/A')) . "</td><td>" . e(($p->admin_name ?: 'Unassigned')) . "</td>";
            $html .= "<td><span style=\"color:" . e($statusColor) . ";font-weight:600;\">" . e($statusLabel) . "</span></td>";
            $html .= "<td>" . e(($p->due_date ?: '-')) . "</td></tr>";
        }

        $html .= '</tbody></table>';
        if ($projects->isEmpty()) {
            $html .= '<div style="text-align:center;padding:32px;color:var(--pn-muted);">No projects found. Create your first project!</div>';
        }
        return $html;
    }
}