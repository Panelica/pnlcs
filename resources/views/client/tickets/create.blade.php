@extends("client.layouts.app")
@section("title", "Open Ticket")
@section("content")
<div class="max-w-2xl">
    <h1 class="text-2xl font-bold mb-6">Open Support Ticket</h1>
    <form method="POST" action="{{ route("client.tickets.store") }}" class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6 space-y-4">
        @csrf
        @if($errors->any())<div class="bg-red-50 border border-red-200 rounded-lg p-3 text-sm text-red-600">@foreach($errors->all() as $e)<p>{{ $e }}</p>@endforeach</div>@endif
        <div><label class="block text-sm font-medium mb-1">Department *</label><select name="department_id" required class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg text-sm bg-white dark:bg-slate-700">@foreach($departments as $d)<option value="{{ $d->id }}">{{ $d->name }}</option>@endforeach</select></div>
        <div><label class="block text-sm font-medium mb-1">Subject *</label><input type="text" name="subject" value="{{ old("subject") }}" required class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg text-sm bg-white dark:bg-slate-700"></div>
        <div><label class="block text-sm font-medium mb-1">Priority</label><select name="priority" class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg text-sm bg-white dark:bg-slate-700"><option value="low">Low</option><option value="medium" selected>Medium</option><option value="high">High</option></select></div>
        <div><label class="block text-sm font-medium mb-1">Message *</label><textarea name="message" rows="8" required class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg text-sm bg-white dark:bg-slate-700">{{ old("message") }}</textarea></div>
        <button type="submit" class="px-6 py-2 bg-indigo-600 hover:bg-indigo-500 text-white font-medium rounded-lg">Submit Ticket</button>
    </form>
</div>
@endsection
