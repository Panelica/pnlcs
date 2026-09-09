<p>This policy explains the cookies and similar technologies used on {{ $company['website'] }}. The short answer first: <strong>this site sets no advertising cookies, tracking pixels or third-party analytics.</strong> Every cookie we use is either necessary for the site to work or remembers a preference you chose.</p>

<h2>1. What a cookie is</h2>
<p>A cookie is a small text file a site stores in your browser. On your next visit the site reads it to recognise you — for example, that you are signed in, or which language you picked.</p>

<h2>2. The cookies we use</h2>
<table>
    <thead><tr><th>Cookie</th><th>Purpose</th><th>Lifetime</th><th>Type</th></tr></thead>
    <tbody>
        <tr>
            <td><code>{{ config('session.cookie') }}</code></td>
            <td>Keeps you signed in. Without it, signing in to the client area is impossible.</td>
            <td>2 hours</td>
            <td>Strictly necessary</td>
        </tr>
        <tr>
            <td><code>XSRF-TOKEN</code></td>
            <td>Protects your form submissions against cross-site request forgery. A security measure.</td>
            <td>2 hours</td>
            <td>Strictly necessary</td>
        </tr>
        <tr>
            <td><code>pnlcs_locale</code></td>
            <td>Remembers the language you chose so you need not pick it again.</td>
            <td>30 days</td>
            <td>Preference</td>
        </tr>
        <tr>
            <td><code>pnlcs_theme</code></td>
            <td>Remembers whether you prefer the light or dark theme.</td>
            <td>Until the browser closes</td>
            <td>Preference</td>
        </tr>
        <tr>
            <td><code>pnlcs_aff</code></td>
            <td>If you arrived through an affiliate link, records which partner referred you so their commission can be paid. Set only when you arrive via a referral link.</td>
            <td>90 days</td>
            <td>Functional</td>
        </tr>
    </tbody>
</table>

<h2>3. What we do not use</h2>
<p>To be explicit — <strong>none</strong> of the following are present on this site:</p>
<ul>
    <li>Google Analytics or any other third-party analytics,</li>
    <li>Facebook, Google Ads, TikTok or similar advertising pixels,</li>
    <li>Retargeting cookies,</li>
    <li>Heatmaps, session recording or behavioural tracking,</li>
    <li>Any data sharing with advertising networks.</li>
</ul>
<p>Because every cookie we set is either strictly necessary or a preference you chose, <strong>no prior consent is required</strong> under the GDPR and ePrivacy rules, and we do not show you a cookie banner. Should we ever introduce analytics or advertising cookies, they will run only with your explicit consent and this page will be updated first.</p>

<h2>4. Third-party resources</h2>
<p>Our pages load two external resources. Neither sets a cookie, but your browser does connect to them:</p>
<ul>
    <li><strong>Google Fonts</strong> (<code>fonts.googleapis.com</code>, <code>fonts.gstatic.com</code>) — page typefaces.</li>
    <li><strong>jsDelivr CDN</strong> (<code>cdn.jsdelivr.net</code>) — interface icons and JavaScript libraries.</li>
</ul>
<p>No cookie is created by these connections, but your IP address does technically reach those servers. We are working to serve these assets from our own infrastructure.</p>

<h2>5. Managing cookies</h2>
<p>You can delete or block cookies in your browser settings:</p>
<ul>
    <li><strong>Chrome:</strong> Settings &rsaquo; Privacy and security &rsaquo; Third-party cookies</li>
    <li><strong>Firefox:</strong> Settings &rsaquo; Privacy &amp; Security &rsaquo; Cookies and Site Data</li>
    <li><strong>Safari:</strong> Settings &rsaquo; Privacy</li>
    <li><strong>Edge:</strong> Settings &rsaquo; Cookies and site permissions</li>
</ul>
<div class="legal-note">
    <p>If you block the strictly necessary cookies you will not be able to sign in, place an order or open a support ticket. They are not a preference; they are how the site works.</p>
</div>

<h2>6. Related documents</h2>
<p>For how we handle personal data, see the <a href="{{ route('legal.show', 'privacy') }}">Privacy Policy</a>.</p>
