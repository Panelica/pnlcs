<p>This document sets out the uptime we commit to for our hosting services and what we do if we miss it. It forms part of the <a href="{{ route('legal.show', 'terms') }}">Terms of Service</a>.</p>

<h2>1. The commitment</h2>
<p>On paid hosting plans we commit to <strong>99.9% network and server availability</strong> measured per calendar month. That corresponds to roughly <strong>43 minutes</strong> of unplanned downtime per month.</p>
<p>The commitment covers the availability of the server and the network. It does not cover your own application failing through its own fault.</p>

<h2>2. Exclusions</h2>
<p>The following are not counted as downtime:</p>
<ul>
    <li><strong>Planned maintenance.</strong> Announced by email at least 72 hours in advance, carried out where possible between 02:00 and 06:00 Türkiye time, and not exceeding 4 hours in total per month.</li>
    <li><strong>Emergency security maintenance.</strong> Closing a vulnerability under active exploitation. Announced afterwards — waiting would cause more harm than the interruption.</li>
    <li>Problems originating in your application, theme, plugin or configuration.</li>
    <li>Slowness or errors caused by exceeding your plan's resource limits.</li>
    <li>Suspensions for breach of the <a href="{{ route('legal.show', 'aup') }}">Acceptable Use Policy</a> or for unpaid invoices.</li>
    <li>Access problems caused by your domain's DNS settings, its expiry, or its status at the registrar.</li>
    <li>Problems in the wider internet between you and our data centre that are outside our control.</li>
    <li>Force majeure: natural disaster, war, general strike, nationwide infrastructure or power failure, or an order of a competent authority.</li>
</ul>

<h2>3. Service credits</h2>
<p>If we fall below the commitment we credit your account against the monthly fee for the affected service:</p>
<table>
    <thead><tr><th>Monthly availability</th><th>Approximate downtime</th><th>Credit</th></tr></thead>
    <tbody>
        <tr><td>99.90% – 99.00%</td><td>43 min – 7.2 hours</td><td>10% of the monthly fee</td></tr>
        <tr><td>99.00% – 98.00%</td><td>7.2 – 14.4 hours</td><td>25% of the monthly fee</td></tr>
        <tr><td>98.00% – 95.00%</td><td>14.4 – 36 hours</td><td>50% of the monthly fee</td></tr>
        <tr><td>Below 95.00%</td><td>More than 36 hours</td><td>100% of the monthly fee</td></tr>
    </tbody>
</table>
<p>On annual plans the monthly fee is one twelfth of the annual amount.</p>

<h3>Claiming</h3>
<p>Credits are not automatic; you must claim them. Open a support ticket within <strong>30 days</strong> of the end of the month in which the downtime occurred, stating the dates and times. We compare your claim against our own monitoring records and decide within <strong>10 business days</strong>.</p>
<p>Credits are applied to your account balance and used against future invoices. No cash payment is made. Total credits in a calendar month cannot exceed that month's service fee.</p>

<h2>4. Free plans</h2>
<p>The free Hermes promotional plan is outside this SLA. We make every reasonable effort to keep it running but give no commitment, and no credits apply.</p>

<h2>5. Support response targets</h2>
<p>Support requests can be raised from the client area or by emailing <a href="mailto:{{ $company['email'] }}">{{ $company['email'] }}</a>. Our target first-response times:</p>
<table>
    <thead><tr><th>Priority</th><th>Example</th><th>Target first response</th></tr></thead>
    <tbody>
        <tr><td>Critical</td><td>Site entirely unreachable, server down</td><td>1 hour</td></tr>
        <tr><td>High</td><td>Email not sending, SSL failure, severe slowness</td><td>4 hours</td></tr>
        <tr><td>Normal</td><td>Configuration questions, panel usage</td><td>12 hours</td></tr>
        <tr><td>Low</td><td>Information requests, upgrade questions</td><td>24 hours</td></tr>
    </tbody>
</table>
<div class="legal-note">
    <p>These are <strong>targets, not commitments</strong>, and missing one does not generate a credit. Our servers are monitored automatically around the clock and critical alarms reach us immediately, but we do not offer 24/7 live telephone support. We would rather be precise about what we provide than promise something we cannot keep.</p>
</div>

<h2>6. Data durability</h2>
<p>Automatic backups run on the Apollo, Ares and Zeus plans and are accessible from the Panelica control panel. The free Hermes plan does not include backups.</p>
<p>Backups are a convenience, not an archival guarantee; you should keep your own independent copy. Where a backup proves incomplete or unrestorable, our liability is limited to the service fee for the month concerned.</p>

<h2>7. Sole remedy</h2>
<p>The credits described here are your <strong>sole and exclusive remedy</strong> for a failure to meet the uptime commitment. That limit does not apply to damage arising from our wilful misconduct or gross negligence, nor to mandatory consumer rights.</p>
