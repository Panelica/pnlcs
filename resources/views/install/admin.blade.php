@extends('install.layout', ['step' => 'admin'])
@section('title', __('install.admin_page_title'))
@section('content')
    <h2 class="text-xl font-semibold text-slate-900 mb-1">{{ __('install.admin_title') }}</h2>
    <p class="text-slate-600 text-sm mb-6">{{ __('install.admin_intro') }}</p>

    <form method="POST" action="/install/admin" class="space-y-4">
        @csrf
        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="text-sm font-medium text-slate-700">{{ __('install.username') }}</label>
                <input type="text" name="username" value="{{ old('username', 'admin') }}" required class="mt-1 w-full px-3 py-2 border border-slate-300 rounded text-sm">
            </div>
            <div>
                <label class="text-sm font-medium text-slate-700">{{ __('install.email') }}</label>
                <input type="email" name="email" value="{{ old('email') }}" required class="mt-1 w-full px-3 py-2 border border-slate-300 rounded text-sm">
            </div>
        </div>
        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="text-sm font-medium text-slate-700">{{ __('install.first_name') }}</label>
                <input type="text" name="first_name" value="{{ old('first_name', 'System') }}" required class="mt-1 w-full px-3 py-2 border border-slate-300 rounded text-sm">
            </div>
            <div>
                <label class="text-sm font-medium text-slate-700">{{ __('install.last_name') }}</label>
                <input type="text" name="last_name" value="{{ old('last_name', 'Administrator') }}" class="mt-1 w-full px-3 py-2 border border-slate-300 rounded text-sm">
            </div>
        </div>
        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="text-sm font-medium text-slate-700">{{ __('install.password') }}</label>
                <input type="password" name="password" required minlength="6" class="mt-1 w-full px-3 py-2 border border-slate-300 rounded text-sm">
            </div>
            <div>
                <label class="text-sm font-medium text-slate-700">{{ __('install.password_confirm') }}</label>
                <input type="password" name="password_confirmation" required minlength="6" class="mt-1 w-full px-3 py-2 border border-slate-300 rounded text-sm">
            </div>
        </div>

        <div class="flex gap-3 pt-2">
            <button type="submit" class="px-5 py-2 bg-blue-600 text-white text-sm font-semibold rounded hover:bg-blue-700">
                {{ __('install.admin_create') }}
            </button>
        </div>
    </form>
@endsection
