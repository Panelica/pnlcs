@extends("admin.layouts.app")
@section("title", "Quotes")
@section("content")
<div class="flex items-center justify-between mb-6">
    <h1 class="text-2xl font-bold">Quotes</h1>
    <a href="{{ route('admin.quotes.create') }}" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-lg transition-colors">+ New Quote</a>
</div>
<div class="flex gap-2 mb-4 flex-wrap">
    @foreach([''=>'All','Draft'=>'Draft','Sent'=>'Sent','Accepted'=>'Accepted','Declined'=>'Declined'] as $val=>$label)
    <a href="{{ route('admin.quotes.index', ['status'=>$val,'search'=>request('search')]) }}"
       class="px-3 py-1.5 rounded-lg text-sm font-medium {{ request('status')==$val ? 'bg-indigo-600 text-white' : 'bg-slate-100 dark:bg-slate-700 hover:bg-slate-200' }} transition-colors">{{ $label }}</a>
    @endforeach
</div>
<form method="GET" class="mb-4">
    <input type="hidden" name="status" value="{{ request('status') }}">
    <input type="text" name="search" value="{{ request('search') }}" placeholder="Search by subject or client..." class="w-full max-w-sm px-3 py-2 text-sm border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-800 focus:ring-2 focus:ring-indigo-500 focus:outline-none">
</form>
@if(session('success'))
    <div class="mb-4 px-4 py-3 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-lg text-sm">{{ session('success') }}</div>
@endif
<div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden">
    <table class="w-full text-sm">
        <thead class="bg-slate-50 dark:bg-slate-700/50">
            <tr>
                <th class="px-4 py-3 text-left font-medium text-slate-600">#</th>
                <th class="px-4 py-3 text-left font-medium text-slate-600">Subject</th>
                <th class="px-4 py-3 text-left font-medium text-slate-600">Client</th>
                <th class="px-4 py-3 text-left font-medium text-slate-600">Date</th>
                <th class="px-4 py-3 text-left font-medium text-slate-600">Valid Until</th>
                <th class="px-4 py-3 text-right font-medium text-slate-600">Total</th>
                <th class="px-4 py-3 text-left font-medium text-slate-600">Status</th>
                <th class="px-4 py-3 text-left font-medium text-slate-600">Actions</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
            @forelse($quotes as $quote)
            @php $colors=['Draft'=>'slate','Sent'=>'blue','Accepted'=>'emerald','Declined'=>'red']; $c=$colors[$quote->status]??'slate'; @endphp
            <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/30">
                <td class="px-4 py-3 text-slate-500 font-mono">{{ $quote->id }}</td>
                <td class="px-4 py-3"><a href="{{ route('admin.quotes.show', $quote) }}" class="text-indigo-600 hover:text-indigo-500 font-medium">{{ $quote->subject }}</a></td>
                <td class="px-4 py-3">{{ $quote->client->full_name??'N/A' }}</td>
                <td class="px-4 py-3 text-slate-500">{{ \Carbon\Carbon::parse($quote->date)->format('d M Y') }}</td>
                <td class="px-4 py-3 text-slate-500">{{ \Carbon\Carbon::parse($quote->valid_until)->format('d M Y') }}</td>
                <td class="px-4 py-3 text-right font-medium">${{ number_format($quote->total,2) }}</td>
                <td class="px-4 py-3"><span class="px-2 py-0.5 text-xs font-medium rounded-full bg-{{ $c }}-100 text-{{ $c }}-700">{{ $quote->status }}</span></td>
                <td class="px-4 py-3">
                    <div class="flex items-center gap-2">
                        <a href="{{ route('admin.quotes.show', $quote) }}" class="text-indigo-600 hover:underline text-xs">View</a>
                        <a href="{{ route('admin.quotes.edit', $quote) }}" class="text-slate-500 hover:underline text-xs">Edit</a>
                        <form method="POST" action="{{ route('admin.quotes.destroy', $quote) }}" onsubmit="return confirm('Delete this quote?')">
                            @csrf @method('DELETE')
                            <button type="submit" class="text-red-500 hover:underline text-xs">Delete</button>
                        </form>
                    </div>
                </td>
            </tr>
            @empty
            <tr><td colspan="8" class="px-4 py-12 text-center text-slate-500">No quotes found.</td></tr>
            @endforelse
        </tbody>
    </table>
    <div class="px-4 py-3 border-t border-slate-200 dark:border-slate-700">{{ $quotes->withQueryString()->links() }}</div>
</div>
@endsection
