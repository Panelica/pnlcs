{{-- ===== FOOTER ===== --}}
@php
    $c = $content ?? collect();
    $footerDesc = $c->has('description') ? $c->get('description')->content_value : null;
    $footerEmail = $c->has('email') ? $c->get('email')->content_value : null;
    $footerSupportEmail = $c->has('support_email') ? $c->get('support_email')->content_value : null;
    $footerWebsite = $c->has('website') ? $c->get('website')->content_value : null;

    // White-label fallbacks
    $bName = $brandName ?? 'PNLCS';
    $bUrl = $brandUrl ?? 'https://www.panelica.com';
    $bEmail = $footerEmail ?? ($brandEmail ?? 'info@panelica.com');
    $bSupportEmail = $footerSupportEmail ?? 'support@panelica.com';
    $bWebsite = $footerWebsite ?? 'panelica.com';
    $bCopyright = $brandCopyright ?? $bName;
    $bDesc = $footerDesc ?? ($bName . ' ' . __('sections.footer.description'));
@endphp
<footer class="footer">
    <div class="container">
        <div class="footer__grid">
            <div>
                <div class="footer__brand">
                    @if(!empty($customLogo))
                        <img src="{{ $customLogo }}" alt="{{ $bName }}" style="height: 28px;">
                    @else
                        {{ $bName }}<span class="footer__brand-dot"></span>
                    @endif
                </div>
                <p class="footer__desc">{{ $bDesc }}</p>
                <div class="footer__contact-item"><i class="ri-mail-line"></i> {{ $bEmail }}</div>
                <div class="footer__contact-item"><i class="ri-customer-service-line"></i> {{ $bSupportEmail }}</div>
                <div class="footer__contact-item"><i class="ri-global-line"></i> {{ $bWebsite }}</div>
            </div>
            <div>
                <div class="footer__col-title">{{ __('sections.footer.col_domains') }}</div>
                <a href="/client/domain-search" class="footer__link">{{ __('sections.footer.domain_search') }}</a>
                <a href="/client/domain-search" class="footer__link">{{ __('sections.footer.domain_transfer') }}</a>
                <a href="/client/domain-search" class="footer__link">{{ __('sections.footer.whois_lookup') }}</a>
            </div>
            <div>
                <div class="footer__col-title">{{ __('sections.footer.col_hosting') }}</div>
                <a href="/client/store" class="footer__link">{{ __('sections.footer.shared_hosting') }}</a>
                <a href="/client/store" class="footer__link">{{ __('sections.footer.wordpress_hosting') }}</a>
                <a href="/client/store" class="footer__link">{{ __('sections.footer.business_hosting') }}</a>
                <a href="/client/store" class="footer__link">{{ __('sections.footer.reseller_hosting') }}</a>
                <a href="/client/store" class="footer__link">{{ __('sections.footer.vps_server') }}</a>
            </div>
            <div>
                <div class="footer__col-title">{{ __('sections.footer.col_support') }}</div>
                <a href="/client/knowledgebase" class="footer__link">{{ __('sections.footer.knowledge_base') }}</a>
                <a href="/client/announcements" class="footer__link">{{ __('sections.footer.announcements') }}</a>
                <a href="/client/contact" class="footer__link">{{ __('sections.footer.contact_us') }}</a>
                <a href="{{ $bUrl }}" class="footer__link">{{ $bWebsite }}</a>
                <a href="{{ $bUrl }}/blog" class="footer__link">{{ __('sections.footer.blog') }}</a>
            </div>
        </div>
        <div class="footer__bottom">
            <span>&copy; {{ date('Y') }} {{ $bCopyright }}. {{ __('sections.footer.copyright') }}</span>
            <div class="footer__payments">
                <div class="footer__payment-icon">VISA</div>
                <div class="footer__payment-icon">MC</div>
                <div class="footer__payment-icon">AMEX</div>
                <div class="footer__payment-icon">PP</div>
                <div class="footer__payment-icon">BTC</div>
            </div>
        </div>
    </div>
</footer>
