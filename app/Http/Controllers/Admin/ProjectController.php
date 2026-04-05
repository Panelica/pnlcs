<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Project;
use App\Models\ProjectMessage;
use App\Models\ProjectTask;
use Illuminate\Http\Request;

class ProjectController extends Controller
{
    public function index(Request $request)
    {
        $query = Project::with('client', 'tasks');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', '%' . $search . '%')
                  ->orWhereHas('client', fn ($c) =>
                      $c->where('first_name', 'like', '%' . $search . '%')
                        ->orWhere('last_name', 'like', '%' . $search . '%')
                        ->orWhere('company_name', 'like', '%' . $search . '%')
                  );
            });
        }

        $projects = $query->orderBy('created_at', 'desc')->paginate(25);

        return view('admin.projects.index', compact('projects'));
    }

    public function create()
    {
        $clients = Client::orderBy('first_name')->get();
        return view('admin.projects.create', compact('clients'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'client_id'   => 'required|exists:clients,id',
            'title'       => 'required|string|max:255',
            'description' => 'nullable|string',
            'status'      => 'required|in:pending,in_progress,completed,cancelled',
            'due_date'    => 'nullable|date',
            'start_date'  => 'nullable|date',
        ]);

        $project = Project::create($validated);

        return redirect()->route('admin.projects.show', $project)
            ->with('success', __('messages.success.project_created'));
    }

    public function show(Project $project)
    {
        $project->load('client', 'tasks', 'messages');
        return view('admin.projects.show', compact('project'));
    }

    public function edit(Project $project)
    {
        $clients = Client::orderBy('first_name')->get();
        return view('admin.projects.edit', compact('project', 'clients'));
    }

    public function update(Request $request, Project $project)
    {
        $validated = $request->validate([
            'title'       => 'required|string|max:255',
            'description' => 'nullable|string',
            'status'      => 'required|in:pending,in_progress,completed,cancelled',
            'due_date'    => 'nullable|date',
            'start_date'  => 'nullable|date',
        ]);

        $project->update($validated);

        return redirect()->route('admin.projects.show', $project)
            ->with('success', __('messages.success.project_updated_successfully'));
    }

    public function destroy(Project $project)
    {
        $project->messages()->delete();
        $project->tasks()->delete();
        $project->delete();

        return redirect()->route('admin.projects.index')
            ->with('success', __('messages.success.project_deleted'));
    }

    public function addTask(Request $request, Project $project)
    {
        $validated = $request->validate([
            'task'     => 'required|string|max:500',
            'notes'    => 'nullable|string',
            'due_date' => 'nullable|date',
        ]);

        $project->tasks()->create(array_merge($validated, [
            'completed'  => false,
            'sort_order' => $project->tasks()->max('sort_order') + 1,
        ]));

        return redirect()->route('admin.projects.show', $project)
            ->with('success', __('messages.success.task_added'));
    }

    public function updateTask(Request $request, Project $project, ProjectTask $task)
    {
        $validated = $request->validate([
            'task'      => 'nullable|string|max:500',
            'notes'     => 'nullable|string',
            'due_date'  => 'nullable|date',
            'completed' => 'nullable|boolean',
        ]);

        $task->update($validated);

        if ($request->wantsJson()) {
            return response()->json(['success' => true]);
        }

        return redirect()->route('admin.projects.show', $project)
            ->with('success', __('messages.success.task_updated'));
    }

    public function deleteTask(Project $project, ProjectTask $task)
    {
        $task->delete();

        return redirect()->route('admin.projects.show', $project)
            ->with('success', __('messages.success.task_deleted'));
    }

    public function addMessage(Request $request, Project $project)
    {
        $validated = $request->validate([
            'message' => 'required|string',
        ]);

        $project->messages()->create([
            'message' => $validated['message'],
            'admin'   => auth('admin')->user()?->full_name ?? 'Admin',
        ]);

        return redirect()->route('admin.projects.show', $project)
            ->with('success', __('messages.success.message_posted'));
    }
}
