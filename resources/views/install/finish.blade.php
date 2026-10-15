@extends('install.layout', ['step' => 'finish'])
@section('title', __('install.done_page_title'))
@section('content')
    <div class="text-center py-6">
        <div class="w-16 h-16 mx-auto rounded-full bg-green-100 text-green-600 flex items-center justify-center text-3xl mb-4">✓</div>
        <h2 class="text-2xl font-bold text-slate-900 mb-2">{{ __('install.done_title') }}</h2>
        <p class="text-slate-600 mb-6">{{ __('install.done_intro') }}</p>

        <div class="bg-slate-50 border border-slate-200 rounded p-4 max-w-md mx-auto mb-6 text-left text-sm">
            <div class="text-slate-700 font-semibold mb-1">{{ __('install.next_steps') }}</div>
            <ul class="text-slate-600 space-y-1">
                <li>{{ trans_markup('install.next_login', ['username' => $username]) }}</li>
                <li>{{ __('install.next_smtp') }}</li>
                <li>{{ __('install.next_gateway') }}</li>
                <li>{{ __('install.next_modules') }}</li>
            </ul>
        </div>

        <div class="bg-amber-50 border border-amber-200 rounded p-4 max-w-md mx-auto mb-6 text-left text-sm">
            <div class="text-amber-900 font-semibold mb-1">{{ __('install.cron_title') }}</div>
            <p class="text-amber-900 mb-2">{{ __('install.cron_intro') }}</p>
            <code class="block bg-white border border-amber-200 rounded px-2 py-1.5 text-xs break-all select-all">* * * * * cd {{ $path }} && php artisan schedule:run >> /dev/null 2>&1</code>
            <p class="text-amber-800 mt-2 text-xs">{{ __('install.cron_php') }}</p>
            <p class="text-amber-800 mt-1 text-xs">{{ __('install.cron_note') }}</p>
        </div>

        <a href="/admin/login" class="inline-flex items-center px-6 py-3 bg-blue-600 text-white font-semibold rounded hover:bg-blue-700">
            {{ __('install.go_login') }}
        </a>
    </div>
@endsection
