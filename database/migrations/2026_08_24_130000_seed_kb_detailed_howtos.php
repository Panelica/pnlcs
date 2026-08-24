<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/*
 * The second layer of the knowledge base: the first seed drew the map, this
 * one walks every screen step by step - which tab, which button, which field,
 * in the words the interface actually uses. Written by reading the screens,
 * not from memory; a how-to that names a button that is not there teaches the
 * customer to distrust the whole book.
 *
 * Same rules as the first seed: repository-shipped so it survives any
 * database, matched by title so an operator's edits are never overwritten.
 */
return new class extends Migration
{
    /** @return array<string, array<int, array{0: string, 1: string}>> keyed by category name */
    private function articles(): array
    {
        return [
            'Getting Started' => [
                [
                    'Signing in, lost passwords, and active sessions',
                    <<<'TXT'
Sign in with the email address and password you set when the account was created.

Forgot the password? The login page has a reset link: enter your email, and a message with a reset link arrives if an account exists for it. The link opens a page where you set a new password.

Under Account, the Security page lists your active sessions - every device currently signed in, with its IP and last activity. If you see a session you do not recognise, press Revoke next to it and change your password immediately. Revoking a session signs that device out on its next click.

If two-factor authentication is enabled, signing in asks for a six-digit code after the password. Lost access to your authenticator app? Open a ticket from the email address on the account so support can verify you.
TXT,
                ],
                [
                    'Your profile and additional contacts',
                    <<<'TXT'
Under Account, the Profile page holds your name, company, address and phone - the details that appear on your invoices. Keep the email address current above all: invoices, ticket replies and renewal notices go there, and a dead address is how domains expire unnoticed.

The Contacts page adds more people to the account - a colleague, your developer, your accountant. Each contact has their own name and email. Use it instead of sharing your own password: sharing the password shares everything, including the right to close the account.
TXT,
                ],
            ],
            'Hosting & Websites' => [
                [
                    'The Files tab, button by button',
                    <<<'TXT'
Open your service and choose Files. You are looking at your webspace.

- Upload - press it, or simply drag files from your computer onto the page ("Drop here"). Progress is shown per file.
- New File / New Folder - create either, name it in the box that opens.
- Click a folder to enter it; the path above the listing walks you back up.
- Each file's row offers: Edit (opens text files in a browser editor - save writes straight back), Rename, Download, Permissions (the Unix mode, e.g. 644 for files, 755 for folders), and Delete, which asks before it acts.
- Archives: upload a .zip of your whole site and extract it on the server - far faster than uploading a thousand small files.

The listing shows each item's size and when it was last modified. If a page of your site behaves oddly after an edit, this Modified column tells you what changed last.
TXT,
                ],
                [
                    'FTP accounts: creating one and connecting',
                    <<<'TXT'
The FTP tab manages accounts for uploading with a desktop client such as FileZilla or WinSCP.

Press Create. You choose:
- Username - the account name.
- Password - use a generated one; you can change it later with Change password.
- Directory - where the account lands and is confined. Leave the default for the account root, or point it at a single folder to give someone access to just that folder.
- Quota (MB) - how much the account may store.

The Connection box on the same page shows exactly what to type into your client: host, port and protocol. Heed the protocol hint - connect with the secure variant your client offers, not plain FTP, so your password does not travel readable.

Your plan sets how many FTP accounts you may have; the page tells you when the limit is reached.
TXT,
                ],
                [
                    'Email mailboxes and webmail',
                    <<<'TXT'
The Email tab creates real mailboxes on your domain.

Press Create: pick the name (the part before the @), the domain, a password, and a quota in MB - or unlimited, if the plan allows. The new address can send and receive within moments.

- Webmail - every mailbox row has a Webmail button; it opens the inbox in the browser, no setup at all.
- Change password - per mailbox, from its row.
- Usage - the row shows how much of the quota is used.
- Delete - removes the mailbox AND its stored mail. Download anything you need first.

For a phone or desktop mail app, use the mail server settings shown on the page; the username is the full address.

If the tab says no domains are available, the service has no domain yet - email lives on a domain.
TXT,
                ],
                [
                    'DNS records on the hosting DNS tab',
                    <<<'TXT'
The DNS tab edits the zone of a domain on your hosting.

Press Create and fill four fields:
- Type - A for "this name points at this server address", CNAME for "this name is an alias of that name", MX for mail routing (with a Priority - lower is tried first), TXT for verifications such as SPF.
- Name - the host part. The hint under the field shows how it completes: www becomes www.yourdomain.com, @ means the domain itself.
- Value - the address or text the record answers with.
- TTL - how long the world may cache the answer, in seconds. 3600 is a sensible default.

Rows marked Protected are records the platform manages for your hosting to function - the hint on them explains why they resist editing. Change those only if you know exactly why.

Edits are live in the zone immediately, but the internet honours the old TTL, so give changes time.
TXT,
                ],
                [
                    'Subdomains',
                    <<<'TXT'
The Subdomains tab carves sections out of your domain - blog.yourdomain.com, shop.yourdomain.com - each with its own folder.

Press Create:
- Name - just the first label ("blog"); the page shows the full name it will become.
- Domain - which of your domains it hangs off.
- Document root - the folder its files live in; a default is suggested.
- PHP - the PHP version it runs, independent of the main site if you need that.
- SSL - note the hint: the certificate situation of a fresh subdomain is described right there.

A subdomain is a separate website in every practical sense: its own folder in Files, its own entry in DNS. Deleting one removes the subdomain, and asks first.
TXT,
                ],
                [
                    'Cron jobs: scheduled tasks',
                    <<<'TXT'
The Cron tab runs commands on a schedule - the heartbeat behind things like WordPress maintenance or a Laravel scheduler.

Press Create. Two modes:
- Basic - pick a frequency from a list.
- Advanced - set the five classic fields yourself: minute, hour, day of month, month, day of week.

The Command field is what runs. The Examples on the page cover the common cases - running a PHP script, a WordPress cron, a Laravel scheduler, or fetching a URL - copy the one that matches and adjust the path.

Email on error - give an address and the job's output is mailed to you when it fails, which is the only way you will ever hear about a broken nightly task.

Jobs can be disabled without deleting them - useful while debugging. Your plan caps how many jobs you may have.
TXT,
                ],
                [
                    'Backups on the Backups tab, precisely',
                    <<<'TXT'
The Backups tab shows your restore points and creates new ones.

Press Create: name the backup (or accept the suggestion), choose the scope - the whole account or specific domains - and whether it is full or incremental. Incremental records only what changed since the last one, so it is faster and smaller; full stands alone.

Each restore point lists what it contains. From a point you can download the archive - keep a copy off the server before risky work - or restore, which the hint on the page describes: restoring puts things back AS THEY WERE, overwriting what is there now.

Backups marked encrypted are stored encrypted at rest.

Rule of thumb: create a point before every upgrade or migration, and download one copy of anything you could not bear to lose.
TXT,
                ],
                [
                    'Database users, roles and the web manager',
                    <<<'TXT'
The Databases tab, in detail.

Creating a database offers to create its primary user in the same stroke - accept that unless you have a reason not to; a database without a user cannot be used by anything.

Add user attaches more users to an existing database, each with a role - full rights for the application itself, tighter rights for, say, a reporting tool that should only read.

Change password is per user, from its row. Your website's configuration must be updated with the new password at the same moment, or the site loses its database until you do.

The phpMyAdmin button opens the database in the web manager, already signed in - browse tables, run SQL, import and export dumps. Export is also the quickest manual database backup there is.

Deleting a database asks first, and means it: the data goes with it.
TXT,
                ],
            ],
            'Apps & Docker' => [
                [
                    "Your app's address, login details and shell",
                    <<<'TXT'
Every installed app on the Apps tab has a card, and the card carries what you need to actually use it:

- Open app - the address the app answers on. For an app linked to your domain, that is the domain; otherwise the server address and the app's port.
- Connection details - the logins the app was created with: admin users, database passwords. Values are masked; press the copy button next to one to copy it. These are the details recorded at install time - if you changed a password inside the app afterwards, the app is right and the card is stale.
- Data folder - where the app's files live in your account. You can reach it through the Files tab; it is yours.
- Open shell - a terminal inside the app's container, in the browser, as the app's own user or as root. For following logs, running the app's own command-line tools, quick fixes.

The card also shows the app's state. Crashing means it starts and dies repeatedly - the hint on the card says what to check first, and "My app says Not starting" in this category goes deeper.
TXT,
                ],
            ],
            'Domains' => [
                [
                    'The domain page, field by field',
                    <<<'TXT'
Open Domains and click one. The page is the domain's control room:

- Domain information - registration and expiry dates. The expiry date is the one to respect: a domain past it stops resolving, taking the website and every mailbox on it down.
- Auto Renew - on means an invoice is issued and the domain renewed before expiry, automatically. For any domain that matters, on.
- Nameservers - who answers DNS for this domain. Save changes here only when moving DNS hosting; the page will say if the registrar does not allow it.
- Manage DNS - the record editor, when the domain uses our DNS.
- Registrar Lock - keeps transfers away blocked. Leave it on.
- ID Protection - keeps your details out of public WHOIS.
- Get EPP Code - the transfer password, for when you deliberately move the domain elsewhere.

Renewal itself is an invoice like any other: it appears under Invoices ahead of the date, and paying it is what renews the domain.
TXT,
                ],
            ],
            'Billing' => [
                [
                    'Promo codes',
                    <<<'TXT'
A promotion code is entered in the cart, before checkout: the cart page has a field for it, and applying a valid code recalculates the totals immediately - you see the discount before paying anything.

Codes have terms set by the operator: some work only on the first invoice, some on every renewal, some only for specific products or billing cycles. If a code is refused, the cart says so rather than silently ignoring it.

One code applies per cart.
TXT,
                ],
                [
                    'Upgrading or downgrading a plan',
                    <<<'TXT'
Open the service and choose Upgrade/Downgrade. The page shows the plan you are currently on and the plans you may move to; pick one and press Request change.

What happens next depends on the direction. An upgrade is priced for the remainder of the current period - you pay the difference, not a full new term. The service's resources change once the request is processed and any difference is settled.

Your files, databases, email and apps ride along untouched: a plan change resizes the account, it does not rebuild it. Downgrading to a plan smaller than what you currently use - more disk in use than the new plan allows, more apps than it permits - is the one case to check before requesting.

If the page says no changes are available, the operator has not defined an upgrade path for your product; a ticket is the way to ask.
TXT,
                ],
            ],
            'SSL Certificates' => [
                [
                    'Ordering an SSL certificate',
                    <<<'TXT'
The SSL page under your client area lists your certificates and sells new ones - these are paid certificates issued by a certificate authority, the kind that carries organisation validation or warranty where the product includes it.

Ordering is like any product: pick the certificate, pay the invoice. The new certificate then appears under My certificates as Pending configuration - it exists as an order, and the next article's configuration step is what turns it into an issued certificate.

Certificates have their own expiry, shown on each one's page. Renewal is a fresh invoice ahead of the date, like a domain.
TXT,
                ],
                [
                    'Configuring and validating your certificate',
                    <<<'TXT'
Open the pending certificate and press Configure now. Three things happen on this page:

1. The CSR. A certificate signing request carries the domain name (the Common name) and, on multi-domain certificates, the additional names (SANs). Paste one from your server if you have it - the page decodes it so you can check what it says - or let the page generate it. The Common name must be exactly the domain the certificate is for.
2. Contact details - the administrative contact the authority records.
3. The validation method - how the authority checks the domain is yours:
   - Email - a message to an address on the domain; click its link.
   - DNS - add the CNAME record the page shows to the domain's DNS.
   - HTTP - place the file the page provides at the path it names on your site.

After validation the authority issues the certificate and it lands on the certificate's page, ready to install. If validation stalls, the page shows which method it is waiting on - the DNS record or file it expects is spelled out there.
TXT,
                ],
            ],
            'Support' => [
                [
                    'What happens after you open a ticket',
                    <<<'TXT'
A new ticket lands with the department you chose, marked with your priority and, if you set one, the related service - which is why setting it matters: the person answering opens your ticket already looking at the right product.

Replies arrive two ways at once: on the ticket's page, and as an email to your account address. Answering the email or replying on the page are the same conversation.

A ticket stays open while the conversation is live. When it is answered and done, it is closed - and a closed ticket is not a locked door: replying to it brings it back.

Attachments can be added with any reply, not just the first message. When support asks for a screenshot or a log, drop it into your next reply on the ticket page.
TXT,
                ],
            ],
        ];
    }

    public function up(): void
    {
        $now = now();

        foreach ($this->articles() as $categoryName => $articles) {
            $categoryId = DB::table('kb_categories')->where('name', $categoryName)->value('id');
            if (! $categoryId) {
                // The first seed creates every category; standing alone (a
                // fresh install runs migrations in order) this still works.
                $categoryId = DB::table('kb_categories')->insertGetId([
                    'name' => $categoryName,
                    'description' => '',
                    'hidden' => false,
                    'sort_order' => 9,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            $sort = (int) DB::table('kb_articles')->where('category_id', $categoryId)->max('sort_order');
            foreach ($articles as [$title, $body]) {
                if (! DB::table('kb_articles')->where('title', $title)->exists()) {
                    DB::table('kb_articles')->insert([
                        'category_id' => $categoryId,
                        'title' => $title,
                        'article' => $body,
                        'private' => false,
                        'sort_order' => ++$sort,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        foreach ($this->articles() as $articles) {
            DB::table('kb_articles')->whereIn('title', array_column($articles, 0))->delete();
        }
        DB::table('kb_categories')->where('name', 'SSL Certificates')
            ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('kb_articles')
                ->whereColumn('kb_articles.category_id', 'kb_categories.id'))
            ->delete();
    }
};
