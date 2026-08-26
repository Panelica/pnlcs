@extends('admin.layouts.app')
@section('title', __('admin.api_docs.title'))
@section('content')

<style>
.api-section { margin-bottom: 32px; }
.api-section h3 { font-size: 16px; font-weight: 700; padding: 8px 12px; background: #f5f5f5; border-left: 4px solid #337ab7; margin: 0 0 0 0; border-radius: 2px; }
.api-table { width: 100%; border-collapse: collapse; font-size: 13px; margin-top: 0; }
.api-table th { background: #fafafa; padding: 8px 10px; text-align: left; border-bottom: 2px solid #e5e5e5; font-weight: 600; color: #555; }
.api-table td { padding: 7px 10px; border-bottom: 1px solid #f0f0f0; vertical-align: top; }
.api-table tr:hover { background: #fafcff; }
.badge-get { background: #5cb85c; color: #fff; padding: 1px 6px; border-radius: 3px; font-size: 11px; font-weight: 700; }
.badge-post { background: #337ab7; color: #fff; padding: 1px 6px; border-radius: 3px; font-size: 11px; font-weight: 700; }
.ep-url { font-family: monospace; font-size: 12px; color: #333; }
.ep-desc { color: #555; }
.code-block { background: #1e1e1e; color: #d4d4d4; padding: 15px; border-radius: 4px; font-family: monospace; font-size: 12px; overflow-x: auto; margin: 0; }
.tab-btns { display: flex; gap: 4px; margin-bottom: 8px; }
.tab-btn { padding: 4px 12px; border: 1px solid #ddd; background: #f5f5f5; cursor: pointer; border-radius: 3px; font-size: 12px; }
.tab-btn.active { background: #337ab7; color: #fff; border-color: #337ab7; }
.tab-pane { display: none; }
.tab-pane.active { display: block; }
.toc-list { column-count: 3; column-gap: 20px; padding: 0; list-style: none; margin: 0; }
.toc-list li { margin-bottom: 4px; }
.toc-list a { color: #337ab7; font-size: 13px; text-decoration: none; }
.toc-list a:hover { text-decoration: underline; }
@media (max-width: 900px) { .toc-list { column-count: 2; } }
@media (max-width: 600px) { .toc-list { column-count: 1; } }
</style>

<div class="page-header" style="display:flex;align-items:center;justify-content:space-between;">
    <h1>{{ __('admin.api_docs.title') }}</h1>
    <a href="{{ route('admin.config.api-credentials') }}" class="btn btn-default btn-sm">{{ __('admin.api_docs.manage_keys') }}</a>
</div>

{{-- Authentication --}}
<div class="card" style="margin-bottom:24px;">
    <div class="card-header" style="background:#f5f5f5;padding:12px 16px;border-bottom:1px solid #e5e5e5;">
        <h2 style="margin:0;font-size:16px;font-weight:700;">{{ __('admin.api_docs.auth_title') }}</h2>
    </div>
    <div class="card-body" style="padding:16px;">
        <p style="margin:0 0 12px;font-size:13px;color:#555;">{!! __('admin.api_docs.auth_intro') !!}</p>
        <table style="width:100%;border-collapse:collapse;font-size:13px;margin-bottom:16px;">
            <thead><tr><th style="text-align:left;padding:6px 10px;background:#fafafa;border-bottom:2px solid #e5e5e5;">{{ __('admin.api_docs.parameter') }}</th><th style="text-align:left;padding:6px 10px;background:#fafafa;border-bottom:2px solid #e5e5e5;">{{ __('common.table.description') }}</th></tr></thead>
            <tbody>
                <tr><td style="padding:6px 10px;border-bottom:1px solid #f0f0f0;"><code>identifier</code></td><td style="padding:6px 10px;border-bottom:1px solid #f0f0f0;">{{ __('admin.api_docs.identifier_desc') }}</td></tr>
                <tr><td style="padding:6px 10px;border-bottom:1px solid #f0f0f0;"><code>secret</code></td><td style="padding:6px 10px;border-bottom:1px solid #f0f0f0;">{{ __('admin.api_docs.secret_desc') }}</td></tr>
                <tr><td style="padding:6px 10px;"><code>action</code></td><td style="padding:6px 10px;">{!! __('admin.api_docs.action_param_desc') !!}</td></tr>
            </tbody>
        </table>
        <p style="font-size:13px;color:#555;margin:0 0 8px;"><strong>{{ __('admin.api_docs.base_url') }}:</strong> <code>POST {{ url('/api/v1') }}</code></p>
        <p style="font-size:12px;color:#888;margin:0;">{!! __('admin.api_docs.response_note') !!}</p>
    </div>
</div>

{{-- Table of Contents --}}
<div class="card" style="margin-bottom:24px;">
    <div class="card-header" style="background:#f5f5f5;padding:12px 16px;border-bottom:1px solid #e5e5e5;">
        <h2 style="margin:0;font-size:16px;font-weight:700;">{{ __('admin.api_docs.toc') }}</h2>
    </div>
    <div class="card-body" style="padding:16px;">
        <ul class="toc-list">
            @foreach($sections as $section => $rows)
            <li><a href="#section-{{ $section }}">{{ __('admin.api_docs.section_'.$section) }} ({{ count($rows) }})</a></li>
            @endforeach
            <li><a href="#section-examples">{{ __('admin.api_docs.section_examples') }}</a></li>
        </ul>
    </div>
</div>

{{-- Endpoint tables, one per API controller. The rows come from the live
     route table (ConfigController::apiDocs), so this reference cannot promise
     an endpoint that does not exist or omit one that does. --}}
@foreach($sections as $section => $rows)
<div class="card api-section" id="section-{{ $section }}">
    <h3>{{ __('admin.api_docs.section_'.$section) }}</h3>
    <table class="api-table">
        <thead><tr><th style="width:70px;">{{ __('admin.api_docs.method') }}</th><th style="width:260px;">{{ __('admin.api_docs.action') }}</th><th>{{ __('common.table.description') }}</th><th style="width:260px;">{{ __('admin.api_docs.key_params') }}</th></tr></thead>
        <tbody>
            @foreach($rows as $slug => $row)
            <tr>
                <td><span class="badge-{{ strtolower($row['method']) }}">{{ $row['method'] }}</span></td>
                <td class="ep-url">{{ $slug }}</td>
                <td class="ep-desc">{{ $row['desc'] }}</td>
                <td>{{ $row['params'] }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endforeach

{{-- CODE EXAMPLES --}}
<div class="card api-section" id="section-examples">
    <h3>{{ __('admin.api_docs.section_examples') }}</h3>
    <div style="padding:16px;">

        <h4 style="font-size:14px;font-weight:700;margin:0 0 8px;">{{ __('admin.api_docs.example_get_clients') }}</h4>
        <div class="tab-btns">
            <button class="tab-btn active" onclick="switchTab('curl1','tab-curl1-btn')">cURL</button>
            <button class="tab-btn" onclick="switchTab('php1','tab-php1-btn')">PHP</button>
            <button class="tab-btn" onclick="switchTab('python1','tab-python1-btn')">Python</button>
        </div>
        <div id="curl1" class="tab-pane active">
<pre class="code-block">curl -X POST {{ url('/api/v1') }} \
  -d "identifier=YOUR_IDENTIFIER" \
  -d "secret=YOUR_SECRET" \
  -d "action=getclients" \
  -d "limitnum=25"</pre>
        </div>
        <div id="php1" class="tab-pane">
<pre class="code-block">&lt;?php
$url = '{{ url('/api/v1') }}';
$params = [
    'identifier' => 'YOUR_IDENTIFIER',
    'secret'     => 'YOUR_SECRET',
    'action'     => 'getclients',
    'limitnum'   => 25,
];
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$result = json_decode(curl_exec($ch), true);
curl_close($ch);
print_r($result);</pre>
        </div>
        <div id="python1" class="tab-pane">
<pre class="code-block">import requests

url = '{{ url('/api/v1') }}'
payload = {
    'identifier': 'YOUR_IDENTIFIER',
    'secret': 'YOUR_SECRET',
    'action': 'getclients',
    'limitnum': 25,
}
response = requests.post(url, data=payload)
print(response.json())</pre>
        </div>

        <hr style="margin:20px 0;">

        <h4 style="font-size:14px;font-weight:700;margin:0 0 8px;">{{ __('admin.api_docs.example_create_invoice') }}</h4>
        <div class="tab-btns">
            <button class="tab-btn active" onclick="switchTab('curl2','tab-curl2-btn')">cURL</button>
            <button class="tab-btn" onclick="switchTab('php2','tab-php2-btn')">PHP</button>
        </div>
        <div id="curl2" class="tab-pane active">
<pre class="code-block">curl -X POST {{ url('/api/v1') }} \
  -d "identifier=YOUR_IDENTIFIER" \
  -d "secret=YOUR_SECRET" \
  -d "action=createinvoice" \
  -d "userid=1" \
  -d "date=2026-01-01" \
  -d "duedate=2026-01-15" \
  -d "itemdescription[]=Hosting - January 2026" \
  -d "itemamount[]=29.99" \
  -d "paymentmethod=banktransfer"</pre>
        </div>
        <div id="php2" class="tab-pane">
<pre class="code-block">&lt;?php
$params = [
    'identifier'       => 'YOUR_IDENTIFIER',
    'secret'           => 'YOUR_SECRET',
    'action'           => 'createinvoice',
    'userid'           => 1,
    'date'             => '2026-01-01',
    'duedate'          => '2026-01-15',
    'itemdescription'  => ['Hosting - January 2026'],
    'itemamount'       => [29.99],
    'paymentmethod'    => 'banktransfer',
];
// ... send POST request ...</pre>
        </div>

        <hr style="margin:20px 0;">

        <h4 style="font-size:14px;font-weight:700;margin:0 0 8px;">{{ __('admin.api_docs.example_response') }}</h4>
<pre class="code-block">{
  "result": "success",        {{ __('admin.api_docs.response_success_comment') }}
  "totalresults": 25,         {{ __('admin.api_docs.response_total_comment') }}
  "startnumber": 0,           {{ __('admin.api_docs.response_start_comment') }}
  "numreturned": 10,          {{ __('admin.api_docs.response_num_comment') }}
  "clients": {                {{ __('admin.api_docs.response_data_comment') }}
    "client": [ ... ]
  }
}

{{ __('admin.api_docs.response_error_comment') }}
{
  "result": "error",
  "message": "Authentication Failed"
}</pre>

    </div>
</div>

@push('scripts')
<script>
function switchTab(paneId, btnClass) {
    // Find parent container
    var pane = document.getElementById(paneId);
    if (!pane) return;
    var container = pane.parentElement;
    // Deactivate all panes and buttons in container
    container.querySelectorAll('.tab-pane').forEach(function(p) { p.classList.remove('active'); });
    container.querySelectorAll('.tab-btn').forEach(function(b) { b.classList.remove('active'); });
    // Activate clicked
    pane.classList.add('active');
    event.target.classList.add('active');
}
</script>
@endpush

@endsection
