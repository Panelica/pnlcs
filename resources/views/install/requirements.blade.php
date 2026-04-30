@extends('install.layout', ['step' => 'requirements'])
@section('title', 'Requirements')
@section('content')
    <h2 class="text-xl font-semibold text-slate-900 mb-1">System Requirements</h2>
    <p class="text-slate-600 text-sm mb-6">Verify your environment meets PNLCS requirements before continuing.</p>

    <div class="space-y-3 mb-6">
        <div class="flex items-center justify-between p-3 rounded border {{ $phpOk ? 'border-green-200 bg-green-50' : 'border-red-200 bg-red-50' }}">
            <span class="text-sm font-medium text-slate-700">PHP &ge; 8.4</span>
            <span class="text-sm {{ $phpOk ? 'text-green-700' : 'text-red-700' }}">{{ $phpOk ? '✓' : '✗' }} {{ $php }}</span>
        </div>

        <div class="border border-slate-200 rounded p-3">
            <div class="text-sm font-medium text-slate-700 mb-2">PHP Extensions</div>
            <div class="grid grid-cols-3 gap-2">
                @foreach($extensions as $ext => $ok)
                    <span class="text-xs px-2 py-1 rounded {{ $ok ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                        {{ $ok ? '✓' : '✗' }} {{ $ext }}
                    </span>
                @endforeach
            </div>
        </div>

        <div class="border border-slate-200 rounded p-3">
            <div class="text-sm font-medium text-slate-700 mb-2">Writable Directories</div>
            @foreach($writable as $path => $ok)
                <div class="text-xs flex justify-between py-0.5">
                    <code class="text-slate-600">{{ $path }}</code>
                    <span class="{{ $ok ? 'text-green-700' : 'text-red-700' }}">{{ $ok ? '✓ writable' : '✗ not writable' }}</span>
                </div>
            @endforeach
        </div>

        <div class="border border-slate-200 rounded p-3">
            <div class="text-sm font-medium text-slate-700 mb-2">Built Assets</div>
            @foreach($assets as $path => $ok)
                <div class="text-xs flex justify-between py-0.5">
                    <code class="text-slate-600">{{ $path }}</code>
                    <span class="{{ $ok ? 'text-green-700' : 'text-red-700' }}">{{ $ok ? '✓ present' : '✗ missing — run composer install / npm run build' }}</span>
                </div>
            @endforeach
        </div>
    </div>

    @if($allOk)
        <a href="/install/database" class="inline-flex items-center px-5 py-2.5 bg-blue-600 text-white text-sm font-semibold rounded hover:bg-blue-700">
            Continue →
        </a>
    @else
        <div class="p-3 rounded bg-amber-50 border border-amber-200 text-amber-800 text-sm">
            Please resolve the missing requirements above and reload this page.
        </div>
        <a href="/install/requirements" class="mt-4 inline-flex items-center px-5 py-2.5 bg-slate-200 text-slate-700 text-sm font-semibold rounded hover:bg-slate-300">
            Re-check
        </a>
    @endif
@endsection
