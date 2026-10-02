{{-- Results under the admin bar's search box: clients, invoices, services,
     domains, tickets and orders, each only when the admin may open it
     (Admin\SearchController). Enter with nothing picked still searches the
     client list, as the box always did. Styles and script are inline so the
     box works without rebuilding the asset bundle. --}}
<div id="intellisearch-results" class="is-results" role="listbox" hidden></div>
<style>
.is-results { position:absolute; top:40px; right:0; width:420px; max-width:90vw; max-height:70vh; overflow:auto; background:#fff; color:#333; border:1px solid #d5d9e0; border-radius:4px; box-shadow:0 6px 24px rgba(0,0,0,.18); z-index:1050; text-align:left; font-size:13px; }
.is-results .is-group { padding:6px 12px 2px; font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:.04em; color:#888; }
.is-results a { display:block; padding:6px 12px; color:#333; text-decoration:none; line-height:1.35; }
.is-results a small { display:block; color:#888; font-size:12px; }
.is-results a.is-active, .is-results a:hover { background:#eef2f9; }
.is-results .is-empty { padding:10px 12px; color:#888; }
.is-results .is-all { border-top:1px solid #eee; color:#405189; }
[data-theme="dark"] .is-results { background:#1f2430; color:#e5e7eb; border-color:#374151; }
[data-theme="dark"] .is-results a { color:#e5e7eb; }
[data-theme="dark"] .is-results a.is-active, [data-theme="dark"] .is-results a:hover { background:#2b3242; }
</style>
<script>
(function () {
    var box = document.getElementById('intellisearch');
    if (!box) return;
    var input = box.querySelector('input[name="search"]');
    var list = document.getElementById('intellisearch-results');
    var endpoint = @json(route('admin.search'));
    var texts = @json(['none' => __('admin.search.no_results'), 'all' => __('admin.search.all_clients')]);
    var timer = null, controller = null, active = -1;

    function links() { return Array.prototype.slice.call(list.querySelectorAll('a')); }
    function close() { list.hidden = true; input.setAttribute('aria-expanded', 'false'); active = -1; }
    function highlight(i) {
        var all = links();
        all.forEach(function (a, n) { a.classList.toggle('is-active', n === i); a.setAttribute('aria-selected', n === i ? 'true' : 'false'); });
        active = i;
        if (all[i]) all[i].scrollIntoView({ block: 'nearest' });
    }
    function el(tag, cls, text) { var e = document.createElement(tag); if (cls) e.className = cls; if (text !== undefined) e.textContent = text; return e; }
    function render(data) {
        list.textContent = '';
        (data.groups || []).forEach(function (group) {
            list.appendChild(el('div', 'is-group', group.label));
            group.items.forEach(function (item) {
                var a = el('a', '', item.title);
                a.href = item.url;
                a.setAttribute('role', 'option');
                if (item.subtitle) a.appendChild(el('small', '', item.subtitle));
                list.appendChild(a);
            });
        });
        if (!(data.groups || []).length) list.appendChild(el('div', 'is-empty', texts.none));
        var all = el('a', 'is-all', texts.all.replace(':q', data.query));
        all.href = data.all_clients_url;
        list.appendChild(all);
        list.hidden = false;
        input.setAttribute('aria-expanded', 'true');
        active = -1;
    }
    function run() {
        var q = input.value.trim();
        if (q.length < 2) { close(); return; }
        if (controller) controller.abort();
        controller = new AbortController();
        fetch(endpoint + '?q=' + encodeURIComponent(q), { headers: { 'Accept': 'application/json' }, signal: controller.signal, credentials: 'same-origin' })
            .then(function (r) { return r.ok ? r.json() : null; })
            .then(function (data) { if (data && input.value.trim() === q) render(data); })
            .catch(function () {});
    }

    input.addEventListener('input', function () { clearTimeout(timer); timer = setTimeout(run, 200); });
    input.addEventListener('keydown', function (e) {
        if (list.hidden) return;
        var all = links();
        if (e.key === 'ArrowDown') { e.preventDefault(); highlight(Math.min(active + 1, all.length - 1)); }
        else if (e.key === 'ArrowUp') { e.preventDefault(); highlight(Math.max(active - 1, 0)); }
        else if (e.key === 'Escape') { close(); }
        else if (e.key === 'Enter' && active >= 0 && all[active]) { e.preventDefault(); window.location.href = all[active].href; }
    });
    // Keep the box open while a result is being clicked.
    list.addEventListener('mousedown', function (e) { e.preventDefault(); });
    input.addEventListener('blur', function () { setTimeout(close, 200); });
})();
</script>
