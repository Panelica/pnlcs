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
    $bDesc = $footerDesc ?? ($bName . ' is the billing & hosting management platform by Panelica. Domains, hosting, VPS, and SSL — all under one roof.');
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
                <div class="footer__col-title">Domains</div>
                <a href="/client/domain-search" class="footer__link">Domain Search</a>
                <a href="/client/domain-search" class="footer__link">Domain Transfer</a>
                <a href="/client/domain-search" class="footer__link">WHOIS Lookup</a>
            </div>
            <div>
                <div class="footer__col-title">Hosting</div>
                <a href="/client/store" class="footer__link">Shared Hosting</a>
                <a href="/client/store" class="footer__link">WordPress Hosting</a>
                <a href="/client/store" class="footer__link">Business Hosting</a>
                <a href="/client/store" class="footer__link">Reseller Hosting</a>
                <a href="/client/store" class="footer__link">VPS Server</a>
            </div>
            <div>
                <div class="footer__col-title">Support</div>
                <a href="/client/knowledgebase" class="footer__link">Knowledge Base</a>
                <a href="/client/announcements" class="footer__link">Announcements</a>
                <a href="/client/contact" class="footer__link">Contact Us</a>
                <a href="{{ $bUrl }}" class="footer__link">{{ $bWebsite }}</a>
                <a href="{{ $bUrl }}/blog" class="footer__link">Blog</a>
            </div>
        </div>
        <div class="footer__bottom">
            <span>&copy; {{ date('Y') }} {{ $bCopyright }}. All rights reserved.</span>
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
