@extends("admin.layouts.app")
@section("title", "Products/Services")
@section("content")
@if(session("success"))<div class="mb-4 p-3 bg-emerald-50 border border-emerald-200 rounded-lg text-sm text-emerald-700">{{ session("success") }}</div>@endif

<div class="flex items-center justify-between mb-6">
    <h1 class="text-2xl font-bold">Products/Services</h1>
    <div class="flex gap-2">
        <a href="{{ route("admin.products.groups.create") }}" class="px-4 py-2 bg-slate-100 dark:bg-slate-700 hover:bg-slate-200 text-sm font-medium rounded-lg transition-colors">New Group</a>
        <a href="{{ route("admin.products.create") }}" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white text-sm font-medium rounded-lg transition-colors">New Product</a>
    </div>
</div>

@forelse($groups as $group)
<div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 mb-6">
    <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700 flex items-center justify-between">
        <div>
            <h3 class="font-semibold text-lg">{{ $group->name }}</h3>
            @if($group->headline)<p class="text-sm text-slate-500">{{ $group->headline }}</p>@endif
        </div>
        <span class="text-xs text-slate-400">{{ $group->products->count() }} products</span>
    </div>
    @if($group->products->count() > 0)
    <table class="w-full text-sm">
        <thead class="bg-slate-50 dark:bg-slate-700/50">
            <tr>
                <th class="px-6 py-2 text-left font-medium text-slate-600">Product Name</th>
                <th class="px-6 py-2 text-left font-medium text-slate-600">Type</th>
                <th class="px-6 py-2 text-left font-medium text-slate-600">Payment</th>
                <th class="px-6 py-2 text-left font-medium text-slate-600">Status</th>
                <th class="px-6 py-2"></th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
            @foreach($group->products as $product)
            <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/30">
                <td class="px-6 py-3 font-medium">
                    <a href="{{ route("admin.products.edit", $product) }}" class="text-indigo-600 hover:text-indigo-500">{{ $product->name }}</a>
                    @if($product->is_featured)<span class="ml-2 text-xs bg-amber-100 text-amber-700 px-1.5 py-0.5 rounded">Featured</span>@endif
                </td>
                <td class="px-6 py-3 capitalize">{{ $product->type }}</td>
                <td class="px-6 py-3 capitalize">{{ $product->pay_type }}</td>
                <td class="px-6 py-3">
                    @if($product->hidden)<span class="text-xs bg-slate-100 text-slate-600 px-2 py-0.5 rounded-full">Hidden</span>
                    @elseif($product->retired)<span class="text-xs bg-red-100 text-red-600 px-2 py-0.5 rounded-full">Retired</span>
                    @else<span class="text-xs bg-emerald-100 text-emerald-600 px-2 py-0.5 rounded-full">Active</span>@endif
                </td>
                <td class="px-6 py-3 text-right">
                    <a href="{{ route("admin.products.edit", $product) }}" class="text-slate-400 hover:text-indigo-600"><x-heroicon-o-pencil-square class="w-4 h-4 inline" /></a>
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @else
    <div class="px-6 py-8 text-center text-slate-400">No products in this group. <a href="{{ route("admin.products.create") }}" class="text-indigo-600">Add one</a></div>
    @endif
</div>
@empty
<div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-12 text-center">
    <x-heroicon-o-cube class="w-12 h-12 text-slate-300 mx-auto mb-4" />
    <p class="text-slate-500 mb-4">No product groups yet.</p>
    <a href="{{ route("admin.products.groups.create") }}" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white text-sm font-medium rounded-lg">Create First Group</a>
</div>
@endforelse
@endsection
