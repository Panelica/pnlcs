@extends('install.layout', ['step' => 'app'])
@section('title', 'Application Settings')
@section('content')
    <h2 class="text-xl font-semibold text-slate-900 mb-1">Application Settings</h2>
    <p class="text-slate-600 text-sm mb-6">Final touches: branding and locale.</p>

    <form method="POST" action="/install/app" class="space-y-4">
        @csrf
        <div>
            <label class="text-sm font-medium text-slate-700">Application URL</label>
            <input type="url" name="app_url" value="{{ old('app_url', $app_url) }}" required class="mt-1 w-full px-3 py-2 border border-slate-300 rounded text-sm">
            <p class="text-xs text-slate-500 mt-1">Public URL where this PNLCS will be reachable (e.g. https://billing.example.com).</p>
        </div>
        <div>
            <label class="text-sm font-medium text-slate-700">Application Name</label>
            <input type="text" name="app_name" value="{{ old('app_name', $app_name) }}" required class="mt-1 w-full px-3 py-2 border border-slate-300 rounded text-sm">
        </div>
        <div>
            <label class="text-sm font-medium text-slate-700">Default Locale</label>
            <select name="app_locale" class="mt-1 w-full px-3 py-2 border border-slate-300 rounded text-sm">
                <option value="en" {{ $app_locale === 'en' ? 'selected' : '' }}>English</option>
                <option value="tr" {{ $app_locale === 'tr' ? 'selected' : '' }}>Türkçe</option>
                <option value="de">Deutsch</option>
                <option value="fr">Français</option>
                <option value="es">Español</option>
                <option value="it">Italiano</option>
                <option value="ru">Русский</option>
                <option value="pt-br">Português (BR)</option>
                <option value="nl">Nederlands</option>
                <option value="pl">Polski</option>
                <option value="cs">Čeština</option>
                <option value="zh" {{ $app_locale === 'zh' ? 'selected' : '' }}>简体中文</option>
            </select>
        </div>

        <div class="flex gap-3 pt-2">
            <button type="submit" class="px-5 py-2 bg-blue-600 text-white text-sm font-semibold rounded hover:bg-blue-700">
                Finish Installation →
            </button>
        </div>
    </form>
@endsection
