@extends("client.layouts.app")
@section("title", "Change Password")
@section("content")

<div class="mb-6">
    <h1 class="text-2xl font-bold">Change Password</h1>
</div>

<div class="grid grid-cols-1 lg:grid-cols-4 gap-8">
    <div class="lg:col-span-1">
        <nav class="space-y-1">
            <a href="{{ route("client.account.profile") }}" class="flex items-center gap-2 px-3 py-2 text-sm rounded-lg text-slate-600 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-700">Profile Details</a>
            <a href="{{ route("client.account.password") }}" class="flex items-center gap-2 px-3 py-2 text-sm rounded-lg bg-indigo-50 text-indigo-700 font-medium dark:bg-indigo-900/30 dark:text-indigo-400">Change Password</a>
            <a href="{{ route("client.account.contacts") }}" class="flex items-center gap-2 px-3 py-2 text-sm rounded-lg text-slate-600 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-700">Contacts</a>
            <a href="{{ route("client.account.security") }}" class="flex items-center gap-2 px-3 py-2 text-sm rounded-lg text-slate-600 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-700">Security</a>
        </nav>
    </div>
    <div class="lg:col-span-3">
        <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6 max-w-lg">
            <h2 class="font-semibold mb-6">Change Password</h2>
            <form method="POST" action="{{ route("client.account.password.update") }}">
                @csrf
                @method("PUT")

                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium mb-1">Current Password <span class="text-red-500">*</span></label>
                        <input type="password" name="current_password" autocomplete="current-password"
                            class="w-full border border-slate-300 dark:border-slate-600 rounded-lg px-3 py-2 text-sm bg-white dark:bg-slate-700 focus:outline-none focus:ring-2 focus:ring-indigo-400">
                        @error("current_password") <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">New Password <span class="text-red-500">*</span></label>
                        <input type="password" name="password" autocomplete="new-password"
                            class="w-full border border-slate-300 dark:border-slate-600 rounded-lg px-3 py-2 text-sm bg-white dark:bg-slate-700 focus:outline-none focus:ring-2 focus:ring-indigo-400">
                        @error("password") <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        <p class="mt-1 text-xs text-slate-400">Minimum 8 characters with mixed case and numbers.</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">Confirm New Password <span class="text-red-500">*</span></label>
                        <input type="password" name="password_confirmation" autocomplete="new-password"
                            class="w-full border border-slate-300 dark:border-slate-600 rounded-lg px-3 py-2 text-sm bg-white dark:bg-slate-700 focus:outline-none focus:ring-2 focus:ring-indigo-400">
                    </div>
                </div>

                <div class="mt-6 pt-5 border-t border-slate-200 dark:border-slate-700 flex justify-end">
                    <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white font-semibold px-6 py-2 rounded-lg transition-colors text-sm">
                        Update Password
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
