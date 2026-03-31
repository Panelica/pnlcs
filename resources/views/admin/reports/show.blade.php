@extends("admin.layouts.app")
@section("title", $title)
@section("content")
<div class="flex items-center justify-between mb-6">
    <h1 class="text-2xl font-bold">{{ $title }}</h1>
    <a href="{{ route("admin.reports.index") }}" class="px-4 py-2 bg-slate-100 dark:bg-slate-700 hover:bg-slate-200 text-sm font-medium rounded-lg">Back to Reports</a>
</div>
<div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden">
    <table class="w-full text-sm">
        <thead class="bg-slate-50 dark:bg-slate-700/50">
            <tr>@foreach($columns as $col)<th class="px-4 py-3 text-left font-medium text-slate-600">{{ $col }}</th>@endforeach</tr>
        </thead>
        <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
            @forelse($data as $row)
            <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/30">
                @if(is_object($row) && isset($row->month))
                    <td class="px-4 py-3">{{ $row->month }}</td>
                    @foreach(array_slice($row->toArray(), 1) as $val)<td class="px-4 py-3">{{ is_numeric($val) ? "$" . number_format($val, 2) : $val }}</td>@endforeach
                @elseif(is_object($row) && isset($row->id))
                    <td class="px-4 py-3">{{ $row->id }}</td>
                    <td class="px-4 py-3">{{ $row->client->full_name ?? "N/A" }}</td>
                    <td class="px-4 py-3">{{ $row->product->name ?? "N/A" }}</td>
                    <td class="px-4 py-3">{{ $row->domain ?? "-" }}</td>
                    <td class="px-4 py-3">${{ number_format($row->amount ?? $row->revenue ?? 0, 2) }}</td>
                @else
                    @foreach((array) $row as $val)<td class="px-4 py-3">{{ $val }}</td>@endforeach
                @endif
            </tr>
            @empty
            <tr><td colspan="{{ count($columns) }}" class="px-4 py-12 text-center text-slate-500">No data available.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
