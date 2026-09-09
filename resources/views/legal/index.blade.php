@extends('legal.layout')

@php $tr = $legalLocale === 'tr'; @endphp

@section('legal-title', $tr ? 'Yasal Belgeler' : 'Legal')
@section('legal-description', $tr
    ? $company['name'].' kullanım koşulları, gizlilik politikası, iade politikası ve diğer yasal belgeler.'
    : $company['name'].' terms of service, privacy policy, refund policy, service level agreement and other legal documents.')

@section('legal-content')
    <div class="legal-head">
        <h1>{{ $tr ? 'Yasal Belgeler' : 'Legal Documents' }}</h1>
        <p>
            {{ $tr
                ? 'Hizmetlerimizi kullanırken geçerli olan sözleşme ve politikaların tamamı. Her belge yürürlük tarihi taşır; bir siparişe, siparişin verildiği tarihte yürürlükte olan metin uygulanır.'
                : 'Every agreement and policy that applies when you use our services. Each document carries an effective date; an order is governed by the text in force on the day it was placed.' }}
        </p>
    </div>

    <div class="legal-cards">
        @foreach($documents as $slug => $doc)
            <a href="{{ route('legal.show', $slug) }}" class="legal-card">
                <i class="{{ $doc['icon'] }}"></i>
                <b>
                    {{ $doc[$legalLocale][0] }}
                    @if(!empty($doc['tr_only']))
                        <span class="legal-tag">{{ $tr ? 'Türkiye' : 'Türkiye' }}</span>
                    @endif
                </b>
                <span>{{ $doc[$legalLocale][1] }}</span>
            </a>
        @endforeach
    </div>

    <div class="legal-note" style="margin-top:32px;">
        <p>
            <strong>{{ $tr ? 'Sorularınız için' : 'Questions' }}</strong> —
            {{ $tr ? 'Bu belgelerle ilgili her türlü soru için' : 'For any question about these documents, write to' }}
            <a href="mailto:{{ $company['email'] }}">{{ $company['email'] }}</a>{{ $tr ? ' adresine yazabilirsiniz.' : '.' }}
            {{ $tr
                ? 'Kötüye kullanım ve telif ihlali bildirimleri için'
                : 'Abuse reports and copyright complaints go to' }}
            <a href="mailto:{{ $company['abuse_email'] }}">{{ $company['abuse_email'] }}</a>.
        </p>
    </div>

    <p class="legal-meta">
        {{ $tr ? 'Son güncelleme' : 'Last updated' }}:
        {{ \Carbon\Carbon::parse($revised)->translatedFormat($tr ? 'd F Y' : 'F j, Y') }}
    </p>
@endsection
