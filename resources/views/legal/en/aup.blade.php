<p>This policy sets out what may and may not be done on our servers and network. Its purpose is simple: one customer's behaviour must not degrade another's service, compromise our servers or damage the reputation of our IP addresses. It forms part of the <a href="{{ route('legal.show', 'terms') }}">Terms of Service</a> and applies to all our services.</p>

<h2>1. Prohibited content and activity</h2>
<p>The following are prohibited without exception:</p>
<ul>
    <li><strong>Child sexual abuse material.</strong> Accounts are terminated without notice and the matter is reported to the competent authorities.</li>
    <li><strong>Copyright infringement.</strong> Distributing software, films, music, books, games or licence keys without the rights holder's permission.</li>
    <li><strong>Phishing, fraud and impersonation sites.</strong> Imitating a bank, public authority, courier or any other brand.</li>
    <li><strong>Malware.</strong> Hosting or distributing viruses, trojans, ransomware, keyloggers, botnet panels or exploit kits.</li>
    <li><strong>Unauthorised access activity.</strong> Port scanning, brute-force attacks, vulnerability exploitation or password cracking — whether directed from our servers at others, or at us.</li>
    <li><strong>Denial-of-service attacks</strong> and reflection or amplification services that facilitate them.</li>
    <li><strong>Terrorist propaganda,</strong> incitement to violence, hate speech and incitement to crime.</li>
    <li><strong>Illegal gambling, narcotics or weapons trading, forged documents</strong> and other activity contrary to applicable law.</li>
    <li><strong>Any content unlawful under the law of the Republic of Türkiye,</strong> including the catalogue offences under Law No. 5651.</li>
</ul>

<h2>2. Email and bulk sending</h2>
<p>The reputation of our IP addresses is an asset shared by every customer. Accordingly:</p>
<ul>
    <li><strong>Unsolicited email (spam) is strictly prohibited.</strong> Purchased, scraped or inherited lists may not be mailed.</li>
    <li>Bulk sending requires <strong>explicit opt-in consent</strong> from recipients, and you must be able to evidence that consent.</li>
    <li>Every bulk email must carry a <strong>working unsubscribe link</strong>.</li>
    <li>Forging or misrepresenting sender addresses, domains or headers is prohibited.</li>
    <li>Hourly sending limits apply per plan. If you regularly need more, contact us in advance so we can propose something built for the volume.</li>
</ul>
<p>If your complaint rate rises or we detect behaviour likely to get us blacklisted, your sending privileges may be restricted immediately.</p>

<h2>3. Resource use</h2>
<p>Each account runs within its own allocated CPU, memory and disk limits, enforced at kernel level. Even so, the following are prohibited on shared hosting:</p>
<ul>
    <li>Cryptocurrency mining and similar workloads that hold the CPU at full load continuously,</li>
    <li>Use as general file storage, a backup repository or a media archive (content unrelated to the site you host),</li>
    <li>Torrent seeding, open proxies, open VPN exits and TOR exit nodes,</li>
    <li>Processes that attempt to escape the account or probe kernel or panel vulnerabilities.</li>
</ul>
<p>Where a workload affects other customers we contact you first and look for a solution together. Where the impact is immediate and severe, the process may be stopped and you notified afterwards.</p>

<h2>4. Your security obligations</h2>
<ul>
    <li>Keep applications, themes and plugins updated. The overwhelming majority of compromised accounts run outdated software.</li>
    <li>Use strong, unique passwords and enable two-factor authentication wherever it is offered.</li>
    <li>Tell us promptly if you suspect your account has been compromised.</li>
    <li><strong>Obtain written permission in advance</strong> before running any security testing against our servers. Unauthorised testing is treated as an attack.</li>
</ul>

<h2>5. Enforcement</h2>
<p>We match the response to the severity of the breach:</p>
<table>
    <thead><tr><th>Situation</th><th>Our response</th></tr></thead>
    <tbody>
        <tr><td>Compromised site, outdated software, excessive resource use</td><td>Notice and a period to remedy; the process may be limited temporarily if needed</td></tr>
        <tr><td>Spam, open proxy, copyright complaint</td><td>Suspension of the relevant function and a request to remedy</td></tr>
        <tr><td>Phishing, malware distribution, an active attack</td><td>Immediate suspension of the service</td></tr>
        <tr><td>Child sexual abuse material</td><td>Termination without notice and referral to the authorities</td></tr>
    </tbody>
</table>
<p>No refund is given for the unused portion of a service terminated for breach of this policy.</p>

<h2>6. Reporting</h2>
<p>Complaints about content hosted on our servers go to <a href="mailto:{{ $company['abuse_email'] }}">{{ $company['abuse_email'] }}</a>. How to file a report and how we handle it is set out in the <a href="{{ route('legal.show', 'abuse') }}">Abuse and Copyright Policy</a>.</p>
