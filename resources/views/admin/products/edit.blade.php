@extends("admin.layouts.app")
@section("title", "Edit " . $product->name)
@section("content")
<div class="max-w-4xl">
    @if(session("success"))<div class="mb-4 p-3 bg-emerald-50 border border-emerald-200 rounded-lg text-sm text-emerald-700">{{ session("success") }}</div>@endif
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold">Edit Product: {{ $product->name }}</h1>
        <form method="POST" action="{{ route("admin.products.destroy", $product) }}" onsubmit="return confirm(Delete this product?)">@csrf @method("DELETE")<button class="px-3 py-1.5 text-sm text-red-600 hover:bg-red-50 rounded-lg">Delete</button></form>
    </div>
    <form method="POST" action="{{ route("admin.products.update", $product) }}" class="space-y-6">
        @csrf @method("PUT")
        <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6">
            <h3 class="font-semibold mb-4">Details</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div><label class="block text-sm font-medium mb-1">Name *</label><input type="text" name="name" value="{{ $product->name }}" required class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg text-sm bg-white dark:bg-slate-700 focus:ring-2 focus:ring-indigo-500"></div>
                <div><label class="block text-sm font-medium mb-1">Group *</label><select name="group_id" class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg text-sm bg-white dark:bg-slate-700">@foreach($groups as $g)<option value="{{ $g->id }}" {{ $product->group_id == $g->id ? "selected" : "" }}>{{ $g->name }}</option>@endforeach</select></div>
                <div><label class="block text-sm font-medium mb-1">Type</label><select name="type" class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg text-sm bg-white dark:bg-slate-700"><option value="hosting" {{ $product->type == "hosting" ? "selected" : "" }}>Shared Hosting</option><option value="reseller" {{ $product->type == "reseller" ? "selected" : "" }}>Reseller</option><option value="vps" {{ $product->type == "vps" ? "selected" : "" }}>VPS/Dedicated</option><option value="other" {{ $product->type == "other" ? "selected" : "" }}>Other</option></select></div>
                <div><label class="block text-sm font-medium mb-1">Payment</label><select name="pay_type" class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg text-sm bg-white dark:bg-slate-700"><option value="recurring" {{ $product->pay_type == "recurring" ? "selected" : "" }}>Recurring</option><option value="onetime" {{ $product->pay_type == "onetime" ? "selected" : "" }}>One Time</option><option value="free" {{ $product->pay_type == "free" ? "selected" : "" }}>Free</option></select></div>
                <div><label class="block text-sm font-medium mb-1">Auto Setup</label><select name="auto_setup" class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg text-sm bg-white dark:bg-slate-700"><option value="order" {{ $product->auto_setup == "order" ? "selected" : "" }}>On Order</option><option value="payment" {{ $product->auto_setup == "payment" ? "selected" : "" }}>On Payment</option><option value="on" {{ $product->auto_setup == "on" ? "selected" : "" }}>Always</option><option value="off" {{ $product->auto_setup == "off" ? "selected" : "" }}>Never</option></select></div>
                <div><label class="block text-sm font-medium mb-1">Server Module</label><input type="text" name="server_type" value="{{ $product->server_type }}" placeholder="e.g. cpanel, custom" class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg text-sm bg-white dark:bg-slate-700"></div>
                <div class="md:col-span-2"><label class="block text-sm font-medium mb-1">Description</label><textarea name="description" rows="3" class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg text-sm bg-white dark:bg-slate-700">{{ $product->description }}</textarea></div>
                <div class="flex gap-6">
                    <label class="flex items-center gap-2"><input type="checkbox" name="hidden" value="1" {{ $product->hidden ? "checked" : "" }} class="rounded"><span class="text-sm">Hidden</span></label>
                    <label class="flex items-center gap-2"><input type="checkbox" name="retired" value="1" {{ $product->retired ? "checked" : "" }} class="rounded"><span class="text-sm">Retired</span></label>
                    <label class="flex items-center gap-2"><input type="checkbox" name="is_featured" value="1" {{ $product->is_featured ? "checked" : "" }} class="rounded"><span class="text-sm">Featured</span></label>
                </div>
            </div>
        </div>

        <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6">
            <h3 class="font-semibold mb-4">Pricing</h3>
            @foreach($currencies as $currency)
            @php $p = $pricing[$currency->id] ?? null; @endphp
            <div class="bg-slate-50 dark:bg-slate-700/30 rounded-lg p-4 mb-4">
                <p class="font-medium text-sm mb-3">{{ $currency->code }} ({{ $currency->prefix }})</p>
                <div class="grid grid-cols-3 md:grid-cols-6 gap-3">
                    @foreach(["monthly","quarterly","semiannually","annually","biennially","triennially"] as $cycle)
                    <div>
                        <label class="block text-xs text-slate-500 mb-1 capitalize">{{ $cycle }}</label>
                        <input type="number" step="0.01" name="pricing[{{ $currency->id }}][{{ $cycle }}]" value="{{ $p ? $p->$cycle : -1 }}" class="w-full px-2 py-1.5 border border-slate-300 dark:border-slate-600 rounded text-xs bg-white dark:bg-slate-700">
                    </div>
                    @endforeach
                </div>
                <div class="grid grid-cols-3 md:grid-cols-6 gap-3 mt-2">
                    @foreach(["monthly_setup","quarterly_setup","semiannually_setup","annually_setup"] as $setup)
                    <div>
                        <label class="block text-xs text-slate-500 mb-1">{{ str_replace("_", " ", ucfirst($setup)) }}</label>
                        <input type="number" step="0.01" name="pricing[{{ $currency->id }}][{{ $setup }}]" value="{{ $p ? $p->$setup : 0 }}" class="w-full px-2 py-1.5 border border-slate-300 dark:border-slate-600 rounded text-xs bg-white dark:bg-slate-700">
                    </div>
                    @endforeach
                </div>
            </div>
            @endforeach
        </div>

        <button type="submit" class="px-6 py-2 bg-indigo-600 hover:bg-indigo-500 text-white font-medium rounded-lg">Save Changes</button>
    </form>
</div>
@endsection
