@extends("client.layouts.app")
@section("title", $domain->domain)
@section("content")

<div class="mb-6 flex items-start justify-between">
    <div>
        <a href="{{ route("client.domains.index") }}" class="text-sm text-indigo-600 hover:underline">&larr; Back to Domains</a>
        <h1 class="text-2xl font-bold mt-2">{{ $domain->domain }}</h1>
    </div>
    @php
        $statusColors = [
            "Active"    => "bg-emerald-100 text-emerald-700",
            "Expired"   => "bg-red-100 text-red-700",
            "Pending"   => "bg-yellow-100 text-yellow-700",
            "Locked"    => "bg-blue-100 text-blue-700",
            "Cancelled" => "bg-slate-100 text-slate-600",
        ];
        $statusClass = $statusColors[$domain->status] ?? "bg-slate-100 text-slate-600";
    @endphp
    <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium {{ $statusClass }}">{{ $domain->status }}</span>
</div>

@if(session("error"))
<div class="mb-4 p-3 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700">{{ session("error") }}</div>
@endif

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <div class="lg:col-span-2 space-y-6">
        <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6">
            <h2 class="font-semibold mb-5">Nameservers</h2>
            <form method="POST" action="{{ route("client.domains.nameservers", $domain) }}">
                @csrf
                @method("PUT")
                @php
                    $ns = is_string($domain->nameservers) ? json_decode($domain->nameservers, true) : ($domain->nameservers ?? []);
                    $ns = $ns ?? [];
                @endphp
                <div class="space-y-3">
                    @foreach(["ns1","ns2","ns3","ns4","ns5"] as $i => $nsKey)
                    <div class="flex items-center gap-3">
                        <label class="text-sm font-medium text-slate-500 w-10">{{ strtoupper($nsKey) }}</label>
                        <input type="text" name="{{ $nsKey }}"
                            value="{{ old($nsKey, $ns[$nsKey] ?? "") }}"
                            placeholder="{{ $i < 2 ? "Required" : "Optional" }}"
                            class="flex-1 border border-slate-300 dark:border-slate-600 rounded-lg px-3 py-2 text-sm bg-white dark:bg-slate-700 focus:outline-none focus:ring-2 focus:ring-indigo-400">
                    </div>
                    @endforeach
                </div>
                @error("ns1") <p class="mt-2 text-xs text-red-600">{{ $message }}</p> @enderror
                @error("ns2") <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                <div class="mt-4 flex justify-end">
                    <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white font-semibold px-5 py-2 rounded-lg transition-colors text-sm">
                        Save Nameservers
                    </button>
                </div>
            </form>
        </div>

        <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6">
            <h2 class="font-semibold mb-5">Domain Options</h2>
            <div class="space-y-4">
                <div class="flex items-center justify-between py-3 border-b border-slate-100 dark:border-slate-700">
                    <div>
                        <p class="text-sm font-medium">Domain Lock</p>
                        <p class="text-xs text-slate-400 mt-0.5">Prevents unauthorized transfers away from your registrar.</p>
                    </div>
                    <form method="POST" action="{{ route("client.domains.lock", $domain) }}">
                        @csrf
                        <button type="submit" class="text-sm font-medium px-4 py-1.5 rounded-lg border transition-colors
                            @if($domain->status === "Locked") bg-blue-50 border-blue-300 text-blue-700 hover:bg-blue-100 @else bg-slate-50 border-slate-300 text-slate-600 hover:bg-slate-100 @endif">
                            {{ $domain->status === "Locked" ? "Unlock" : "Lock" }}
                        </button>
                    </form>
                </div>

                <div class="flex items-center justify-between py-3 border-b border-slate-100 dark:border-slate-700">
                    <div>
                        <p class="text-sm font-medium">Auto-Renew</p>
                        <p class="text-xs text-slate-400 mt-0.5">Automatically renew this domain before it expires.</p>
                    </div>
                    @php $autoRenew = $domain->payment_method && $domain->payment_method !== "none"; @endphp
                    <form method="POST" action="{{ route("client.domains.autorenew", $domain) }}">
                        @csrf
                        <button type="submit" class="text-sm font-medium px-4 py-1.5 rounded-lg border transition-colors
                            @if($autoRenew) bg-emerald-50 border-emerald-300 text-emerald-700 hover:bg-emerald-100 @else bg-slate-50 border-slate-300 text-slate-600 hover:bg-slate-100 @endif">
                            {{ $autoRenew ? "Disable" : "Enable" }}
                        </button>
                    </form>
                </div>

                <div class="flex items-center justify-between py-3">
                    <div>
                        <p class="text-sm font-medium">ID Protection</p>
                        <p class="text-xs text-slate-400 mt-0.5">Masks your personal information in the WHOIS database.</p>
                    </div>
                    <span class="text-xs px-2 py-1 rounded {{ $domain->id_protection ? "bg-emerald-100 text-emerald-700" : "bg-slate-100 text-slate-500" }}">
                        {{ $domain->id_protection ? "Enabled" : "Disabled" }}
                    </span>
                </div>
            </div>
        </div>

        <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6">
            <h2 class="font-semibold mb-5">EPP / Auth Code</h2>
            <p class="text-sm text-slate-500 mb-4">Your EPP code (also known as the Auth Code or Transfer Code) is required to transfer your domain to another registrar.</p>
            <button onclick="revealEppCode()" id="epp-btn"
                class="bg-slate-800 hover:bg-slate-700 dark:bg-slate-600 dark:hover:bg-slate-500 text-white text-sm font-medium px-5 py-2 rounded-lg transition-colors">
                Reveal EPP Code
            </button>
            <div id="epp-result" class="hidden mt-4 p-3 bg-slate-50 dark:bg-slate-700 rounded-lg border border-slate-200 dark:border-slate-600">
                <code id="epp-code" class="text-sm font-mono text-slate-800 dark:text-slate-200"></code>
            </div>
            <script>
            function revealEppCode() {
                fetch("{{ route("client.domains.epp", $domain) }}", {
                    headers: { "Accept": "application/json", "X-Requested-With": "XMLHttpRequest" }
                })
                .then(r => r.json())
                .then(data => {
                    document.getElementById("epp-code").textContent = data.epp_code;
                    document.getElementById("epp-result").classList.remove("hidden");
                    document.getElementById("epp-btn").textContent = "Code Revealed";
                });
            }
            </script>
        </div>
    </div>

    <div class="space-y-4">
        <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-5">
            <h3 class="font-semibold mb-4 text-sm">Domain Information</h3>
            <dl class="space-y-3 text-sm">
                <div class="flex justify-between">
                    <dt class="text-slate-500">Registrar</dt>
                    <dd class="font-medium">{{ ucfirst($domain->registrar) }}</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-slate-500">Type</dt>
                    <dd class="font-medium">{{ $domain->type }}</dd>
                </div>
                <div class="border-t border-slate-100 dark:border-slate-700 pt-3">
                    <dt class="text-slate-500 mb-1">Registration Date</dt>
                    <dd class="font-medium">{{ $domain->registration_date ? $domain->registration_date->format("d M Y") : "—" }}</dd>
                </div>
                <div>
                    <dt class="text-slate-500 mb-1">Expiry Date</dt>
                    <dd class="font-medium @if($domain->expiry_date && $domain->expiry_date->isPast()) text-red-600 @elseif($domain->expiry_date && $domain->expiry_date->diffInDays() < 30) text-yellow-600 @endif">
                        {{ $domain->expiry_date ? $domain->expiry_date->format("d M Y") : "—" }}
                    </dd>
                </div>
                <div>
                    <dt class="text-slate-500 mb-1">Next Due Date</dt>
                    <dd class="font-medium">{{ $domain->next_due_date ? $domain->next_due_date->format("d M Y") : "—" }}</dd>
                </div>
                <div>
                    <dt class="text-slate-500 mb-1">Recurring Amount</dt>
                    <dd class="font-medium">${{ number_format($domain->recurring_amount, 2) }}</dd>
                </div>
            </dl>
        </div>

        @if($domain->dns_management)
        <div class="bg-indigo-50 dark:bg-indigo-900/20 border border-indigo-200 dark:border-indigo-700 rounded-xl p-5">
            <h3 class="font-semibold mb-2 text-sm text-indigo-700 dark:text-indigo-400">DNS Management</h3>
            <p class="text-xs text-indigo-600 dark:text-indigo-400 mb-3">DNS management is enabled for this domain.</p>
            <a href="#" class="text-xs font-medium text-indigo-600 hover:underline">Manage DNS Records &rarr;</a>
        </div>
        @endif
    </div>
</div>
@endsection
