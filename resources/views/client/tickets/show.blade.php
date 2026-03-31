@extends("client.layouts.app")
@section("title", "#" . $ticket->tid . " " . $ticket->title)
@section("content")
<div class="max-w-3xl">
    <h1 class="text-xl font-bold mb-1">#{{ $ticket->tid }} — {{ $ticket->title }}</h1>
    <p class="text-sm text-slate-500 mb-6">{{ $ticket->department->name ?? "" }} | {{ ucfirst($ticket->priority) }} | {{ ucfirst($ticket->status) }}</p>

    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6 mb-4">
        <div class="flex justify-between mb-2"><span class="font-medium">{{ $ticket->name ?? auth()->user()->full_name }}</span><span class="text-xs text-slate-400">{{ $ticket->created_at->format("d M Y H:i") }}</span></div>
        <div class="prose prose-sm max-w-none">{!! nl2br(e($ticket->message)) !!}</div>
    </div>

    @foreach($ticket->replies as $reply)
    <div class="rounded-xl shadow-sm border p-6 mb-4 {{ $reply->admin ? "bg-indigo-50 dark:bg-indigo-900/20 border-indigo-200" : "bg-white dark:bg-slate-800 border-slate-200" }}">
        <div class="flex justify-between mb-2"><span class="font-medium">{{ $reply->admin ? "Staff: ".$reply->admin : auth()->user()->full_name }}</span><span class="text-xs text-slate-400">{{ $reply->created_at->format("d M Y H:i") }}</span></div>
        <div class="prose prose-sm max-w-none">{!! nl2br(e($reply->message)) !!}</div>
    </div>
    @endforeach

    @if($ticket->status !== "closed")
    <form method="POST" action="{{ route("client.tickets.reply", $ticket) }}" class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6">
        @csrf
        <textarea name="message" rows="4" required placeholder="Type your reply..." class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm bg-white dark:bg-slate-700 mb-3"></textarea>
        <button type="submit" class="px-6 py-2 bg-indigo-600 hover:bg-indigo-500 text-white font-medium rounded-lg">Reply</button>
    </form>
    @endif
</div>
@endsection
