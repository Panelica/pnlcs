<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    public function general()
    {
        $settings = Setting::where("group", "general")->pluck("value", "setting");
        return view("admin.settings.general", compact("settings"));
    }

    public function updateGeneral(Request $request)
    {
        foreach ($request->except("_token") as $key => $value) {
            Setting::set($key, $value, "general");
        }
        return back()->with("success", "Settings updated.");
    }
}
