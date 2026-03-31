@extends("admin.layouts.app")
@section("title", "Create Product")
@section("content")
<div class="max-w-3xl">
    <h1 class="text-2xl font-bold mb-6">Create Product</h1>
    <form method="POST" action="{{ route("admin.products.store") }}" class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6 space-y-6">
        @csrf
        @if($errors->any())<div class="bg-red-50 border border-red-200 rounded-lg p-3 text-sm text-red-600">@foreach($errors->all() as $e)<p>{{ $e }}</p>@endforeach</div>@endif
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div><label class="block text-sm font-medium mb-1">Product Name *</label><input type="text" name="name" value="{{ old("name") }}" required class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg text-sm bg-white dark:bg-slate-700 focus:ring-2 focus:ring-indigo-500"></div>
            <div><label class="block text-sm font-medium mb-1">Product Group *</label><select name="group_id" required class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg text-sm bg-white dark:bg-slate-700">@foreach($groups as $g)<option value="{{ $g->id }}">{{ $g->name }}</option>@endforeach</select></div>
            <div><label class="block text-sm font-medium mb-1">Product Type *</label><select name="type" class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg text-sm bg-white dark:bg-slate-700"><option value="hosting">Shared Hosting</option><option value="reseller">Reseller Hosting</option><option value="vps">VPS/Dedicated</option><option value="other">Other</option></select></div>
            <div><label class="block text-sm font-medium mb-1">Payment Type *</label><select name="pay_type" class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg text-sm bg-white dark:bg-slate-700"><option value="recurring">Recurring</option><option value="onetime">One Time</option><option value="free">Free</option></select></div>
            <div class="md:col-span-2"><label class="block text-sm font-medium mb-1">Description</label><textarea name="description" rows="3" class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg text-sm bg-white dark:bg-slate-700 focus:ring-2 focus:ring-indigo-500">{{ old("description") }}</textarea></div>
        </div>

        <h3 class="font-semibold border-t border-slate-200 dark:border-slate-700 pt-4">Pricing</h3>
        @foreach($currencies as $currency)
        <div class="bg-slate-50 dark:bg-slate-700/30 rounded-lg p-4">
            <p class="font-medium text-sm mb-3">{{ $currency->code }} ({{ $currency->prefix }})</p>
            <div class="grid grid-cols-3 md:grid-cols-6 gap-3">
                @foreach(["monthly","quarterly","semiannually","annually","biennially","triennially"] as $cycle)
                <div>
                    <label class="block text-xs text-slate-500 mb-1 capitalize">{{ $cycle }}</label>
                    <input type="number" step="0.01" name="pricing[{{ $currency->id }}][{{ $cycle }}]" value="-1" class="w-full px-2 py-1.5 border border-slate-300 dark:border-slate-600 rounded text-xs bg-white dark:bg-slate-700">
                </div>
                @endforeach
            </div>
            <p class="text-xs text-slate-400 mt-2">Set -1 to disable a billing cycle</p>
        </div>
        @endforeach

        <div class="flex gap-3">
            <button type="submit" class="px-6 py-2 bg-indigo-600 hover:bg-indigo-500 text-white font-medium rounded-lg">Create Product</button>
            <a href="{{ route("admin.products.index") }}" class="px-6 py-2 bg-slate-100 dark:bg-slate-700 hover:bg-slate-200 font-medium rounded-lg">Cancel</a>
        </div>
    </form>
</div>
@endsection
