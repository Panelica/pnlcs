@extends('client.layouts.app')
@section('title', 'Upgrade / Downgrade Service')
@section('content')
<div class="max-w-3xl mx-auto">
    <div class="mb-6">
        <h1 class="text-2xl font-bold">Upgrade / Downgrade</h1>
        <p class="text-slate-500 text-sm mt-1">Currently on: <strong>{{ $service->product->name ?? 'N/A' }}</strong> &mdash; ${{ number_format($service->amount, 2) }}/{{ $service->billing_cycle }}</p>
    </div>

    @if($availableProducts->isEmpty())
        <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-12 text-center">
            <p class="text-slate-400">No upgrade or downgrade options are currently available for this service.</p>
            <a href="{{ route('client.services.show', $service) }}" class="inline-block mt-4 text-sm text-indigo-600 hover:text-indigo-500">&larr; Back to Service</a>
        </div>
    @else
        <form method="POST" action="{{ route('client.services.upgrade.process', $service) }}">
            @csrf
            <div class="space-y-4 mb-6">
                @foreach($availableProducts as $product)
                @php
                    $pricing = $product->pricing->first();
                    $price = $pricing ? $pricing->monthly ?? 0 : 0;
                    $diff = $price - $service->amount;
                @endphp
                <label class="flex items-start gap-4 bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-5 cursor-pointer hover:border-indigo-300 dark:hover:border-indigo-600 transition-colors has-[:checked]:border-indigo-500 has-[:checked]:ring-2 has-[:checked]:ring-indigo-200">
                    <input type="radio" name="new_product_id" value="{{ $product->id }}" class="mt-1 text-indigo-600" required>
                    <div class="flex-1">
                        <div class="flex items-center justify-between">
                            <span class="font-semibold">{{ $product->name }}</span>
                            <span class="text-sm font-medium text-slate-600 dark:text-slate-400">
                                ${{ number_format($price, 2) }}/mo
                                @if($diff > 0)
                                    <span class="text-amber-600 text-xs ml-1">(+${{ number_format(abs($diff), 2) }})</span>
                                @elseif($diff < 0)
                                    <span class="text-emerald-600 text-xs ml-1">(-${{ number_format(abs($diff), 2) }})</span>
                                @endif
                            </span>
                        </div>
                        @if($product->description)
                            <p class="text-sm text-slate-500 mt-1">{{ Str::limit($product->description, 120) }}</p>
                        @endif
                    </div>
                </label>
                @endforeach
            </div>

            <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-xl p-4 mb-6">
                <p class="text-sm text-blue-700 dark:text-blue-300">
                    <strong>Note:</strong> Your upgrade request will be reviewed by our team. Any pro-rated cost difference will be applied to your next invoice.
                </p>
            </div>

            @error('new_product_id') <p class="text-red-500 text-sm mb-4">{{ $message }}</p> @enderror

            <div class="flex items-center gap-3">
                <button type="submit" class="px-5 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition-colors">
                    Submit Upgrade Request
                </button>
                <a href="{{ route('client.services.show', $service) }}" class="px-5 py-2 bg-white dark:bg-slate-700 text-slate-700 dark:text-slate-300 text-sm font-medium rounded-lg border border-slate-300 dark:border-slate-600 hover:bg-slate-50 transition-colors">
                    Cancel
                </a>
            </div>
        </form>
    @endif
</div>
@endsection
