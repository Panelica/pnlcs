@extends("admin.layouts.app")
@section("title", "Add Client")
@section("content")
<div class="max-w-3xl">
    <h1 class="text-2xl font-bold mb-6">Add New Client</h1>
    <form method="POST" action="{{ route("admin.clients.store") }}" class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6 space-y-6">
        @csrf
        @if($errors->any())<div class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg p-3 text-sm text-red-600">@foreach($errors->all() as $e)<p>{{ $e }}</p>@endforeach</div>@endif

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div><label class="block text-sm font-medium mb-1">First Name *</label><input type="text" name="first_name" value="{{ old("first_name") }}" required class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg text-sm bg-white dark:bg-slate-700 focus:ring-2 focus:ring-indigo-500"></div>
            <div><label class="block text-sm font-medium mb-1">Last Name *</label><input type="text" name="last_name" value="{{ old("last_name") }}" required class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg text-sm bg-white dark:bg-slate-700 focus:ring-2 focus:ring-indigo-500"></div>
            <div><label class="block text-sm font-medium mb-1">Email *</label><input type="email" name="email" value="{{ old("email") }}" required class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg text-sm bg-white dark:bg-slate-700 focus:ring-2 focus:ring-indigo-500"></div>
            <div><label class="block text-sm font-medium mb-1">Company</label><input type="text" name="company_name" value="{{ old("company_name") }}" class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg text-sm bg-white dark:bg-slate-700 focus:ring-2 focus:ring-indigo-500"></div>
            <div class="md:col-span-2"><label class="block text-sm font-medium mb-1">Address</label><input type="text" name="address1" value="{{ old("address1") }}" class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg text-sm bg-white dark:bg-slate-700 focus:ring-2 focus:ring-indigo-500"></div>
            <div><label class="block text-sm font-medium mb-1">City</label><input type="text" name="city" value="{{ old("city") }}" class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg text-sm bg-white dark:bg-slate-700 focus:ring-2 focus:ring-indigo-500"></div>
            <div><label class="block text-sm font-medium mb-1">State</label><input type="text" name="state" value="{{ old("state") }}" class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg text-sm bg-white dark:bg-slate-700 focus:ring-2 focus:ring-indigo-500"></div>
            <div><label class="block text-sm font-medium mb-1">Postcode</label><input type="text" name="postcode" value="{{ old("postcode") }}" class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg text-sm bg-white dark:bg-slate-700 focus:ring-2 focus:ring-indigo-500"></div>
            <div><label class="block text-sm font-medium mb-1">Country</label><input type="text" name="country" value="{{ old("country", "US") }}" maxlength="2" class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg text-sm bg-white dark:bg-slate-700 focus:ring-2 focus:ring-indigo-500"></div>
            <div><label class="block text-sm font-medium mb-1">Phone</label><input type="text" name="phone_number" value="{{ old("phone_number") }}" class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg text-sm bg-white dark:bg-slate-700 focus:ring-2 focus:ring-indigo-500"></div>
            <div><label class="block text-sm font-medium mb-1">Status</label><select name="status" class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg text-sm bg-white dark:bg-slate-700"><option value="active">Active</option><option value="inactive">Inactive</option><option value="closed">Closed</option></select></div>
            <div><label class="block text-sm font-medium mb-1">Group</label><select name="group_id" class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg text-sm bg-white dark:bg-slate-700"><option value="">None</option>@foreach($groups as $g)<option value="{{ $g->id }}">{{ $g->name }}</option>@endforeach</select></div>
            <div><label class="block text-sm font-medium mb-1">Currency</label><select name="currency_id" class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg text-sm bg-white dark:bg-slate-700">@foreach($currencies as $c)<option value="{{ $c->id }}" {{ $c->is_default ? "selected" : "" }}>{{ $c->code }} ({{ $c->prefix }})</option>@endforeach</select></div>
        </div>
        <div class="flex gap-3">
            <button type="submit" class="px-6 py-2 bg-indigo-600 hover:bg-indigo-500 text-white font-medium rounded-lg transition-colors">Create Client</button>
            <a href="{{ route("admin.clients.index") }}" class="px-6 py-2 bg-slate-100 dark:bg-slate-700 hover:bg-slate-200 dark:hover:bg-slate-600 font-medium rounded-lg transition-colors">Cancel</a>
        </div>
    </form>
</div>
@endsection
