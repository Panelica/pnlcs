@extends("client.layouts.app")
@section("title", $email->subject)
@section("content")

<a href="{{ route('client.emails.index') }}" class="pn-back">
    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
    {{ __('client.emails.back_to_emails') }}
</a>

<div class="pn-page-header">
    <div>
        <h1 class="pn-page-title">{{ $email->subject }}</h1>
        <p class="pn-page-subtitle">
            {{ $email->date?->format(datetime_fmt()) ?? $email->created_at?->format(datetime_fmt()) }}
            &nbsp;·&nbsp; {{ __('client.emails.sent_to') }}: {{ $email->to }}
        </p>
    </div>
</div>

<div class="pn-card">
    <div class="pn-card-body">
        <iframe sandbox="" srcdoc="{{ $email->message }}" style="width:100%;min-height:560px;border:0;background:#fff;border-radius:8px"></iframe>
    </div>
</div>

@endsection
