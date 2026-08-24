<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/*
 * The knowledge base had one category - Apps & Docker - and nothing else, and
 * the homepage's "How it works" button lands exactly here. Everything a
 * customer does in the client area now has a written answer: ordering, the
 * hosting tools, domains, billing, support. Written against the real screens,
 * naming the real tabs and buttons.
 *
 * In the repository, deliberately: hand-entered articles die with the
 * database - they did, once - and content shipped as a migration comes back
 * with every install. Plain text; the view escapes HTML.
 *
 * Operator/admin documentation is NOT here: it lives on the docs site
 * (mkdocs -> panelica.github.io/pnlcs), which the admin dashboard links to.
 * This knowledge base is public and customer-facing.
 */
return new class extends Migration
{
    /** @return array<string, array{sort: int, description: string, articles: array<int, array{0: string, 1: string}>}> */
    private function categories(): array
    {
        return [
            'Getting Started' => [
                'sort' => 1,
                'description' => 'Your first order, your client area, and how to keep your account safe.',
                'articles' => [
                    [
                        'Ordering: you do not need an account first',
                        <<<'TXT'
Browse the store and configure a product - billing cycle, options, a domain if the product wants one - without signing in. Nothing asks you to register while you shop.

Your account is created at the payment step. The checkout page asks for your name, email and a password right above the payment method; press the order button once and the account is opened, you are signed in, and the order is placed - one step, nothing to repeat.

Already have an account? The checkout page has a sign in link. Log in there and your cart comes with you.

After ordering you land in the client area, where the order and its invoice are waiting under Services and Invoices.
TXT,
                    ],
                    [
                        'Finding your way around the client area',
                        <<<'TXT'
Everything you own is reachable from the navigation:

- Services - your hosting plans and apps. Open one to reach its tools: files, databases, email, DNS and more.
- Domains - every domain on the account: renewal dates, nameservers, DNS, transfers.
- Invoices - open and paid invoices, each downloadable as a PDF.
- Tickets - your conversations with support.
- Account - profile, password, security and the contacts allowed to reach support on your behalf.

The dashboard shows the things that need you: unpaid invoices, expiring domains, open tickets.
TXT,
                    ],
                    [
                        'Securing your account',
                        <<<'TXT'
Under Account you will find:

- Password - change it any time. You need the current password to set a new one.
- Security - two-factor authentication. Once enabled, signing in asks for a six-digit code from your authenticator app on top of the password. If someone learns your password, they still cannot get in.
- Contacts - additional people (a colleague, your developer) allowed on the account, with their own details.

Use a password you use nowhere else, and turn on two-factor - it is the single most effective thing on this page.
TXT,
                    ],
                ],
            ],
            'Hosting & Websites' => [
                'sort' => 2,
                'description' => 'The tools inside your hosting service: files, databases, email, DNS, cron, backups.',
                'articles' => [
                    [
                        'The tools inside your service',
                        <<<'TXT'
Open Services and click your hosting plan. The tabs across the top are your toolbox:

- Files - browse, upload, edit and extract files in your webspace, in the browser.
- Databases - create databases and users; open a database in the web database manager.
- Email - mailboxes and forwarders on your domain.
- DNS - the records of your domain zone.
- Subdomains - carve blog.yourdomain.com and friends out of your site.
- Cron - scheduled jobs that run on your account.
- FTP - accounts for uploading with an FTP client.
- Backups - what is kept of your account, and restore.
- Apps - one-click applications (covered in the Apps & Docker section).

Everything here acts on your own account only.
TXT,
                    ],
                    [
                        'Putting your website online',
                        <<<'TXT'
Two ways to get files into your webspace:

1. The Files tab - upload straight from the browser. Archives can be uploaded whole and extracted on the server, which is the fastest way to move a large site.
2. FTP - create an account under the FTP tab and connect with a client such as FileZilla, using the server address, the username and the password shown there.

Your site is served from the public web folder of your account. Put the site's index file there - index.html or index.php - and your domain shows it.

If you are moving in from another host: upload the files, import the database under Databases, then update your site's configuration file with the new database name, user and password.
TXT,
                    ],
                    [
                        'Databases',
                        <<<'TXT'
Under the Databases tab:

1. Create a database.
2. Create a database user with a strong password.
3. Connect them, so the user may use the database.

Your website's configuration needs three things: the database name, the username and the password. The database server address is usually localhost.

The web database manager opens from the same tab - browse tables, run queries, import and export dumps. Importing an existing site's dump here is the usual last step of a migration.
TXT,
                    ],
                    [
                        'Backups: what is kept, and how to get it back',
                        <<<'TXT'
The Backups tab shows what the platform holds for your account and lets you restore from it.

Two habits worth having anyway:

- Before a big change - upgrading your site's software, editing its configuration - download a copy of the files and export the database. Five minutes now beats an evening later.
- After finishing a migration in, take one backup so the earliest good state of the new home is on record.

Restores overwrite what is currently there. If you are unsure, open a ticket first and say what you need back - support can see what exists for your account.
TXT,
                    ],
                ],
            ],
            'Domains' => [
                'sort' => 4,
                'description' => 'Registering, transferring, DNS, nameservers, renewal and protection.',
                'articles' => [
                    [
                        'Registering a domain',
                        <<<'TXT'
Use Domain Search - from the homepage or the client area. Type the name, and the results show whether it is free and what it costs per year, along with suggestions for other endings.

Press Register on the one you want, and it goes to the cart like any product. After payment the domain is registered to you and appears under Domains.

From its page you manage everything: nameservers, DNS records, the registrar lock, ID protection and renewal. Auto-renew is worth turning on for any domain you care about - an expired domain takes your website and email down with it, and getting one back after expiry is slow and sometimes expensive.
TXT,
                    ],
                    [
                        'Transferring a domain to us',
                        <<<'TXT'
A transfer moves the management and billing of your domain here. Your website keeps running throughout.

Three steps, in this order:

1. Unlock the domain at your current registrar - turn off its transfer lock.
2. Request the EPP code there (also called an Auth code). It is usually emailed to the domain owner.
3. Open Domain Transfer here, enter the domain and the code, and complete the order.

The transfer usually completes within 5-7 days; your current registrar may email you a confirmation that speeds things up. The registration time you already paid for is kept, and a year is added on top.

The step that stalls most transfers is the second one - if you cannot find the EPP code, it comes from your CURRENT registrar, not from us.
TXT,
                    ],
                    [
                        'Nameservers, DNS and the difference',
                        <<<'TXT'
Nameservers say WHO answers questions about your domain. DNS records are the answers themselves.

If your domain uses our nameservers, edit records under the domain's Manage DNS: A records point names at server addresses, CNAME records point names at other names, MX records route your email, TXT records carry verifications (SPF, domain ownership and so on).

If the domain points at another provider's nameservers - a CDN, an external DNS host - then records are edited THERE, and our DNS page for that domain has no effect. That mismatch is the most common reason a record "does not work".

DNS changes are not instant: allow up to a few hours for the world to notice, though most changes show within minutes.
TXT,
                    ],
                    [
                        'EPP codes, registrar lock and ID protection',
                        <<<'TXT'
Three switches on the domain's page, all about ownership:

- Registrar lock - blocks transfers away while enabled. Keep it on; turn it off only when you are deliberately transferring out.
- EPP code - the password a transfer needs. Get EPP Code shows yours when you want to move the domain elsewhere. Treat it like a password: whoever has it can start a transfer.
- ID protection - hides your personal details from the public WHOIS directory, which otherwise lists the owner of every domain.

If you are leaving: unlock first, then request the EPP code, and hand it only to the registrar you are moving to.
TXT,
                    ],
                ],
            ],
            'Billing' => [
                'sort' => 5,
                'description' => 'Invoices, paying by bank transfer, account credit and renewals.',
                'articles' => [
                    [
                        'How invoices work',
                        <<<'TXT'
Every product renews on a cycle you chose at order time, and an invoice is issued ahead of each renewal - you will find it under Invoices and in your email.

An invoice shows its issue date, due date and lines. Pay it with any of the payment methods offered on its page; once payment is confirmed the invoice flips to Paid, and a PDF of it can be downloaded any time - the PDF carries the company details and the tax lines, suitable for your bookkeeping.

An unpaid invoice past its due date can suspend the service it belongs to, so if something about an invoice looks wrong, open a ticket before the due date rather than letting it slide.
TXT,
                    ],
                    [
                        'Paying by bank transfer',
                        <<<'TXT'
Bank transfers are confirmed by a person, so they are not instant. The flow:

1. Open the invoice and choose bank transfer. The page shows the account details - use your INVOICE NUMBER as the payment reference, it is how your money finds your invoice.
2. Send the transfer at your bank.
3. Tell us on the invoice page that you paid - attach the receipt if you have one.

Your note lands in a review queue. When the transfer is confirmed, the invoice is marked paid and anything waiting on it proceeds. If the amount differs from the invoice - a partial payment - the invoice stays open for the remainder.
TXT,
                    ],
                    [
                        'Account credit',
                        <<<'TXT'
Under Add Funds you can load money onto the account before you owe it - a few preset amounts, or a custom one.

Credit is applied to invoices as they are issued, which is useful in two situations: renewals you never want to bounce (the invoice pays itself from credit the moment it is issued), and accounting flows where one larger payment is easier to process than many small ones.

Your current balance shows on the same page and on invoices as they consume it.
TXT,
                    ],
                    [
                        'Cancelling a service',
                        <<<'TXT'
Open the service and request cancellation. Two flavours:

- End of billing period - the service runs until the day you already paid for, then closes. The usual choice.
- Immediate - the service closes right away.

Cancelling stops future invoices for that service. It does not delete your invoices or your account, and other services are untouched.

Take your data first. A closed service's files and databases are removed - download what you need under Files and Databases before the end date, not after.
TXT,
                    ],
                ],
            ],
            'Support' => [
                'sort' => 6,
                'description' => 'Tickets, announcements and how to report a problem so it gets fixed fast.',
                'articles' => [
                    [
                        'Opening a support ticket',
                        <<<'TXT'
Tickets are the channel with your account attached - the person answering sees your services and history, which no email address can offer.

Under Tickets, press to open one: pick the department, a subject, the priority, and - this matters - the related service, so nobody has to ask which of your products you mean. Attachments are welcome: a screenshot of an error beats a description of one.

What makes a ticket fast to solve: what you did, what you expected, what happened instead, and when. "The site is down" takes three round-trips; "example.com shows a 500 error since about 14:00, right after I updated a plugin" is often solved in one.
TXT,
                    ],
                    [
                        'Announcements and staying informed',
                        <<<'TXT'
The Announcements page carries news that concerns customers - maintenance windows, new features, pricing changes. Worth a glance when something seems off before opening a ticket: a known maintenance window answers the question faster than we can.

Your email address on the account is where invoices, ticket replies and important notices go. Keep it current under Account - a bounced address means missed renewal notices, and missed renewal notices are how domains expire by surprise.
TXT,
                    ],
                ],
            ],
        ];
    }

    public function up(): void
    {
        $now = now();

        foreach ($this->categories() as $name => $cat) {
            $categoryId = DB::table('kb_categories')->where('name', $name)->value('id');
            if (! $categoryId) {
                $categoryId = DB::table('kb_categories')->insertGetId([
                    'name' => $name,
                    'description' => $cat['description'],
                    'hidden' => false,
                    'sort_order' => $cat['sort'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            $sort = 1;
            foreach ($cat['articles'] as [$title, $body]) {
                // By title, so an operator's edits to a seeded article survive
                // every later deploy - the seed only fills gaps.
                $exists = DB::table('kb_articles')->where('title', $title)->exists();
                if (! $exists) {
                    DB::table('kb_articles')->insert([
                        'category_id' => $categoryId,
                        'title' => $title,
                        'article' => $body,
                        'private' => false,
                        'sort_order' => $sort,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
                $sort++;
            }
        }

        // Apps & Docker slots between Hosting and Domains in the new order.
        DB::table('kb_categories')->where('name', 'Apps & Docker')->update(['sort_order' => 3]);
    }

    public function down(): void
    {
        foreach ($this->categories() as $name => $cat) {
            $titles = array_column($cat['articles'], 0);
            DB::table('kb_articles')->whereIn('title', $titles)->delete();
            DB::table('kb_categories')->where('name', $name)
                ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('kb_articles')
                    ->whereColumn('kb_articles.category_id', 'kb_categories.id'))
                ->delete();
        }
        DB::table('kb_categories')->where('name', 'Apps & Docker')->update(['sort_order' => 1]);
    }
};
