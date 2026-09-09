<p>These Terms of Service ("Agreement") are made between {{ \App\Http\Controllers\LegalController::partyName($company, $tr) }} ("the Company", "we") and the individual or legal entity using our services ("the Customer", "you"). Creating an account, placing an order or using our services means you accept this Agreement.</p>

@include('legal.partials.seller')

<h2>1. Definitions</h2>
<p><strong>Service</strong> means all hosting, domain registration and transfer, email, SSL, application deployment and related support we provide. <strong>Account</strong> means the record created in your name in the client area. <strong>Content</strong> means any file, database, email or data you host on the Service.</p>

<h2>2. Formation and term</h2>
<p>This Agreement takes effect when we accept your order and provision the service. Unless stated otherwise, services run for the billing period you chose (monthly, quarterly, semi-annual or annual) and renew automatically for the same period unless either party cancels.</p>
<p>To stop a renewal, submit a cancellation request from your client area before the current period ends. Once submitted, no further invoice is issued.</p>

<h2>3. Fees, billing and currency</h2>
<p>Prices are displayed on our site in <strong>US dollars (USD)</strong>. Invoices are issued in <strong>Turkish lira (TRY)</strong>, converted at the foreign exchange selling rate published by the Central Bank of the Republic of Türkiye (TCMB) on the day the order is placed. The rate applied, its date and the bulletin number are printed on your invoice so you can verify them against the Central Bank's own publication.</p>
<p>Customers resident in Türkiye are charged <strong>20% VAT</strong>. For customers outside Türkiye, tax is applied according to the law of the relevant country and the arrangements to which Türkiye is a party.</p>
<p>Invoices are payable on issue. Services with an overdue invoice may be suspended until payment is received. We send at least one reminder before suspending anything.</p>

<h2>4. Payment methods</h2>
<p>We accept <strong>bank transfer</strong> and <strong>credit and debit cards</strong>. The methods available for your particular order are listed at the payment step.</p>
<ul>
    <li><strong>Bank transfer:</strong> our bank details appear in your client area and in your invoice email after you place an order. Your service is provisioned once payment reaches our account.</li>
    <li><strong>Credit / debit card:</strong> card payments are taken through a licensed, PCI DSS compliant payment institution. <strong>Your card details never enter our systems and are not stored by us.</strong> Your service is provisioned automatically once the payment is authorised.</li>
</ul>
<p>Where you pay by card, confirming the order authorises recurring charges to that card so the service can renew automatically. You may withdraw that authorisation at any time from your client area; once withdrawn, the service ends at the close of the period already paid for.</p>

<h2>4.a. Money-back guarantee</h2>
<p>On hosting plans you may cancel and receive a full refund, no reason required, within <strong>30 days</strong> of an annual or longer purchase, or <strong>48 hours</strong> of a monthly one. The guarantee covers the first purchase only and does not extend to domains, SSL or renewal payments. Full conditions are in the <a href="{{ route('legal.show', 'refund') }}">Cancellation and Refund Policy</a>.</p>

<h2>5. Suspension and termination</h2>
<p>We may suspend or terminate a service where:</p>
<ul>
    <li>an invoice remains unpaid after a reminder,</li>
    <li>the <a href="{{ route('legal.show', 'aup') }}">Acceptable Use Policy</a> is breached,</li>
    <li>there is a concrete threat to the security of the service, the server or other customers,</li>
    <li>a binding order is received from a court, prosecutor or competent authority.</li>
</ul>
<p>Except where the threat is immediate, we notify you first and allow a reasonable period to put things right. Data on a suspended account is retained for <strong>30 days</strong> from the date of suspension, after which it may be permanently deleted.</p>

<h2>6. Backups and responsibility for data</h2>
<p>Automatic backups are enabled on the Apollo, Ares and Zeus plans and are accessible from the Panelica control panel. The free Hermes promotional plan does not include backups.</p>
<div class="legal-note">
    <p>Backups are a convenience, not an archival guarantee. <strong>Keeping your own independent copy of your Content is your responsibility.</strong> Where a backup proves incomplete, corrupt or unrestorable, our liability is limited to the service fee for the month concerned.</p>
</div>

<h2>7. Your obligations</h2>
<ul>
    <li>Keep your account details accurate and current. Invoices and important notices go to the email address in your client area; we are not responsible for notices that fail to reach an address you did not keep current.</li>
    <li>Keep your password and any two-factor credentials confidential. You are responsible for activity carried out through your account.</li>
    <li>Ensure the Content you host is lawful and that you hold the necessary rights, licences and permissions.</li>
    <li>Keep your applications, themes and plugins updated. Security breaches caused by unpatched software are outside our responsibility.</li>
</ul>

<h2>8. Intellectual property</h2>
<p>Your Content remains yours; this Agreement transfers no rights in it. You grant us only the permission needed to operate the service — storing, backing up, transmitting and displaying your Content. That permission ends automatically when the service ends.</p>
<p>Rights in our website, control panel interface, documentation and trade marks belong to us or our licensors.</p>

<h2>9. Limitation of liability</h2>
<p>To the fullest extent permitted by law, the services are provided "as is". We are not liable for loss of profit, business, goodwill or data, or for indirect losses.</p>
<p>In any event our total liability shall not exceed the amount you paid us for the relevant service in the <strong>twelve (12) months</strong> preceding the event giving rise to the claim.</p>
<p>Nothing in this section excludes liability for wilful misconduct or gross negligence, for death or personal injury, or any right that consumer law does not permit to be limited. If you are a consumer, this Agreement does not restrict your statutory rights in any way.</p>

<h2>10. Uptime</h2>
<p>Our uptime commitment and the credits payable if we miss it are set out in the <a href="{{ route('legal.show', 'sla') }}">Service Level Agreement</a>.</p>

<h2>11. Domain names</h2>
<p>Domain registration and renewal are subject to ICANN policy and the rules of the relevant registry, and are governed by a separate agreement: <a href="{{ route('legal.show', 'domain') }}">Domain Registration Agreement</a>.</p>

<h2>12. Personal data</h2>
<p>How we handle personal data is set out in the <a href="{{ route('legal.show', 'privacy') }}">Privacy Policy</a> and, for data we process on your behalf, in the <a href="{{ route('legal.show', 'dpa') }}">Data Processing Addendum</a>.</p>

<h2>13. Changes</h2>
<p>We may amend this Agreement. Material changes that are to your disadvantage are notified by email at least <strong>30 days</strong> before they take effect. Continuing to use the service after that notice constitutes acceptance; if you do not accept, you may terminate free of charge before the change takes effect and request a refund of the unused portion of your term.</p>
<p>An order is governed by the version of this Agreement in force on the day it was placed.</p>

<h2>14. Assignment</h2>
<p>You may not assign your rights or obligations under this Agreement without our written consent. We may assign it in connection with a merger, demerger or transfer of the business.</p>

<h2>15. Governing law and jurisdiction</h2>
<p>This Agreement is governed by the laws of the Republic of Türkiye. The <strong>Büyükçekmece Courts and Execution Offices</strong>, being those of the Company's registered seat, shall have jurisdiction.</p>
<p>If you are a consumer, that jurisdiction clause does not bind you: you may apply to the <strong>Consumer Arbitration Committee</strong> or <strong>Consumer Court</strong> in your own place of residence, within the applicable monetary thresholds. If you are resident in the European Union, the mandatory consumer protections of your own country continue to apply.</p>

<h2>16. Severability and entire agreement</h2>
<p>If any provision is held invalid, the remainder stays in force. This Agreement, together with the policies it refers to, constitutes the entire agreement between the parties.</p>
