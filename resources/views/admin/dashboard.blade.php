<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PNLCS Admin</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-slate-100 dark:bg-slate-900">
    <div class="flex items-center justify-center min-h-screen">
        <div class="text-center">
            <h1 class="text-4xl font-bold text-slate-800 dark:text-white">PNLCS Admin Dashboard</h1>
            <p class="text-slate-500 dark:text-slate-400 mt-2">Welcome, {{ Auth::guard('admin')->user()->full_name }}</p>
            <form method="POST" action="{{ route('admin.logout') }}" class="mt-4">
                @csrf
                <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-500">Logout</button>
            </form>
        </div>
    </div>
</body>
</html>
