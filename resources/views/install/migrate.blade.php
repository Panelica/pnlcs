@extends('install.layout', ['step' => 'database'])

@section('title', __('install.migrate_title'))

@section('content')
<h2 class="text-xl font-semibold text-slate-900 mb-1">{{ __('install.migrate_title') }}</h2>
<p class="text-slate-600 text-sm mb-6">{{ __('install.migrate_intro') }}</p>

<div class="w-full h-3 bg-slate-200 rounded-full overflow-hidden">
    <div id="migrate-bar" class="h-3 bg-blue-600 rounded-full transition-all duration-300" style="width: {{ $total > 0 ? (int) floor($ran * 100 / $total) : 0 }}%"></div>
</div>
<p id="migrate-count" class="text-sm text-slate-600 mt-2">{{ __('install.migrate_progress', ['done' => $ran, 'total' => $total]) }}</p>

<div id="migrate-error" class="hidden mt-4 p-3 rounded bg-red-50 border border-red-200 text-red-700 text-sm"></div>
<button type="button" id="migrate-retry" class="hidden mt-4 px-5 py-2 bg-blue-600 text-white text-sm font-semibold rounded hover:bg-blue-700">
    {{ __('install.migrate_retry') }}
</button>

<script>
(() => {
    const text = @json(['progress' => __('install.migrate_progress'), 'failed' => __('install.migrate_failed')]);
    const bar = document.getElementById('migrate-bar');
    const count = document.getElementById('migrate-count');
    const error = document.getElementById('migrate-error');
    const retry = document.getElementById('migrate-retry');
    const token = document.querySelector('meta[name=csrf-token]').content;

    const show = (data) => {
        if (!data.total) return;
        bar.style.width = Math.floor(data.ran * 100 / data.total) + '%';
        count.textContent = text.progress.replace(':done', data.ran).replace(':total', data.total);
    };
    const fail = (message) => {
        error.textContent = text.failed.replace(':message', message);
        error.classList.remove('hidden');
        retry.classList.remove('hidden');
    };

    // Each request runs the migrations for a few seconds; a request cut off
    // by the web server (502-504) leaves the next one to find the work still
    // going on, so those are retried for a while before giving up.
    let retries = 0;
    const step = async () => {
        let data = null, status = 0;
        try {
            const resp = await fetch('/install/database/migrate', {
                method: 'POST',
                headers: {'X-CSRF-TOKEN': token, 'Accept': 'application/json'},
            });
            status = resp.status;
            data = await resp.json();
        } catch (e) {
            if ((status === 0 || status >= 502) && retries++ < 40) { setTimeout(step, 3000); return; }
        }
        if (!data) { fail('HTTP ' + status); return; }
        retries = 0;
        show(data);
        // Anything but a report from this step (an expired session's 419, an
        // error page) stops here instead of being asked again and again.
        if (data.ok !== true) { fail(data.message || ('HTTP ' + status)); return; }
        if (data.done) { window.location.href = '/install/admin'; return; }
        setTimeout(step, data.busy ? 2000 : 50);
    };

    retry.addEventListener('click', () => {
        error.classList.add('hidden');
        retry.classList.add('hidden');
        step();
    });
    step();
})();
</script>
@endsection
