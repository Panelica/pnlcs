{{-- ===== FLAVOR FOOTER (Minimal 3-column) ===== --}}
@php
    $c = $content ?? collect();
    $bName = $brandName ?? 'PNLCS';
    $bUrl = $brandUrl ?? 'https://www.panelica.com';
    $bEmail = $c->has('email') ? $c->get('email')->content_value : ($brandEmail ?? 'info@panelica.com');
    $bCopyright = $brandCopyright ?? $bName;
@endphp
<footer class="footer" style="padding: 48px 0 0;">
    <div class="container">
        <div style="display:grid;grid-template-columns:1.5fr 1fr 1fr;gap:40px;margin-bottom:40px;">
            <div>
                <div class="footer__brand">
                    @if(!empty($customLogo))
                        <img src="{{ $customLogo }}" alt="{{ $bName }}" style="height: 28px;">
                    @else
                        {{ $bName }}<span class="footer__brand-dot"></span>
                    @endif
                </div>
                <p class="footer__desc" style="max-width:300px;">Reliable hosting, domains, and servers — all managed from a single platform.</p>
                <div class="footer__contact-item"><i class="ri-mail-line"></i> {{ $bEmail }}</div>
            </div>
            <div>
                <div class="footer__col-title">Products</div>
                <a href="/client/store" class="footer__link">Shared Hosting</a>
                <a href="/client/store" class="footer__link">VPS Server</a>
                <a href="/client/domain-search" class="footer__link">Domain Search</a>
                <a href="/client/store" class="footer__link">SSL Certificates</a>
            </div>
            <div>
                <div class="footer__col-title">Company</div>
                <a href="/client/knowledgebase" class="footer__link">Knowledge Base</a>
                <a href="/client/announcements" class="footer__link">Announcements</a>
                <a href="/client/contact" class="footer__link">Contact</a>
                <a href="{{ $bUrl }}" class="footer__link">About Us</a>
            </div>
        </div>
        <div class="footer__bottom">
            <span>&copy; {{ date('Y') }} {{ $bCopyright }}. All rights reserved.</span>
            <div class="footer__payments">
                <div class="footer__payment-icon">VISA</div>
                <div class="footer__payment-icon">MC</div>
                <div class="footer__payment-icon">PP</div>
            </div>
        </div>
    </div>
</footer>
