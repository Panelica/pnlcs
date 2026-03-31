<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Client Login - PNLCS</title>
    @vite(["resources/css/app.css"])
</head>
<body class="antialiased bg-slate-50 dark:bg-slate-900">
<div class="min-h-screen flex items-center justify-center py-12 px-4">
    <div class="w-full max-w-md">
        <div class="text-center mb-8">
            <h1 class="text-3xl font-bold text-slate-800 dark:text-white">PNLCS</h1>
            <p class="text-slate-500 mt-2">Sign in to your account</p>
        </div>
        <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-xl border border-slate-200 dark:border-slate-700 p-8">
            <form method="POST" action="{{ route("client.login.submit") }}" class="space-y-5">
                @csrf
                @if($errors->any())<div class="bg-red-50 border border-red-200 rounded-lg p-3 text-sm text-red-600">{{ $errors->first() }}</div>@endif
                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Email</label>
                    <input type="email" name="email" value="{{ old("email") }}" required autofocus class="w-full px-4 py-3 border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 text-slate-800 dark:text-white focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Password</label>
                    <input type="password" name="password" required class="w-full px-4 py-3 border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 text-slate-800 dark:text-white focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                </div>
                <div class="flex items-center justify-between">
                    <label class="flex items-center"><input type="checkbox" name="remember" class="mr-2 rounded"><span class="text-sm text-slate-600 dark:text-slate-400">Remember me</span></label>
                    <a href="#" class="text-sm text-indigo-600 hover:text-indigo-500">Forgot password?</a>
                </div>
                <button type="submit" class="w-full py-3 bg-indigo-600 hover:bg-indigo-500 text-white font-semibold rounded-lg transition-colors">Sign In</button>
            </form>
        </div>
        <p class="text-center text-sm text-slate-500 mt-6">No account? <a href="{{ route("client.register") }}" class="text-indigo-600 hover:text-indigo-500 font-medium">Register</a></p>
    </div>
</div>
</body>
</html>
