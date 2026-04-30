@extends('install.layout', ['step' => 'database'])
@section('title', 'Database')
@section('content')
    <h2 class="text-xl font-semibold text-slate-900 mb-1">Database Configuration</h2>
    <p class="text-slate-600 text-sm mb-6">Provide MySQL/MariaDB credentials. The database must already exist; PNLCS will create the tables.</p>

    <form method="POST" action="/install/database" class="space-y-4">
        @csrf
        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="text-sm font-medium text-slate-700">Host</label>
                <input type="text" name="host" value="{{ old('host', $host) }}" required class="mt-1 w-full px-3 py-2 border border-slate-300 rounded text-sm">
            </div>
            <div>
                <label class="text-sm font-medium text-slate-700">Port</label>
                <input type="number" name="port" value="{{ old('port', $port) }}" required class="mt-1 w-full px-3 py-2 border border-slate-300 rounded text-sm">
            </div>
        </div>
        <div>
            <label class="text-sm font-medium text-slate-700">Database Name</label>
            <input type="text" name="database" value="{{ old('database', $database) }}" required class="mt-1 w-full px-3 py-2 border border-slate-300 rounded text-sm">
        </div>
        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="text-sm font-medium text-slate-700">Username</label>
                <input type="text" name="username" value="{{ old('username', $username) }}" required class="mt-1 w-full px-3 py-2 border border-slate-300 rounded text-sm">
            </div>
            <div>
                <label class="text-sm font-medium text-slate-700">Password</label>
                <input type="password" name="password" value="{{ old('password') }}" class="mt-1 w-full px-3 py-2 border border-slate-300 rounded text-sm">
            </div>
        </div>

        <div id="test-result" class="hidden p-3 rounded text-sm"></div>

        <div class="flex gap-3 pt-2">
            <button type="button" id="test-btn" class="px-4 py-2 bg-slate-200 text-slate-700 text-sm font-semibold rounded hover:bg-slate-300">
                Test Connection
            </button>
            <button type="submit" class="px-5 py-2 bg-blue-600 text-white text-sm font-semibold rounded hover:bg-blue-700">
                Save & Run Migrations →
            </button>
        </div>
    </form>

    <script>
        document.getElementById('test-btn').addEventListener('click', async () => {
            const form = document.querySelector('form');
            const fd = new FormData(form);
            const res = document.getElementById('test-result');
            res.className = 'p-3 rounded text-sm bg-slate-100 text-slate-700';
            res.textContent = 'Testing connection...';
            try {
                const resp = await fetch('/install/database/test', {
                    method: 'POST',
                    headers: {'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, 'Accept': 'application/json'},
                    body: fd,
                });
                const data = await resp.json();
                res.className = 'p-3 rounded text-sm ' + (resp.ok ? 'bg-green-50 text-green-800 border border-green-200' : 'bg-red-50 text-red-800 border border-red-200');
                res.textContent = (resp.ok ? '✓ ' : '✗ ') + data.message;
            } catch (e) {
                res.className = 'p-3 rounded text-sm bg-red-50 text-red-800 border border-red-200';
                res.textContent = '✗ ' + e.message;
            }
        });
    </script>
@endsection
