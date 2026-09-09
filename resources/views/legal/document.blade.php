@extends('legal.layout')

@php $tr = $legalLocale === 'tr'; @endphp

@section('legal-title', $meta[$legalLocale][0])
@section('legal-description', $meta[$legalLocale][1])

@section('legal-content')
    <div class="legal-crumb">
        <a href="{{ route('legal.index') }}">{{ $tr ? 'Yasal Belgeler' : 'Legal' }}</a>
        &rsaquo; {{ $meta[$legalLocale][0] }}
    </div>

    <div class="legal-head">
        <h1>{{ $meta[$legalLocale][0] }}</h1>
        <p>{{ $meta[$legalLocale][1] }}</p>
    </div>

    <div class="legal-grid">
        <nav class="legal-side">
            <p class="legal-side-title">{{ $tr ? 'DİĞER BELGELER' : 'ALL DOCUMENTS' }}</p>
            @foreach($documents as $slug => $doc)
                <a href="{{ route('legal.show', $slug) }}" class="{{ $slug === $document ? 'is-active' : '' }}">
                    {{ $doc[$legalLocale][0] }}
                </a>
            @endforeach
        </nav>

        <article class="legal-body">
            @include($body)

            <p class="legal-meta">
                {{ $tr ? 'Yürürlük tarihi' : 'Effective date' }}:
                {{ \Carbon\Carbon::parse($revised)->translatedFormat($tr ? 'd F Y' : 'F j, Y') }} &middot;
                {{ $company['name'] }} &middot;
                <a href="mailto:{{ $company['email'] }}">{{ $company['email'] }}</a>
            </p>
        </article>
    </div>
@endsection
