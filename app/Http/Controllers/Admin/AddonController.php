<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AddonManager;
use Illuminate\Http\Request;

class AddonController extends Controller
{
    public function __construct(protected AddonManager $manager) {}

    public function index()
    {
        $addons = $this->manager->all();
        $statuses = [];
        foreach ($addons as $name => $addon) {
            $statuses[$name] = $this->manager->isActive($name);
        }
        return view('admin.config.addon-modules', compact('addons', 'statuses'));
    }

    public function show(string $name, Request $request)
    {
        $addon = $this->manager->find($name);
        if (!$addon) {
            return back()->with('error', __('messages.error.addon_not_found'));
        }

        if (!$this->manager->isActive($name)) {
            return back()->with('error', __('messages.error.addon_not_active'));
        }

        $output = $addon->output($request);
        return view('admin.config.addon-output', [
            'addon' => $addon,
            'output' => $output,
        ]);
    }

    public function toggle(string $name)
    {
        if ($this->manager->isActive($name)) {
            $result = $this->manager->deactivate($name);
        } else {
            $result = $this->manager->activate($name);
        }

        return back()->with($result['success'] ? 'success' : 'error', $result['message']);
    }
}
