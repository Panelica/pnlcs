{{-- ===== TOP BAR ===== --}}
<div class="top-bar">
    <div class="container">
        <div class="top-bar__inner">
            <div class="top-bar__left">
                <a href="mailto:{{ $brandEmail ?? 'info@panelica.com' }}" class="top-bar__item"><i class="ri-mail-line"></i> {{ $brandEmail ?? 'info@panelica.com' }}</a>
                <div class="top-bar__divider"></div>
                <a href="/client/contact" class="top-bar__item"><i class="ri-headphone-line"></i> {{ __('sections.topbar.contact') }}</a>
                <div class="top-bar__divider"></div>
                <a href="/client/tickets/create" class="top-bar__item"><i class="ri-ticket-line"></i> {{ __('sections.topbar.support_ticket') }}</a>
                <div class="top-bar__divider"></div>
                <a href="{{ $brandUrl ?? 'https://www.panelica.com' }}/blog" class="top-bar__item"><i class="ri-article-line"></i> {{ __('sections.topbar.blog') }}</a>
            </div>
            <div class="top-bar__right">
                <a href="{{ route('client.login') }}" class="top-bar__item"><i class="ri-user-line"></i> {{ __('sections.topbar.my_account') }}</a>
                <div class="top-bar__divider"></div>
                <a href="{{ route('client.register') }}" class="top-bar__item"><i class="ri-user-add-line"></i> {{ __('sections.topbar.sign_up') }}</a>
                <div class="top-bar__divider"></div>
                @if(isset($activeLanguages) && $activeLanguages->count() > 1)
                <details class="top-bar__language">
                    <summary class="top-bar__item top-bar__language-toggle">
                        <i class="ri-global-line"></i> {{ $currentLocaleName ?? __('client.topbar.language') }}
                    </summary>
                    <div class="top-bar__language-menu">
                        @foreach($activeLanguages as $language)
                            <a href="{{ request()->fullUrlWithQuery(['lang' => $language->code]) }}" class="top-bar__language-option {{ $language->code === ($currentLocale ?? app()->getLocale()) ? 'top-bar__language-option--active' : '' }}">
                                {{ $language->native_name }}
                            </a>
                        @endforeach
                    </div>
                </details>
                @else
                <span class="top-bar__item"><i class="ri-global-line"></i> {{ __('client.topbar.language') }}</span>
                @endif
                <div class="top-bar__divider"></div>
                <span class="top-bar__item"><i class="ri-money-dollar-circle-line"></i> {{ __('client.topbar.currency') }}</span>
            </div>
        </div>
    </div>
</div>
