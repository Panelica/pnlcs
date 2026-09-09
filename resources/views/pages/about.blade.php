{{--
     About us.

     Shares legal.layout: that layout is not really specific to legal text, it
     is a readable page wrapped in the site's own menu and footer, and writing
     it twice would be worse than sharing it.

     The prose is the operator's own, kept in the AboutText setting so it can
     be written from the panel without editing a file. The company details
     come from the same settings the invoice, the contracts and the contact
     page read, so the four can never disagree.
--}}
@extends('legal.layout')

@php $tr = app()->getLocale() === 'tr'; @endphp

@section('legal-title', __('client.pages.about_title'))
@section('legal-description', __('client.pages.about_description', ['company' => $company['name']]))

@section('legal-content')
    <div class="legal-head">
        <h1>{{ __('client.pages.about_title') }}</h1>
        <p>{{ __('client.pages.about_description', ['company' => $company['name']]) }}</p>
    </div>

    <div class="legal-grid">
        <nav class="legal-side">
            <p class="legal-side-title">{{ __('client.pages.on_this_page') }}</p>
            @if($about !== '')
                <a href="#who">{{ __('client.pages.about_who') }}</a>
            @endif
            <a href="#details">{{ __('client.pages.company_details') }}</a>
        </nav>

        <article class="legal-body">
            @if($about !== '')
                <h2 id="who">{{ __('client.pages.about_who') }}</h2>
                {{-- Plain paragraphs: the operator typed text, not markup. --}}
                @foreach(preg_split('/\R{2,}/', trim($about)) as $paragraph)
                    <p>{!! nl2br(e(trim($paragraph))) !!}</p>
                @endforeach
            @endif

            <h2 id="details">{{ __('client.pages.company_details') }}</h2>
            @include('legal.partials.seller', ['company' => $company, 'tr' => $tr])
        </article>
    </div>
@endsection
