{{-- The shop's social media profiles as icon links (social_profiles(), Setup > General).
     Nothing when none is set. A theme can include it, or draw its own from social_profiles(). --}}
@php
    $socialProfiles = social_profiles();
    $socialIcons = ['x' => 'ri-twitter-x-line', 'facebook' => 'ri-facebook-circle-line', 'instagram' => 'ri-instagram-line', 'linkedin' => 'ri-linkedin-box-line', 'youtube' => 'ri-youtube-line', 'tiktok' => 'ri-tiktok-line', 'threads' => 'ri-threads-line', 'medium' => 'ri-medium-line'];
@endphp
@if($socialProfiles)
<div class="footer__social">
    @foreach($socialProfiles as $network => $url)
    <a href="{{ $url }}" target="_blank" rel="noopener me" aria-label="{{ \App\Support\SocialProfiles::NAMES[$network] }}"><i class="{{ $socialIcons[$network] }}" aria-hidden="true"></i></a>
    @endforeach
</div>
@endif
