<p>This Data Processing Addendum ("DPA") supplements the <a href="{{ route('legal.show', 'terms') }}">Terms of Service</a> and applies to personal data we process <strong>on your behalf</strong> when you use our services. No separate signature is needed; it takes effect when you use the service.</p>

<div class="legal-note">
    <p><strong>Roles.</strong> For the visitor, customer and user data you host on your site, <strong>you are the controller</strong> and <strong>we are the processor</strong>. You decide what that data is, why it is collected and how long it is kept.</p>
    <p>For data about your own account, invoices and support correspondence, <strong>we are the controller</strong> — see the <a href="{{ route('legal.show', 'privacy') }}">Privacy Policy</a>.</p>
</div>

<h2>1. Subject matter and duration</h2>
<p><strong>Subject matter:</strong> the provision of hosting, email, database, backup and support services.</p>
<p><strong>Duration:</strong> for the term of the service relationship and, after it ends, for the deletion periods set out below.</p>
<p><strong>Nature of processing:</strong> storage, hosting, transmission, backup, restoration, deletion, and access in the course of support.</p>
<p><strong>Categories of data:</strong> determined by you. Typically visitor and customer identity, contact, order and payment details, user accounts and site content.</p>
<p><strong>Categories of data subject:</strong> determined by you — visitors, customers, members, employees.</p>

<h2>2. Your instructions</h2>
<p>We process personal data only on your <strong>documented instructions</strong>. Your use of the service and the directions you give in support requests constitute instructions for this purpose.</p>
<p>If we consider an instruction to infringe applicable data protection law, we will tell you before acting on it. Where a legally binding requirement compels us to act outside your instructions, we will inform you first unless the law prohibits that notice.</p>
<p>We do not use your data for our own purposes, do not sell it and do not profile it for advertising.</p>

<h2>3. Confidentiality</h2>
<p>Personnel with access to your data are bound by confidentiality obligations that survive the end of their engagement. Access is limited to those who genuinely need it and is logged.</p>

<h2>4. Security measures</h2>
<p>The technical and organisational measures we maintain under GDPR Art. 32 and KVKK Art. 12:</p>
<ul>
    <li><strong>Encryption in transit:</strong> all web and panel traffic is protected by TLS, with certificates renewed automatically.</li>
    <li><strong>Isolation:</strong> each customer account runs under its own system user with resources separated at kernel level; one account cannot reach another's files.</li>
    <li><strong>Access control:</strong> administrative access is key-based, two-factor authentication is mandatory, and password-based SSH is disabled.</li>
    <li><strong>Network defence:</strong> host firewall with allowlist-based port management, ModSecurity web application firewall, malware scanning.</li>
    <li><strong>Backups:</strong> regular backups held in restricted directories.</li>
    <li><strong>Logging:</strong> access and security events are logged and retained in reviewable form.</li>
    <li><strong>Physical security:</strong> our servers are housed in an ISO 27001 certified data centre.</li>
</ul>

<h2>5. Sub-processors</h2>
<p>We use the following sub-processors:</p>
<table>
    <thead><tr><th>Sub-processor</th><th>Service</th><th>Location</th></tr></thead>
    <tbody>
        <tr><td>Hetzner Online GmbH</td><td>Server and data centre infrastructure</td><td>Germany (EU)</td></tr>
        <tr><td>DomainNameAPI</td><td>Domain registration — only the contact data registration requires</td><td>Türkiye</td></tr>
    </tbody>
</table>
<p>Each sub-processor is bound by contract to obligations at least equivalent to those in this DPA. We give at least <strong>30 days' notice</strong> by email before adding or replacing a sub-processor. If you object on reasonable and substantiated grounds we will look for a solution with you; if none is found you may terminate without penalty and receive a refund of the unused portion of your term.</p>

<h2>6. International transfers</h2>
<p>Our servers are in Germany, so your data is processed within the EU as a rule. Where a transfer outside the EEA becomes necessary, it is made under the European Commission's Standard Contractual Clauses or an adequacy decision, and in accordance with the international transfer provisions of Turkish Law No. 6698.</p>

<h2>7. Data subject requests</h2>
<p>If a data subject contacts us directly we do not answer on your behalf; we <strong>forward the request to you without delay</strong>, because you are the controller for that data.</p>
<p>We provide the technical assistance you need to satisfy requests for access, rectification, erasure, restriction and portability. Where the panel already gives you access to the data, those tools are treated as sufficient assistance.</p>

<h2>8. Personal data breach notification</h2>
<p>If we become aware of a breach affecting data we process on your behalf we notify you <strong>without undue delay and in any event within 24 hours</strong>. Our notice includes, so far as known:</p>
<ul>
    <li>the nature of the breach, the categories and approximate numbers of data subjects and records affected,</li>
    <li>the likely consequences,</li>
    <li>the measures taken and proposed,</li>
    <li>a contact point for further information.</li>
</ul>
<p>Because the duty to notify the supervisory authority rests with you as controller, we provide the information and evidence you need to do so within your own deadline.</p>

<h2>9. Audit</h2>
<p>We make available the information needed to demonstrate compliance with this DPA. Once a year, on at least 30 days' written notice, during business hours and in a manner that does not expose other customers' data, you may conduct an audit or appoint an independent auditor. You bear the cost; if the audit reveals a material non-compliance, we bear it.</p>

<h2>10. Deletion and return at the end of the service</h2>
<p>When the service ends:</p>
<ul>
    <li>you may access and export your data for <strong>30 days</strong>;</li>
    <li>at the end of that period data is permanently deleted from live systems;</li>
    <li>copies held in backups are removed in the ordinary backup cycle, within <strong>90 days</strong> at the latest;</li>
    <li>records we are legally required to keep (invoices, logs under Law No. 5651) are retained until the end of the applicable period and processed for that purpose only.</li>
</ul>

<h2>11. Conflict</h2>
<p>Where this DPA and the <a href="{{ route('legal.show', 'terms') }}">Terms of Service</a> conflict on a matter of personal data, this DPA prevails. Questions: <a href="mailto:{{ $company['kvkk_email'] }}">{{ $company['kvkk_email'] }}</a></p>
