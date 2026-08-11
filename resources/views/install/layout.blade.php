<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Install') — PNLCS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body { font-family: ui-sans-serif, system-ui, -apple-system, sans-serif; }
    </style>
</head>
<body class="min-h-screen bg-gradient-to-br from-slate-50 to-blue-50 py-10 px-4">
    <div class="max-w-3xl mx-auto">
        <div class="text-center mb-8">
            <h1 class="text-3xl font-bold text-slate-900">PNLCS Install Wizard</h1>
            <p class="text-slate-600 mt-2">Open-source hosting billing platform</p>
        </div>

        <!-- Steps -->
        <ol class="flex items-center justify-between mb-8 max-w-xl mx-auto">
            @php
                $steps = [
                    ['key' => 'requirements', 'label' => 'Requirements'],
                    ['key' => 'database',     'label' => 'Database'],
                    ['key' => 'admin',        'label' => 'Admin'],
                    ['key' => 'app',          'label' => 'App'],
                    ['key' => 'finish',       'label' => 'Finish'],
                ];
                $current = $step ?? 'requirements';
                $currentIdx = collect($steps)->search(fn($s) => $s['key'] === $current);
            @endphp
            @foreach($steps as $i => $s)
                @php $done = $i < $currentIdx; $active = $i === $currentIdx; @endphp
                <li class="flex items-center {{ $i < count($steps) - 1 ? 'flex-1' : '' }}">
                    <div class="flex items-center">
                        <span class="w-8 h-8 rounded-full flex items-center justify-center text-sm font-semibold
                            {{ $done ? 'bg-green-500 text-white' : ($active ? 'bg-blue-600 text-white' : 'bg-slate-200 text-slate-500') }}">
                            {{ $done ? '✓' : ($i + 1) }}
                        </span>
                        <span class="ml-2 text-sm {{ $active ? 'font-semibold text-slate-900' : 'text-slate-500' }}">{{ $s['label'] }}</span>
                    </div>
                    @if($i < count($steps) - 1)
                        <div class="flex-1 h-0.5 mx-3 {{ $done ? 'bg-green-500' : 'bg-slate-200' }}"></div>
                    @endif
                </li>
            @endforeach
        </ol>

        <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-8">
            @if(session('error'))
                <div class="mb-4 p-3 rounded bg-red-50 border border-red-200 text-red-700 text-sm">
                    {{ session('error') }}
                </div>
            @endif
            @if($errors->any())
                <div class="mb-4 p-3 rounded bg-red-50 border border-red-200 text-red-700 text-sm">
                    <ul class="list-disc pl-5">
                        @foreach($errors->all() as $err)<li>{{ $err }}</li>@endforeach
                    </ul>
                </div>
            @endif

            @yield('content')
        </div>

        <p class="text-center text-xs text-slate-400 mt-6">
            <a href="https://github.com/Panelica/pnlcs" class="hover:text-slate-600">github.com/Panelica/pnlcs</a>
            ·
            <a href="https://hub.docker.com/r/panelica/pnlcs-runtime" class="hover:text-slate-600">Docker Hub</a>
        </p>
    </div>
</body>
</html>
