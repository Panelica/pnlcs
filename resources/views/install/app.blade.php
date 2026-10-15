@extends('install.layout', ['step' => 'app'])
@section('title', __('install.app_title'))
@section('content')
    <h2 class="text-xl font-semibold text-slate-900 mb-1">{{ __('install.app_title') }}</h2>
    <p class="text-slate-600 text-sm mb-6">{{ __('install.app_intro') }}</p>

    <form method="POST" action="/install/app" class="space-y-4">
        @csrf
        <div>
            <label class="text-sm font-medium text-slate-700">{{ __('install.app_url') }}</label>
            <input type="url" name="app_url" value="{{ old('app_url', $app_url) }}" required class="mt-1 w-full px-3 py-2 border border-slate-300 rounded text-sm">
            <p class="text-xs text-slate-500 mt-1">{{ __('install.app_url_hint') }}</p>
        </div>
        <div>
            <label class="text-sm font-medium text-slate-700">{{ __('install.app_name') }}</label>
            <input type="text" name="app_name" value="{{ old('app_name', $app_name) }}" required class="mt-1 w-full px-3 py-2 border border-slate-300 rounded text-sm">
        </div>
        <div>
            <label class="text-sm font-medium text-slate-700">{{ __('install.app_locale') }}</label>
            <select name="app_locale" class="mt-1 w-full px-3 py-2 border border-slate-300 rounded text-sm">
                @foreach($locales as $code => $name)
                <option value="{{ $code }}" @selected(old('app_locale', $app_locale) === $code)>{{ $name }}</option>
                @endforeach
            </select>
        </div>

        <div class="flex gap-3 pt-2">
            <button type="submit" class="px-5 py-2 bg-blue-600 text-white text-sm font-semibold rounded hover:bg-blue-700">
                {{ __('install.app_finish') }}
            </button>
        </div>
    </form>
@endsection
