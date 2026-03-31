<div class="min-h-screen flex items-center justify-center bg-gradient-to-br from-slate-900 via-indigo-950 to-slate-900">
    <div class="w-full max-w-md">
        <div class="bg-white/10 backdrop-blur-xl rounded-2xl shadow-2xl border border-white/10 p-8">
            <div class="text-center mb-8">
                <h1 class="text-3xl font-bold text-white">PNLCS</h1>
                <p class="text-indigo-200 mt-2">Administration Area</p>
            </div>

            <form wire:submit="login" class="space-y-6">
                @if ($errors->has('username'))
                    <div class="bg-red-500/20 border border-red-500/50 rounded-lg p-3 text-red-200 text-sm">
                        {{ $errors->first('username') }}
                    </div>
                @endif

                <div>
                    <label for="username" class="block text-sm font-medium text-indigo-200 mb-2">Username</label>
                    <input wire:model="username" type="text" id="username" autocomplete="username"
                        class="w-full px-4 py-3 rounded-lg bg-white/5 border border-white/10 text-white placeholder-white/30 focus:border-indigo-400 focus:ring-2 focus:ring-indigo-400/20 transition-all"
                        placeholder="Enter your username">
                </div>

                <div>
                    <label for="password" class="block text-sm font-medium text-indigo-200 mb-2">Password</label>
                    <input wire:model="password" type="password" id="password" autocomplete="current-password"
                        class="w-full px-4 py-3 rounded-lg bg-white/5 border border-white/10 text-white placeholder-white/30 focus:border-indigo-400 focus:ring-2 focus:ring-indigo-400/20 transition-all"
                        placeholder="••••••••">
                </div>

                <div class="flex items-center">
                    <input wire:model="remember" type="checkbox" id="remember"
                        class="w-4 h-4 rounded border-white/20 bg-white/5 text-indigo-500 focus:ring-indigo-400/20">
                    <label for="remember" class="ml-2 text-sm text-indigo-200">Remember me</label>
                </div>

                <button type="submit"
                    class="w-full py-3 px-4 bg-indigo-600 hover:bg-indigo-500 text-white font-semibold rounded-lg transition-all duration-200 shadow-lg hover:shadow-indigo-500/25"
                    wire:loading.attr="disabled"
                    wire:loading.class="opacity-50 cursor-wait">
                    <span wire:loading.remove>Sign In</span>
                    <span wire:loading>Signing in...</span>
                </button>
            </form>
        </div>

        <p class="text-center text-indigo-300/50 text-sm mt-6">
            &copy; {{ date('Y') }} PNLCS. All rights reserved.
        </p>
    </div>
</div>
