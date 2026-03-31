@extends("client.layouts.app")
@section("title", "Security Settings")
@section("content")

<div class="mb-6">
    <h1 class="text-2xl font-bold">Security Settings</h1>
</div>

<div class="grid grid-cols-1 lg:grid-cols-4 gap-8">
    <div class="lg:col-span-1">
        <nav class="space-y-1">
            <a href="{{ route("client.account.profile") }}" class="flex items-center gap-2 px-3 py-2 text-sm rounded-lg text-slate-600 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-700">Profile Details</a>
            <a href="{{ route("client.account.password") }}" class="flex items-center gap-2 px-3 py-2 text-sm rounded-lg text-slate-600 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-700">Change Password</a>
            <a href="{{ route("client.account.contacts") }}" class="flex items-center gap-2 px-3 py-2 text-sm rounded-lg text-slate-600 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-700">Contacts</a>
            <a href="{{ route("client.account.security") }}" class="flex items-center gap-2 px-3 py-2 text-sm rounded-lg bg-indigo-50 text-indigo-700 font-medium dark:bg-indigo-900/30 dark:text-indigo-400">Security</a>
        </nav>
    </div>
    <div class="lg:col-span-3 space-y-6">
        <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6">
            <h2 class="font-semibold mb-4">Two-Factor Authentication</h2>
            @if($user->second_factor_type)
            <div class="flex items-center gap-3 p-4 bg-emerald-50 border border-emerald-200 rounded-lg mb-4">
                <span class="text-emerald-600 text-sm font-medium">Two-factor authentication is enabled ({{ $user->second_factor_type }}).</span>
            </div>
            @else
            <div class="flex items-center gap-3 p-4 bg-yellow-50 border border-yellow-200 rounded-lg mb-4">
                <span class="text-yellow-700 text-sm">Two-factor authentication is not enabled on your account.</span>
            </div>
            @endif
            <p class="text-sm text-slate-500 mb-4">Two-factor authentication adds an extra layer of security. Once configured, you will need to provide a code from your authenticator app at login.</p>
            <button disabled class="bg-slate-200 dark:bg-slate-700 text-slate-400 dark:text-slate-500 font-medium px-5 py-2 rounded-lg text-sm cursor-not-allowed">
                Configure 2FA (Coming Soon)
            </button>
        </div>

        <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6">
            <h2 class="font-semibold mb-4">Active Sessions</h2>
            <p class="text-sm text-slate-500">Session management allows you to view and revoke active login sessions from other devices.</p>
            <p class="text-xs text-slate-400 mt-2">This feature is coming soon.</p>
        </div>
    </div>
</div>
@endsection
