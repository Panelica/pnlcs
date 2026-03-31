@extends("admin.layouts.app")
@section("title", $client->full_name)
@section("content")
@if(session("success"))<div class="mb-4 p-3 bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-800 rounded-lg text-sm text-emerald-700 dark:text-emerald-300">{{ session("success") }}</div>@endif

<div class="flex items-center justify-between mb-6">
    <div>
        <h1 class="text-2xl font-bold">{{ $client->full_name }}</h1>
        <p class="text-slate-500">{{ $client->email }} | {{ $client->company_name }}</p>
    </div>
    <div class="flex gap-2">
        <a href="{{ route("admin.clients.edit", $client) }}" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white text-sm font-medium rounded-lg transition-colors">Edit</a>
        <span class="inline-flex items-center px-3 py-1 text-sm font-medium rounded-full {{ $client->status->value == "active" ? "bg-emerald-100 text-emerald-700" : "bg-slate-100 text-slate-700" }}">{{ ucfirst($client->status->value) }}</span>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <!-- Profile -->
    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6">
        <h3 class="font-semibold mb-4">Profile</h3>
        <dl class="space-y-2 text-sm">
            <div class="flex justify-between"><dt class="text-slate-500">Name</dt><dd>{{ $client->full_name }}</dd></div>
            <div class="flex justify-between"><dt class="text-slate-500">Company</dt><dd>{{ $client->company_name ?? "-" }}</dd></div>
            <div class="flex justify-between"><dt class="text-slate-500">Email</dt><dd>{{ $client->email }}</dd></div>
            <div class="flex justify-between"><dt class="text-slate-500">Phone</dt><dd>{{ $client->phone_number ?? "-" }}</dd></div>
            <div class="flex justify-between"><dt class="text-slate-500">Address</dt><dd>{{ $client->address1 ?? "-" }}, {{ $client->city }} {{ $client->postcode }}</dd></div>
            <div class="flex justify-between"><dt class="text-slate-500">Country</dt><dd>{{ $client->country }}</dd></div>
            <div class="flex justify-between"><dt class="text-slate-500">Created</dt><dd>{{ $client->created_at->format("d M Y") }}</dd></div>
        </dl>
    </div>

    <!-- Billing -->
    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6">
        <h3 class="font-semibold mb-4">Billing</h3>
        <dl class="space-y-2 text-sm">
            <div class="flex justify-between"><dt class="text-slate-500">Credit Balance</dt><dd class="font-bold text-lg">${{ number_format($client->credit, 2) }}</dd></div>
            <div class="flex justify-between"><dt class="text-slate-500">Invoices</dt><dd>0</dd></div>
            <div class="flex justify-between"><dt class="text-slate-500">Unpaid</dt><dd>0</dd></div>
            <div class="flex justify-between"><dt class="text-slate-500">Tax Exempt</dt><dd>{{ $client->tax_exempt ? "Yes" : "No" }}</dd></div>
        </dl>
    </div>

    <!-- Services -->
    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6">
        <h3 class="font-semibold mb-4">Services</h3>
        <dl class="space-y-2 text-sm">
            <div class="flex justify-between"><dt class="text-slate-500">Products/Services</dt><dd>0</dd></div>
            <div class="flex justify-between"><dt class="text-slate-500">Domains</dt><dd>0</dd></div>
            <div class="flex justify-between"><dt class="text-slate-500">Tickets</dt><dd>0</dd></div>
            <div class="flex justify-between"><dt class="text-slate-500">Contacts</dt><dd>{{ $client->contacts->count() }}</dd></div>
        </dl>
    </div>
</div>
@endsection
