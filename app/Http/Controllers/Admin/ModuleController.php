<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Services\Module\ModuleSwitchboard;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Every installed module on one screen, each with its on/off switch.
 * The rules about what a switch means live in ModuleSwitchboard.
 */
class ModuleController extends Controller
{
    public function __construct(private ModuleSwitchboard $switchboard) {}

    public function index()
    {
        return view('admin.config.modules', [
            'groups' => $this->switchboard->rows(),
        ]);
    }

    public function toggle(Request $request, string $type, string $key)
    {
        $request->validate(['active' => ['required', Rule::in(['0', '1'])]]);
        abort_unless(in_array($type, ModuleSwitchboard::TYPES, true), 404);
        abort_unless($this->switchboard->exists($type, $key), 404);

        $on = $request->input('active') === '1';
        $result = $this->switchboard->setActive($type, $key, $on);

        if ($result['success']) {
            ActivityLog::log(
                'Module '.$type.'/'.$key.' switched '.($on ? 'on' : 'off'),
                auth('admin')->user()?->username
            );
        }

        return redirect()->route('admin.config.modules')
            ->with($result['success'] ? 'success' : 'error', $result['message']);
    }
}
