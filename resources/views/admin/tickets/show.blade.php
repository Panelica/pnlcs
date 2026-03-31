@extends("admin.layouts.app")
@section("title", "#" . $ticket->tid . " " . $ticket->title)
@section("content")
@if(session("success"))<div class="mb-4 p-3 bg-emerald-50 border border-emerald-200 rounded-lg text-sm text-emerald-700">{{ session("success") }}</div>@endif

<div class="max-w-4xl">
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-xl font-bold">#{{ $ticket->tid }} — {{ $ticket->title }}</h1>
            <p class="text-sm text-slate-500">{{ $ticket->department->name ?? "" }} | {{ $ticket->email }} | Priority: {{ ucfirst($ticket->priority) }}</p>
        </div>
        <span class="px-3 py-1 text-sm font-medium rounded-full {{ $ticket->status == "open" ? "bg-emerald-100 text-emerald-700" : "bg-slate-100 text-slate-700" }}">{{ ucfirst($ticket->status) }}</span>
    </div>

    <!-- Original Message -->
    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6 mb-4">
        <div class="flex items-center justify-between mb-3">
            <span class="font-medium">{{ $ticket->name ?? $ticket->client->full_name ?? $ticket->email }}</span>
            <span class="text-xs text-slate-400">{{ $ticket->created_at->format("d M Y H:i") }}</span>
        </div>
        <div class="prose prose-sm max-w-none dark:prose-invert">{!! nl2br(e($ticket->message)) !!}</div>
    </div>

    <!-- Replies -->
    @foreach($ticket->replies as $reply)
    <div class="rounded-xl shadow-sm border p-6 mb-4 {{ $reply->admin ? "bg-indigo-50 dark:bg-indigo-900/20 border-indigo-200 dark:border-indigo-800" : "bg-white dark:bg-slate-800 border-slate-200 dark:border-slate-700" }}">
        <div class="flex items-center justify-between mb-3">
            <span class="font-medium">{{ $reply->admin ? "Staff: " . $reply->admin : ($ticket->client->full_name ?? $ticket->name ?? $ticket->email) }}</span>
            <span class="text-xs text-slate-400">{{ $reply->created_at->format("d M Y H:i") }}</span>
        </div>
        <div class="prose prose-sm max-w-none dark:prose-invert">{!! nl2br(e($reply->message)) !!}</div>
    </div>
    @endforeach

    <!-- Reply Form -->
    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6">
        <h3 class="font-semibold mb-4">Add Reply</h3>
        <form method="POST" action="{{ route("admin.tickets.reply", $ticket) }}">
            @csrf
            <textarea name="message" rows="6" required placeholder="Type your reply..." class="w-full px-4 py-3 border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 focus:ring-2 focus:ring-indigo-500 text-sm"></textarea>
            <div class="flex gap-3 mt-4">
                <button type="submit" class="px-6 py-2 bg-indigo-600 hover:bg-indigo-500 text-white font-medium rounded-lg">Send Reply</button>
            </div>
        </form>
    </div>

    <!-- Internal Notes -->
    @if($ticket->notes->count() > 0)
    <div class="mt-6">
        <h3 class="font-semibold mb-3">Internal Notes</h3>
        @foreach($ticket->notes as $note)
        <div class="bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-lg p-4 mb-2">
            <div class="flex justify-between mb-1"><span class="text-sm font-medium">{{ $note->admin }}</span><span class="text-xs text-slate-400">{{ $note->created_at->format("d M Y H:i") }}</span></div>
            <p class="text-sm">{!! nl2br(e($note->message)) !!}</p>
        </div>
        @endforeach
    </div>
    @endif
</div>
@endsection
