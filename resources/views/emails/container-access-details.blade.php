<x-mail::message>
# {{ __('client.hosting.containers.email_title', ['app' => $appName]) }}

{{ __('client.hosting.containers.email_intro') }}

@if($accessUrl)
<x-mail::button :url="$accessUrl">
{{ __('client.hosting.containers.open_app') }}
</x-mail::button>
@endif

<x-mail::table>
| | |
|:--|:--|
@foreach($items as $label => $value)
| **{{ $label }}** | `{{ $value }}` |
@endforeach
</x-mail::table>

@if($notes !== '')
{{ $notes }}
@endif

{{ __('client.hosting.containers.email_footer') }}

{{ $companyName }}
</x-mail::message>
