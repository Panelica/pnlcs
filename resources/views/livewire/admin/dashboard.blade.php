<div>
    <!-- Stats Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        <!-- Total Clients -->
        <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-slate-500 dark:text-slate-400">Total Clients</p>
                    <p class="text-3xl font-bold mt-1">{{ $totalClients }}</p>
                </div>
                <div class="w-12 h-12 bg-indigo-100 dark:bg-indigo-900/30 rounded-xl flex items-center justify-center">
                    <x-heroicon-o-users class="w-6 h-6 text-indigo-600 dark:text-indigo-400" />
                </div>
            </div>
        </div>

        <!-- Active Clients -->
        <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-slate-500 dark:text-slate-400">Active Clients</p>
                    <p class="text-3xl font-bold mt-1">{{ $activeClients }}</p>
                </div>
                <div class="w-12 h-12 bg-emerald-100 dark:bg-emerald-900/30 rounded-xl flex items-center justify-center">
                    <x-heroicon-o-check-circle class="w-6 h-6 text-emerald-600 dark:text-emerald-400" />
                </div>
            </div>
        </div>

        <!-- Pending Orders -->
        <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-slate-500 dark:text-slate-400">Pending Orders</p>
                    <p class="text-3xl font-bold mt-1">0</p>
                </div>
                <div class="w-12 h-12 bg-amber-100 dark:bg-amber-900/30 rounded-xl flex items-center justify-center">
                    <x-heroicon-o-shopping-cart class="w-6 h-6 text-amber-600 dark:text-amber-400" />
                </div>
            </div>
        </div>

        <!-- Open Tickets -->
        <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-slate-500 dark:text-slate-400">Open Tickets</p>
                    <p class="text-3xl font-bold mt-1">0</p>
                </div>
                <div class="w-12 h-12 bg-rose-100 dark:bg-rose-900/30 rounded-xl flex items-center justify-center">
                    <x-heroicon-o-ticket class="w-6 h-6 text-rose-600 dark:text-rose-400" />
                </div>
            </div>
        </div>
    </div>

    <!-- Welcome Card -->
    <div class="bg-gradient-to-r from-indigo-600 to-violet-600 rounded-xl shadow-lg p-8 text-white mb-8">
        <h2 class="text-2xl font-bold">Welcome to PNLCS</h2>
        <p class="mt-2 text-indigo-100">Next-generation hosting billing platform. Your admin panel is ready.</p>
        <div class="mt-4 flex gap-3">
            <button class="px-4 py-2 bg-white/20 hover:bg-white/30 rounded-lg text-sm font-medium backdrop-blur-sm transition-colors">
                Add First Client
            </button>
            <button class="px-4 py-2 bg-white/10 hover:bg-white/20 rounded-lg text-sm font-medium backdrop-blur-sm transition-colors">
                Configure Settings
            </button>
        </div>
    </div>

    <!-- Activity & Info -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6">
            <h3 class="text-lg font-semibold mb-4">Recent Activity</h3>
            <p class="text-slate-500 dark:text-slate-400 text-sm">No activity yet. Start by adding clients and products.</p>
        </div>

        <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6">
            <h3 class="text-lg font-semibold mb-4">System Info</h3>
            <dl class="space-y-3 text-sm">
                <div class="flex justify-between">
                    <dt class="text-slate-500 dark:text-slate-400">PNLCS Version</dt>
                    <dd class="font-medium">1.0.0-dev</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-slate-500 dark:text-slate-400">Laravel</dt>
                    <dd class="font-medium">{{ app()->version() }}</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-slate-500 dark:text-slate-400">PHP</dt>
                    <dd class="font-medium">{{ phpversion() }}</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-slate-500 dark:text-slate-400">Admin Users</dt>
                    <dd class="font-medium">{{ $totalAdmins }}</dd>
                </div>
            </dl>
        </div>
    </div>
</div>
