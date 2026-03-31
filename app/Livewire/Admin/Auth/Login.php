<?php

namespace App\Livewire\Admin\Auth;

use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class Login extends Component
{
    public string $username = "";

    public string $password = "";

    public bool $remember = false;

    public function login(): void
    {
        $this->validate([
            "username" => "required|string",
            "password" => "required|string",
        ]);

        $credentials = [
            "username" => $this->username,
            "password" => $this->password,
        ];

        if (Auth::guard("admin")->attempt($credentials, $this->remember)) {
            $admin = Auth::guard("admin")->user();

            if ($admin->is_disabled) {
                Auth::guard("admin")->logout();
                $this->addError("username", __("auth.disabled"));

                return;
            }

            $admin->update([
                "last_login" => now(),
                "last_login_ip" => request()->ip(),
            ]);

            session()->regenerate();

            $this->redirect(route("admin.dashboard"), navigate: true);
        } else {
            $this->addError("username", __("auth.failed"));
        }
    }

    public function render()
    {
        return view("livewire.admin.auth.login")
            ->layout("components.layouts.guest");
    }
}
