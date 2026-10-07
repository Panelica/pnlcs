{{-- Where the operator is: 1 connect, 2 map, 3 import. $current = 1..3 --}}
@php($labels = [1 => __('whmcs_import.steps.connect'), 2 => __('whmcs_import.steps.map'), 3 => __('whmcs_import.steps.import')])
<ol class="wi-steps" aria-label="{{ __('whmcs_import.title') }}">
    @foreach($labels as $n => $label)
        @php($state = $n < $current ? 'done' : ($n === $current ? 'current' : 'pending'))
        <li class="wi-step wi-step--{{ $state }}" @if($state === 'current') aria-current="step" @endif>
            <span class="wi-step__dot">@if($state === 'done')<i class="fas fa-check"></i>@else{{ $n }}@endif</span>
            <span>{{ $label }}</span>
        </li>
        @if(! $loop->last)
            <li class="wi-steps__line {{ $n < $current ? 'wi-steps__line--done' : '' }}" aria-hidden="true"></li>
        @endif
    @endforeach
</ol>
