<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - PNLCS</title>
    @vite(['resources/css/app.css'])
</head>
<body class="antialiased">
<div class="min-h-screen flex items-center justify-center bg-gradient-to-br from-slate-900 via-indigo-950 to-slate-900">
    <div class="w-full max-w-md">
        <div class="bg-white/10 backdrop-blur-xl rounded-2xl shadow-2xl border border-white/10 p-8">
            <div class="text-center mb-8">
                <h1 class="text-3xl font-bold text-white">PNLCS</h1>
                <p class="text-indigo-200 mt-2">Administration Area</p>
            </div>

            <form method="POST" action="{{ route('admin.login.submit') }}" class="space-y-6">
                @csrf

                @if ($errors->any())
                    <div class="bg-red-500/20 border border-red-500/50 rounded-lg p-3 text-red-200 text-sm">
                        {{ $errors->first() }}
                    </div>
                @endif

                <div>
                    <label for="username" class="block text-sm font-medium text-indigo-200 mb-2">Username</label>
                    <input type="text" name="username" id="username" value="{{ old('username') }}" autocomplete="username" required
                        class="w-full px-4 py-3 rounded-lg bg-white/5 border border-white/10 text-white placeholder-white/30 focus:border-indigo-400 focus:ring-2 focus:ring-indigo-400/20 focus:outline-none transition-all"
                        placeholder="Enter your username">
                </div>

                <div>
                    <label for="password" class="block text-sm font-medium text-indigo-200 mb-2">Password</label>
                    <input type="password" name="password" id="password" autocomplete="current-password" required
                        class="w-full px-4 py-3 rounded-lg bg-white/5 border border-white/10 text-white placeholder-white/30 focus:border-indigo-400 focus:ring-2 focus:ring-indigo-400/20 focus:outline-none transition-all"
                        placeholder="••••••••">
                </div>

                <div class="flex items-center">
                    <input type="checkbox" name="remember" id="remember"
                        class="w-4 h-4 rounded border-white/20 bg-white/5 text-indigo-500 focus:ring-indigo-400/20">
                    <label for="remember" class="ml-2 text-sm text-indigo-200">Remember me</label>
                </div>

                <button type="submit"
                    class="w-full py-3 px-4 bg-indigo-600 hover:bg-indigo-500 text-white font-semibold rounded-lg transition-all duration-200 shadow-lg hover:shadow-indigo-500/25 cursor-pointer">
                    Sign In
                </button>
            </form>
        </div>

        <p class="text-center text-indigo-300/50 text-sm mt-6">
            &copy; {{ date('Y') }} PNLCS. All rights reserved.
        </p>
    </div>
</div>
</body>
</html>
