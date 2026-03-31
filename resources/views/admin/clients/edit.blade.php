@extends("admin.layouts.app")
@section("title", "Edit " . $client->full_name)
@section("content")
<div class="max-w-3xl">
    <h1 class="text-2xl font-bold mb-6">Edit Client: {{ $client->full_name }}</h1>
    <form method="POST" action="{{ route("admin.clients.update", $client) }}" class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6 space-y-6">
        @csrf @method("PUT")
        @if($errors->any())<div class="bg-red-50 border border-red-200 rounded-lg p-3 text-sm text-red-600">@foreach($errors->all() as $e)<p>{{ $e }}</p>@endforeach</div>@endif
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div><label class="block text-sm font-medium mb-1">First Name *</label><input type="text" name="first_name" value="{{ old("first_name", $client->first_name) }}" required class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg text-sm bg-white dark:bg-slate-700 focus:ring-2 focus:ring-indigo-500"></div>
            <div><label class="block text-sm font-medium mb-1">Last Name *</label><input type="text" name="last_name" value="{{ old("last_name", $client->last_name) }}" required class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg text-sm bg-white dark:bg-slate-700 focus:ring-2 focus:ring-indigo-500"></div>
            <div><label class="block text-sm font-medium mb-1">Email *</label><input type="email" name="email" value="{{ old("email", $client->email) }}" required class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg text-sm bg-white dark:bg-slate-700 focus:ring-2 focus:ring-indigo-500"></div>
            <div><label class="block text-sm font-medium mb-1">Company</label><input type="text" name="company_name" value="{{ old("company_name", $client->company_name) }}" class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg text-sm bg-white dark:bg-slate-700 focus:ring-2 focus:ring-indigo-500"></div>
            <div class="md:col-span-2"><label class="block text-sm font-medium mb-1">Address</label><input type="text" name="address1" value="{{ old("address1", $client->address1) }}" class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg text-sm bg-white dark:bg-slate-700 focus:ring-2 focus:ring-indigo-500"></div>
            <div><label class="block text-sm font-medium mb-1">City</label><input type="text" name="city" value="{{ old("city", $client->city) }}" class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg text-sm bg-white dark:bg-slate-700 focus:ring-2 focus:ring-indigo-500"></div>
            <div><label class="block text-sm font-medium mb-1">State</label><input type="text" name="state" value="{{ old("state", $client->state) }}" class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg text-sm bg-white dark:bg-slate-700 focus:ring-2 focus:ring-indigo-500"></div>
            <div><label class="block text-sm font-medium mb-1">Postcode</label><input type="text" name="postcode" value="{{ old("postcode", $client->postcode) }}" class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg text-sm bg-white dark:bg-slate-700 focus:ring-2 focus:ring-indigo-500"></div>
            <div><label class="block text-sm font-medium mb-1">Country</label><input type="text" name="country" value="{{ old("country", $client->country) }}" maxlength="2" class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg text-sm bg-white dark:bg-slate-700 focus:ring-2 focus:ring-indigo-500"></div>
            <div><label class="block text-sm font-medium mb-1">Phone</label><input type="text" name="phone_number" value="{{ old("phone_number", $client->phone_number) }}" class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg text-sm bg-white dark:bg-slate-700 focus:ring-2 focus:ring-indigo-500"></div>
            <div><label class="block text-sm font-medium mb-1">Status</label><select name="status" class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg text-sm bg-white dark:bg-slate-700"><option value="active" {{ $client->status->value == "active" ? "selected" : "" }}>Active</option><option value="inactive" {{ $client->status->value == "inactive" ? "selected" : "" }}>Inactive</option><option value="closed" {{ $client->status->value == "closed" ? "selected" : "" }}>Closed</option></select></div>
        </div>
        <div class="flex gap-3">
            <button type="submit" class="px-6 py-2 bg-indigo-600 hover:bg-indigo-500 text-white font-medium rounded-lg transition-colors">Update Client</button>
            <a href="{{ route("admin.clients.show", $client) }}" class="px-6 py-2 bg-slate-100 dark:bg-slate-700 hover:bg-slate-200 dark:hover:bg-slate-600 font-medium rounded-lg transition-colors">Cancel</a>
        </div>
    </form>
</div>
@endsection
