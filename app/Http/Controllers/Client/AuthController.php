<?php
namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function showLogin()
    {
        if (Auth::check()) return redirect()->route("client.home");
        return view("client.auth.login");
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            "email" => "required|email",
            "password" => "required|string",
        ]);
        if (Auth::attempt($credentials, $request->boolean("remember"))) {
            $request->session()->regenerate();
            return redirect()->intended(route("client.home"));
        }
        return back()->withErrors(["email" => __("auth.failed")])->onlyInput("email");
    }

    public function showRegister()
    {
        return view("client.auth.register");
    }

    public function register(Request $request)
    {
        $validated = $request->validate([
            "first_name" => "required|string|max:255",
            "last_name" => "required|string|max:255",
            "email" => "required|email|unique:users,email",
            "password" => "required|string|min:8|confirmed",
            "company_name" => "nullable|string|max:255",
            "address1" => "nullable|string|max:255",
            "city" => "nullable|string|max:255",
            "country" => "nullable|string|max:2",
            "phone_number" => "nullable|string|max:30",
        ]);

        $user = User::create([
            "first_name" => $validated["first_name"],
            "last_name" => $validated["last_name"],
            "email" => $validated["email"],
            "password" => Hash::make($validated["password"]),
        ]);

        $client = Client::create([
            "first_name" => $validated["first_name"],
            "last_name" => $validated["last_name"],
            "email" => $validated["email"],
            "company_name" => $validated["company_name"] ?? null,
            "address1" => $validated["address1"] ?? null,
            "city" => $validated["city"] ?? null,
            "country" => $validated["country"] ?? "US",
            "phone_number" => $validated["phone_number"] ?? null,
        ]);
        $client->users()->attach($user->id, ["owner" => true]);

        Auth::login($user);
        return redirect()->route("client.home");
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route("client.login");
    }
}
