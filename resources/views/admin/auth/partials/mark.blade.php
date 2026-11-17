{{-- The shop's logo, or its initial in the theme colour, instead of a
     hard-coded "P". --}}
@if(!empty($customLogo))
<img src="{{ $customLogo }}" alt="{{ company_name() }}" style="max-height:56px;max-width:220px;margin-bottom:12px;">
@else
<div style="display:inline-flex;align-items:center;justify-content:center;width:56px;height:56px;background:var(--theme-primary,#1a4d80);border-radius:8px;margin-bottom:12px;">
    <span style="color:#fff;font-size:22px;font-weight:700;">{{ mb_strtoupper(mb_substr(company_name(), 0, 1)) }}</span>
</div>
@endif
