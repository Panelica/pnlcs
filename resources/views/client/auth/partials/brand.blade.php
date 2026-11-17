{{-- The uploaded logo, or the shop's name in the theme colour. --}}
<div style="text-align:center;margin-bottom:20px;">
    @if(!empty($customLogo))
    <img src="{{ $customLogo }}" alt="{{ company_name() }}" style="max-height:48px;max-width:240px;">
    @else
    <div style="font-size:24px;font-weight:700;color:var(--theme-primary,#1a4d80);">{{ company_name() }}</div>
    @endif
</div>
