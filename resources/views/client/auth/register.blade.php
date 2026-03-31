<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - PNLCS</title>
    @vite(["resources/css/app.css"])
</head>
<body class="antialiased bg-slate-50 dark:bg-slate-900">
<div class="min-h-screen flex items-center justify-center py-12 px-4">
    <div class="w-full max-w-lg">
        <div class="text-center mb-8">
            <h1 class="text-3xl font-bold text-slate-800 dark:text-white">PNLCS</h1>
            <p class="text-slate-500 mt-2">Create your account</p>
        </div>
        <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-xl border border-slate-200 dark:border-slate-700 p-8">
            <form method="POST" action="{{ route("client.register.submit") }}" class="space-y-4">
                @csrf
                @if($errors->any())<div class="bg-red-50 border border-red-200 rounded-lg p-3 text-sm text-red-600">@foreach($errors->all() as $e)<p>{{ $e }}</p>@endforeach</div>@endif
                <div class="grid grid-cols-2 gap-4">
                    <div><label class="block text-sm font-medium mb-1">First Name *</label><input type="text" name="first_name" value="{{ old("first_name") }}" required class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 focus:ring-2 focus:ring-indigo-500"></div>
                    <div><label class="block text-sm font-medium mb-1">Last Name *</label><input type="text" name="last_name" value="{{ old("last_name") }}" required class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 focus:ring-2 focus:ring-indigo-500"></div>
                </div>
                <div><label class="block text-sm font-medium mb-1">Email *</label><input type="email" name="email" value="{{ old("email") }}" required class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 focus:ring-2 focus:ring-indigo-500"></div>
                <div><label class="block text-sm font-medium mb-1">Password *</label><input type="password" name="password" required class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 focus:ring-2 focus:ring-indigo-500"></div>
                <div><label class="block text-sm font-medium mb-1">Confirm Password *</label><input type="password" name="password_confirmation" required class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 focus:ring-2 focus:ring-indigo-500"></div>
                <div><label class="block text-sm font-medium mb-1">Company</label><input type="text" name="company_name" value="{{ old("company_name") }}" class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 focus:ring-2 focus:ring-indigo-500"></div>
                <div><label class="block text-sm font-medium mb-1">Phone</label><input type="text" name="phone_number" value="{{ old("phone_number") }}" class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 focus:ring-2 focus:ring-indigo-500"></div>
                <button type="submit" class="w-full py-3 bg-indigo-600 hover:bg-indigo-500 text-white font-semibold rounded-lg transition-colors">Register</button>
            </form>
        </div>
        <p class="text-center text-sm text-slate-500 mt-6">Already have an account? <a href="{{ route("client.login") }}" class="text-indigo-600 hover:text-indigo-500 font-medium">Sign In</a></p>
    </div>
</div>
</body>
</html>
