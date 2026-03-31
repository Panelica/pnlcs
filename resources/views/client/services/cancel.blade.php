@extends('client.layouts.app')
@section('title', 'Request Cancellation')
@section('content')
<div class="max-w-2xl mx-auto">
    <div class="mb-6">
        <h1 class="text-2xl font-bold">Request Cancellation</h1>
        <p class="text-slate-500 text-sm mt-1">{{ $service->product->name ?? 'Service' }} &mdash; {{ $service->domain ?? '#' . $service->id }}</p>
    </div>

    <div class="bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-xl p-4 mb-6">
        <div class="flex gap-3">
            <svg class="w-5 h-5 text-amber-600 dark:text-amber-400 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
            <div>
                <p class="text-sm font-medium text-amber-800 dark:text-amber-300">Warning: This action may result in permanent data loss.</p>
                <p class="text-sm text-amber-700 dark:text-amber-400 mt-1">All files, databases, and emails associated with this service may be deleted after cancellation is processed.</p>
            </div>
        </div>
    </div>

    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6">
        <form method="POST" action="{{ route('client.services.cancel.submit', $service) }}">
            @csrf
            <div class="mb-6">
                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-3">Cancellation Type</label>
                <div class="space-y-3">
                    <label class="flex items-start gap-3 cursor-pointer">
                        <input type="radio" name="type" value="Immediate" class="mt-0.5 text-indigo-600" {{ old('type') === 'Immediate' ? 'checked' : '' }} required>
                        <div>
                            <span class="text-sm font-medium">Immediate</span>
                            <p class="text-xs text-slate-500 mt-0.5">The service will be cancelled immediately without a refund for any remaining period.</p>
                        </div>
                    </label>
                    <label class="flex items-start gap-3 cursor-pointer">
                        <input type="radio" name="type" value="End of Billing Period" class="mt-0.5 text-indigo-600" {{ old('type') === 'End of Billing Period' ? 'checked' : '' }}>
                        <div>
                            <span class="text-sm font-medium">End of Billing Period</span>
                            <p class="text-xs text-slate-500 mt-0.5">The service will remain active until {{ $service->next_due_date?->format('d M Y') ?? 'the next due date' }}, then be cancelled.</p>
                        </div>
                    </label>
                </div>
                @error('type') <p class="text-red-500 text-xs mt-2">{{ $message }}</p> @enderror
            </div>

            <div class="mb-6">
                <label for="reason" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">Reason for Cancellation <span class="text-red-500">*</span></label>
                <textarea id="reason" name="reason" rows="4" required maxlength="1000"
                    class="w-full rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-700 px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                    placeholder="Please tell us why you would like to cancel this service...">{{ old('reason') }}</textarea>
                @error('reason') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>

            <div class="flex items-center gap-3">
                <button type="submit" class="px-5 py-2 bg-red-600 text-white text-sm font-medium rounded-lg hover:bg-red-700 transition-colors">
                    Submit Cancellation Request
                </button>
                <a href="{{ route('client.services.show', $service) }}" class="px-5 py-2 bg-white dark:bg-slate-700 text-slate-700 dark:text-slate-300 text-sm font-medium rounded-lg border border-slate-300 dark:border-slate-600 hover:bg-slate-50 transition-colors">
                    Keep My Service
                </a>
            </div>
        </form>
    </div>
</div>
@endsection
