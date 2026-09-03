@props([
    'nipSelector' => 'input[name="tax_id"]',
])

@if(app(\App\Services\AddonManager::class)->isActive('company_lookup'))
<div data-company-lookup
     data-endpoint="{{ route('company.lookup') }}"
     data-csrf="{{ csrf_token() }}"
     data-nip-selector="{{ $nipSelector }}"
     style="display:inline-flex;align-items:center;">
    <button type="button" class="btn btn-default" data-nip-lookup-trigger
        style="white-space:nowrap;flex-shrink:0;height:34px;">{{ __('messages.company_lookup.fetch') }}</button>
</div>

@once
<script>
(function () {
    var L = {!! json_encode([
        'fetch' => __('messages.company_lookup.fetch'),
        'fetching' => __('messages.company_lookup.fetching'),
        'fetched' => __('messages.company_lookup.fetched'),
        'not_found' => __('messages.company_lookup.not_found'),
        'invalid_nip' => __('messages.company_lookup.invalid_nip'),
        'conflicts' => __('messages.company_lookup.conflicts'),
        'overwrite_all' => __('messages.company_lookup.overwrite_all'),
        'vat_status' => __('messages.company_lookup.vat_status'),
        'regon' => __('messages.company_lookup.regon'),
        'legal_form' => __('messages.company_lookup.legal_form'),
        'business_status' => __('messages.company_lookup.business_status'),
        'bank_accounts' => __('messages.company_lookup.bank_accounts'),
        'pkd' => __('messages.company_lookup.pkd'),
        'error' => __('messages.company_lookup.error'),
    ]) !!};

    function statusNode(host) {
        var el = host.querySelector('.nip-lookup-status');
        if (!el) {
            el = document.createElement('div');
            el.className = 'nip-lookup-status';
            el.style.cssText = 'font-size:12px;margin-top:6px;';
            host.appendChild(el);
        }
        return el;
    }

    function applyValue(form, name, value, conflicts) {
        var el = form.querySelector('[name="' + name + '"]');
        if (!el || value === null || value === undefined) return;
        value = String(value).trim();
        if (value === '') return;
        if (el.value.trim() === '') {
            el.value = value;
        } else if (el.value.trim() !== value) {
            conflicts.push(name);
        }
    }

    function infoText(c) {
        var bits = [];
        if (c.regon) bits.push(L.regon + ': ' + c.regon);
        if (c.legal_form) bits.push(L.legal_form + ': ' + c.legal_form);
        if (c.vat && c.vat.status) bits.push(L.vat_status + ': ' + c.vat.status);
        if (c.business_status) bits.push(L.business_status + ': ' + c.business_status);
        return bits.join(' · ');
    }

    function renderInfo(host, c) {
        var info = infoText(c);
        var accounts = c.bank_accounts || [];
        var pkd = c.pkd || [];
        var title = [];
        if (accounts.length) title.push(L.bank_accounts + ': ' + accounts.join(', '));
        if (pkd.length) title.push(L.pkd + ': ' + pkd.join(', '));

        var html = '<span style="color:#2e7d32;">' + L.fetched + '</span>';
        if (info) html += ' <span style="color:var(--muted,#777);">' + escapeHtml(info) + '</span>';
        if (title.length) html += ' <span style="color:var(--muted,#777);cursor:help;border-bottom:1px dotted;" title="' + escapeHtml(title.join('\n')) + '">ⓘ</span>';

        var el = statusNode(host);
        el.innerHTML = html;
        el.style.color = '';
    }

    function escapeHtml(s) {
        return String(s).replace(/[&<>"']/g, function (ch) {
            return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[ch];
        });
    }

    function handle(root, trigger) {
        var host = trigger.closest('.form-group') || trigger.parentElement;
        var form = root.closest('form') || trigger.form;
        var nipScope = form || document;
        var nipInput = nipScope.querySelector(root.getAttribute('data-nip-selector') || 'input[name="tax_id"]');
        var nip = nipInput ? nipInput.value : '';
        var original = trigger.innerHTML;

        var st = statusNode(host);
        st.innerHTML = '';
        st.style.color = '';

        if (!nip.trim()) {
            st.innerHTML = '<span style="color:#c0392b;">' + L.invalid_nip + '</span>';
            return;
        }

        trigger.disabled = true;
        trigger.innerHTML = L.fetching;

        fetch(root.getAttribute('data-endpoint'), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': root.getAttribute('data-csrf'),
            },
            body: JSON.stringify({ nip: nip }),
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            trigger.disabled = false;
            trigger.innerHTML = original;

            if (!data.success) {
                if (data.error === 'INVALID_NIP') {
                    st.innerHTML = '<span style="color:#c0392b;">' + L.invalid_nip + '</span>';
                } else if (data.error === 'COMPANY_NOT_FOUND') {
                    st.innerHTML = '<span style="color:#c0392b;">' + L.not_found + '</span>';
                } else {
                    st.innerHTML = '<span style="color:#c0392b;">' + L.error + '</span>';
                }
                return;
            }

            var c = data.company || {};
            var conflicts = [];

            if (form) {
                if (c.name) applyValue(form, 'company_name', c.name, conflicts);
                if (c.nip) applyValue(form, 'tax_id', c.nip, conflicts);

                var addr = c.address || {};
                var number = addr.building_number || '';
                if (addr.apartment_number) number += '/' + addr.apartment_number;
                var streetFull = [addr.street, number].filter(Boolean).join(' ');
                if (streetFull) applyValue(form, 'address1', streetFull, conflicts);
                if (addr.postal_code) applyValue(form, 'postcode', addr.postal_code, conflicts);
                if (addr.city) applyValue(form, 'city', addr.city, conflicts);
                if (addr.voivodeship) applyValue(form, 'state', addr.voivodeship, conflicts);
            }

            renderInfo(host, c);

            if (conflicts.length) {
                var note = document.createElement('div');
                note.style.cssText = 'font-size:12px;color:#b7791f;margin-top:4px;';
                note.innerHTML = L.conflicts + ' <a href="#" data-nip-overwrite style="text-decoration:underline;">' + L.overwrite_all + '</a>';
                host.appendChild(note);

                note.querySelector('[data-nip-overwrite]').addEventListener('click', function (ev) {
                    ev.preventDefault();
                    if (!form) return;
                    if (c.name) form.querySelector('[name="company_name"]').value = c.name;
                    if (c.nip) form.querySelector('[name="tax_id"]').value = c.nip;
                    var addr2 = c.address || {};
                    var num2 = addr2.building_number || '';
                    if (addr2.apartment_number) num2 += '/' + addr2.apartment_number;
                    var full2 = [addr2.street, num2].filter(Boolean).join(' ');
                    if (full2) form.querySelector('[name="address1"]').value = full2;
                    if (addr2.postal_code) form.querySelector('[name="postcode"]').value = addr2.postal_code;
                    if (addr2.city) form.querySelector('[name="city"]').value = addr2.city;
                    if (addr2.voivodeship) form.querySelector('[name="state"]').value = addr2.voivodeship;
                    note.remove();
                });
            }
        })
        .catch(function () {
            trigger.disabled = false;
            trigger.innerHTML = original;
            st.innerHTML = '<span style="color:#c0392b;">' + L.error + '</span>';
        });
    }

    document.querySelectorAll('[data-company-lookup]').forEach(function (root) {
        var trigger = root.querySelector('[data-nip-lookup-trigger]');
        if (!trigger) return;

        trigger.addEventListener('click', function () { handle(root, trigger); });
    });
})();
</script>
@endonce
@endif
