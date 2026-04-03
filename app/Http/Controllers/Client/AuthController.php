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

    public function showForgotPassword()
    {
        return view("client.auth.forgot-password");
    }

    public function sendResetLink(Request $request)
    {
        $request->validate(["email" => "required|email"]);
        $user = User::where("email", $request->email)->first();
        if (!$user) {
            return back()->with("success", "If an account exists with that email, a password reset link has been sent.");
        }
        $token = \Illuminate\Support\Str::random(64);
        \Illuminate\Support\Facades\DB::table("password_reset_tokens")->updateOrInsert(
            ["email" => $request->email],
            ["token" => Hash::make($token), "created_at" => now()]
        );
        \Illuminate\Support\Facades\Log::info("Password reset for " . $request->email . ": " . $token);
        return back()->with("success", "If an account exists with that email, a password reset link has been sent.");
    }

    public function showResetForm(Request $request, string $token)
    {
        return view("client.auth.reset-password", ["token" => $token, "email" => $request->email]);
    }

    public function resetPassword(Request $request)
    {
        $request->validate(["token"=>"required","email"=>"required|email","password"=>"required|min:8|confirmed"]);
        $record = \Illuminate\Support\Facades\DB::table("password_reset_tokens")->where("email", $request->email)->first();
        if (!$record || !Hash::check($request->token, $record->token)) {
            return back()->withErrors(["email" => "Invalid or expired reset token."]);
        }
        if (now()->diffInMinutes($record->created_at) > 60) {
            return back()->withErrors(["email" => "Reset link expired. Request a new one."]);
        }
        $user = User::where("email", $request->email)->first();
        if (!$user) return back()->withErrors(["email" => "User not found."]);
        $user->password = Hash::make($request->password);
        $user->save();
        \Illuminate\Support\Facades\DB::table("password_reset_tokens")->where("email", $request->email)->delete();
        return redirect()->route("client.login")->with("success", "Password reset successfully. Please log in.");
    }
}
