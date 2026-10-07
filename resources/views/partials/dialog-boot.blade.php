{{-- PNLCS dialogs (resources/js/dialogs.js): their labels, and a stand-in for
     pnConfirm() that holds a click made before the script has loaded and hands
     it over once it has. The action is never taken without the question. --}}
<meta name="pn-dialog" content="{{ json_encode(['ok' => __('common.dialog.ok'), 'confirm' => __('common.actions.confirm'), 'cancel' => __('common.actions.cancel'), 'delete' => __('common.actions.delete'), 'confirm_title' => __('common.dialog.confirm_title')], JSON_UNESCAPED_UNICODE) }}">
<script>
window.pnConfirm = window.pnConfirm || function (e, message, options) {
    if (e && e.preventDefault) { e.preventDefault(); }
    var held = { type: e.type, target: e.target, currentTarget: e.currentTarget, submitter: e.submitter };
    (function wait(n) {
        if (window.__pnConfirm) { return window.__pnConfirm(held, message, options || {}); }
        if (n < 200) { setTimeout(function () { wait(n + 1); }, 50); }
    })(0);
    return false;
};
</script>
