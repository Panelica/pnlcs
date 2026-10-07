{{-- The PNLCS update bar (App\Services\Updates\UpdateBar). --}}
@php($updateBar = app(\App\Services\Updates\UpdateBar::class)->forAdmin(auth('admin')->user(), request()->route()?->getName()))
@if($updateBar)
<style>
    [x-cloak]{display:none !important}
    .pn-update-bar{position:fixed;left:0;right:0;bottom:0;z-index:1990;display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:8px 16px;padding:10px 16px 10px 56px;font-size:13px;box-shadow:0 -2px 8px rgba(0,0,0,.12)}
    .pn-update-bar--available{background:#fff8e6;border-top:2px solid #f0ad4e;color:#5c4400}
    .pn-update-bar--unfinished{background:#fdecea;border-top:2px solid #d9534f;color:#7a1f1c}
    .pn-update-bar__text{display:flex;align-items:center;gap:8px;min-width:0;flex:1 1 280px}
    .pn-update-bar__actions{display:flex;flex-wrap:wrap;gap:6px;align-items:center}
    .pn-update-bar__actions form{margin:0}
    .pn-update-pill{position:fixed;right:16px;bottom:16px;z-index:1990;box-shadow:0 2px 8px rgba(0,0,0,.2)}
    .pn-update-spacer{height:56px}
    @media (max-width:600px){.pn-update-spacer{height:96px}}
</style>
@if($updateBar['kind'] === 'unfinished')
<div class="pn-update-spacer"></div>
<div class="pn-update-bar pn-update-bar--unfinished" role="alert">
    <span class="pn-update-bar__text"><i class="fas fa-exclamation-triangle"></i> {{ __('admin.updates.bar_unfinished', ['version' => $updateBar['version']]) }}</span>
    <span class="pn-update-bar__actions"><a href="{{ route('admin.config.updates') }}" class="btn btn-danger btn-sm">{{ __('admin.updates.bar_open') }}</a></span>
</div>
@else
<div x-data="{
        key: 'pnlcs_update_bar_hidden',
        version: @js($updateBar['version']),
        hidden: false,
        init() { try { this.hidden = localStorage.getItem(this.key) === this.version } catch (e) {} },
        hide() { this.hidden = true; try { localStorage.setItem(this.key, this.version) } catch (e) {} },
        show() { this.hidden = false; try { localStorage.removeItem(this.key) } catch (e) {} },
    }">
    <div class="pn-update-spacer" x-show="!hidden"></div>
    <div class="pn-update-bar pn-update-bar--available" x-show="!hidden" role="status">
        <span class="pn-update-bar__text">
            <i class="fas fa-cloud-download-alt"></i>
            <span>{{ __('admin.updates.bar_available', ['version' => $updateBar['version'], 'installed' => $updateBar['installed']]) }}</span>
            @if($updateBar['pre_release'])<span class="badge badge-warning">{{ __('admin.updates.beta_badge') }}</span>@endif
        </span>
        <span class="pn-update-bar__actions">
            <a href="{{ route('admin.config.updates') }}" class="btn btn-primary btn-sm">{{ __('admin.updates.bar_open') }}</a>
            <button type="button" class="btn btn-default btn-sm" @click="hide()">{{ __('admin.updates.bar_hide') }}</button>
            <form method="POST" action="{{ route('admin.config.updates.bar') }}" onsubmit="return confirm(@js(__('admin.updates.bar_off_confirm')))">
                @csrf
                <input type="hidden" name="show" value="0">
                <button type="submit" class="btn btn-default btn-sm">{{ __('admin.updates.bar_off') }}</button>
            </form>
        </span>
    </div>
    <button type="button" class="btn btn-primary btn-sm pn-update-pill" x-show="hidden" x-cloak @click="show()">
        <i class="fas fa-cloud-download-alt"></i> {{ __('admin.updates.bar_pill', ['version' => $updateBar['version']]) }}
    </button>
</div>
@endif
@endif
