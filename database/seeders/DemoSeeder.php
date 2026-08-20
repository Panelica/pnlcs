<?php

namespace Database\Seeders;

use App\Models\Admin;
use App\Models\AdminRole;
use App\Models\Announcement;
use App\Models\BannedIp;
use App\Models\Client;
use App\Models\ClientGroup;
use App\Models\Currency;
use App\Models\Domain;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\KbArticle;
use App\Models\KbCategory;
use App\Models\Order;
use App\Models\Pricing;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\Promotion;
use App\Models\Server;
use App\Models\ServerGroup;
use App\Models\Service;
use App\Models\Setting;
use App\Models\TaxRule;
use App\Models\Ticket;
use App\Models\TicketDepartment;
use App\Models\TicketReply;
use App\Models\TicketStatus;
use App\Models\TodoItem;
use App\Models\Transaction;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Currencies
        $usd = Currency::firstOrCreate(['code' => 'USD'], [
            'prefix' => '$', 'suffix' => '', 'rate' => 1.00000, 'is_default' => true,
        ]);
        Currency::firstOrCreate(['code' => 'EUR'], [
            'prefix' => '€', 'suffix' => '', 'rate' => 0.92000, 'is_default' => false,
        ]);
        Currency::firstOrCreate(['code' => 'GBP'], [
            'prefix' => '£', 'suffix' => '', 'rate' => 0.79000, 'is_default' => false,
        ]);
        Currency::firstOrCreate(['code' => 'TRY'], [
            'prefix' => '₺', 'suffix' => '', 'rate' => 32.50000, 'is_default' => false,
        ]);

        // 2. Admin roles
        $fullRole = AdminRole::firstOrCreate(
            ['name' => 'Full Administrator'],
            ['description' => 'Full access to all areas', 'is_full_admin' => true]
        );
        AdminRole::firstOrCreate(
            ['name' => 'Support Agent'],
            ['description' => 'Support tickets only', 'is_full_admin' => false,
             'permissions' => ['list_clients', 'view_clients', 'list_tickets', 'view_tickets', 'reply_tickets', 'list_services', 'view_services', 'list_orders', 'view_orders']]
        );
        AdminRole::firstOrCreate(
            ['name' => 'Billing Manager'],
            ['description' => 'Billing and orders', 'is_full_admin' => false,
             'permissions' => ['list_clients', 'view_clients', 'list_invoices', 'view_invoices', 'create_invoices', 'manage_invoices', 'list_orders', 'view_orders', 'manage_orders', 'list_quotes', 'manage_quotes']]
        );

        // 3. Admin users
        Admin::firstOrCreate(['username' => 'admin'], [
            'role_id' => $fullRole->id,
            'email' => 'admin@pnlcs.com',
            'password' => 'admin123',
            'first_name' => 'System',
            'last_name' => 'Administrator',
        ]);

        // 4. Settings
        $settings = [
            ['setting' => 'CompanyName', 'value' => 'PNLCS Hosting', 'group' => 'general'],
            ['setting' => 'Domain', 'value' => 'hosting.panelica.com', 'group' => 'general'],
            ['setting' => 'Logo', 'value' => '', 'group' => 'general'],
            ['setting' => 'DefaultLanguage', 'value' => 'en', 'group' => 'general'],
            ['setting' => 'DateFormat', 'value' => 'd/m/Y', 'group' => 'general'],
        ];
        foreach ($settings as $s) {
            Setting::firstOrCreate(['setting' => $s['setting']], $s);
        }

        // 5. Tax rules
        TaxRule::firstOrCreate(['name' => 'US Sales Tax', 'country' => 'US'], [
            'tax_rate' => 10.00, 'level' => 1, 'state' => '',
        ]);
        TaxRule::firstOrCreate(['name' => 'EU VAT (Germany)', 'country' => 'DE'], [
            'tax_rate' => 19.00, 'level' => 1, 'state' => '',
        ]);
        TaxRule::firstOrCreate(['name' => 'UK VAT', 'country' => 'GB'], [
            'tax_rate' => 20.00, 'level' => 1, 'state' => '',
        ]);

        // 6. Ticket departments
        $deptGeneral = TicketDepartment::firstOrCreate(['name' => 'General'], [
            'description' => 'General inquiries', 'email' => 'support@pnlcs.com',
        ]);
        $deptTech = TicketDepartment::firstOrCreate(['name' => 'Technical Support'], [
            'description' => 'Technical issues and server problems', 'email' => 'tech@pnlcs.com',
        ]);
        $deptBilling = TicketDepartment::firstOrCreate(['name' => 'Billing'], [
            'description' => 'Billing and payment inquiries', 'email' => 'billing@pnlcs.com',
        ]);
        $deptSales = TicketDepartment::firstOrCreate(['name' => 'Sales'], [
            'description' => 'Pre-sales questions', 'email' => 'sales@pnlcs.com',
        ]);

        // 7. Ticket statuses
        TicketStatus::firstOrCreate(['title' => 'Open'], ['color' => '#22c55e', 'sort_order' => 1, 'show_active' => true]);
        TicketStatus::firstOrCreate(['title' => 'Answered'], ['color' => '#3b82f6', 'sort_order' => 2, 'show_active' => true]);
        TicketStatus::firstOrCreate(['title' => 'Customer-Reply'], ['color' => '#f59e0b', 'sort_order' => 3, 'show_active' => true]);
        TicketStatus::firstOrCreate(['title' => 'On Hold'], ['color' => '#8b5cf6', 'sort_order' => 4, 'show_active' => true]);
        TicketStatus::firstOrCreate(['title' => 'In Progress'], ['color' => '#06b6d4', 'sort_order' => 5, 'show_active' => true]);
        TicketStatus::firstOrCreate(['title' => 'Closed'], ['color' => '#6b7280', 'sort_order' => 6, 'show_active' => false]);

        // 8. Client groups
        $groupVip = ClientGroup::firstOrCreate(['name' => 'VIP Clients'], [
            'color' => '#eab308', 'discount_percent' => 15.00, 'suspend_exempt' => true, 'terminate_exempt' => true,
        ]);
        $groupPartner = ClientGroup::firstOrCreate(['name' => 'Partners'], [
            'color' => '#3b82f6', 'discount_percent' => 10.00, 'suspend_exempt' => false, 'terminate_exempt' => false,
        ]);
        ClientGroup::firstOrCreate(['name' => 'Resellers'], [
            'color' => '#8b5cf6', 'discount_percent' => 20.00, 'suspend_exempt' => true, 'terminate_exempt' => false,
        ]);

        // 9. Servers
        $server1 = Server::firstOrCreate(['hostname' => 'web1.pnlcs.com'], [
            'name' => 'Web Server 1',
            'ip_address' => '10.0.1.10',
            'max_accounts' => 500,
            'type' => 'panelica',
            'username' => 'root',
            'port' => 8443,
            'active' => true,
            'disabled' => false,
            'nameserver1' => 'ns1.pnlcs.com',
            'nameserver2' => 'ns2.pnlcs.com',
        ]);
        $server2 = Server::firstOrCreate(['hostname' => 'web2.pnlcs.com'], [
            'name' => 'Web Server 2',
            'ip_address' => '10.0.1.11',
            'max_accounts' => 500,
            'type' => 'panelica',
            'username' => 'root',
            'port' => 8443,
            'active' => true,
            'disabled' => false,
            'nameserver1' => 'ns1.pnlcs.com',
            'nameserver2' => 'ns2.pnlcs.com',
        ]);

        // 10. Server groups
        $serverGroup = ServerGroup::firstOrCreate(['name' => 'Production Servers'], ['fill_type' => 'least']);
        if ($serverGroup->wasRecentlyCreated) {
            $serverGroup->servers()->attach([$server1->id, $server2->id]);
        }

        // 11. Product groups
        $grpShared = ProductGroup::firstOrCreate(['name' => 'Shared Hosting'], ['slug' => 'shared-hosting', 'sort_order' => 1]);
        $grpVps = ProductGroup::firstOrCreate(['name' => 'VPS Hosting'], ['slug' => 'vps-hosting', 'sort_order' => 2]);
        $grpDedicated = ProductGroup::firstOrCreate(['name' => 'Dedicated Servers'], ['slug' => 'dedicated-servers', 'sort_order' => 3]);
        $grpReseller = ProductGroup::firstOrCreate(['name' => 'Reseller Hosting'], ['slug' => 'reseller-hosting', 'sort_order' => 4]);

        // 12. Products
        $products = [
            [
                'name' => 'Starter Plan',
                'slug' => 'starter-plan',
                'description' => '10GB SSD, 100GB Bandwidth, 5 Email Accounts, 1 Domain',
                'group_id' => $grpShared->id,
                'type' => 'hostingaccount',
                'pay_type' => 'recurring',
                'auto_setup' => 'order',
                'server_group_id' => $serverGroup->id,
                'monthly' => 4.99,
                'quarterly' => 13.99,
                'annually' => 47.99,
            ],
            [
                'name' => 'Professional Plan',
                'slug' => 'professional-plan',
                'description' => '50GB SSD, 500GB Bandwidth, 25 Email Accounts, 5 Domains',
                'group_id' => $grpShared->id,
                'type' => 'hostingaccount',
                'pay_type' => 'recurring',
                'auto_setup' => 'order',
                'server_group_id' => $serverGroup->id,
                'monthly' => 9.99,
                'quarterly' => 27.99,
                'annually' => 95.99,
            ],
            [
                'name' => 'Enterprise Plan',
                'slug' => 'enterprise-plan',
                'description' => '100GB SSD, Unlimited Bandwidth, Unlimited Emails, Unlimited Domains',
                'group_id' => $grpShared->id,
                'type' => 'hostingaccount',
                'pay_type' => 'recurring',
                'auto_setup' => 'order',
                'server_group_id' => $serverGroup->id,
                'monthly' => 19.99,
                'quarterly' => 55.99,
                'annually' => 191.99,
            ],
            [
                'name' => 'VPS Basic',
                'slug' => 'vps-basic',
                'description' => '2 vCPU, 4GB RAM, 80GB NVMe SSD, 2TB Bandwidth',
                'group_id' => $grpVps->id,
                'type' => 'hostingaccount',
                'pay_type' => 'recurring',
                'auto_setup' => 'order',
                'server_group_id' => $serverGroup->id,
                'monthly' => 24.99,
                'quarterly' => 69.99,
                'annually' => 239.99,
            ],
            [
                'name' => 'VPS Professional',
                'slug' => 'vps-professional',
                'description' => '4 vCPU, 8GB RAM, 160GB NVMe SSD, 4TB Bandwidth',
                'group_id' => $grpVps->id,
                'type' => 'hostingaccount',
                'pay_type' => 'recurring',
                'auto_setup' => 'order',
                'server_group_id' => $serverGroup->id,
                'monthly' => 49.99,
                'quarterly' => 139.99,
                'annually' => 479.99,
            ],
            [
                'name' => 'Dedicated Server',
                'slug' => 'dedicated-server',
                'description' => '8 Core Intel Xeon, 32GB RAM, 2x1TB SSD RAID-1, 10TB Bandwidth',
                'group_id' => $grpDedicated->id,
                'type' => 'hostingaccount',
                'pay_type' => 'recurring',
                'auto_setup' => 'order',
                'server_group_id' => $serverGroup->id,
                'monthly' => 149.99,
                'quarterly' => 419.99,
                'annually' => 1499.99,
            ],
            [
                'name' => 'Reseller Bronze',
                'slug' => 'reseller-bronze',
                'description' => '50GB SSD, 500GB Bandwidth, 20 cPanel Accounts',
                'group_id' => $grpReseller->id,
                'type' => 'hostingaccount',
                'pay_type' => 'recurring',
                'auto_setup' => 'order',
                'server_group_id' => $serverGroup->id,
                'monthly' => 29.99,
                'quarterly' => 83.99,
                'annually' => 287.99,
            ],
        ];

        $createdProducts = [];
        foreach ($products as $pData) {
            $prices = ['monthly' => $pData['monthly'], 'quarterly' => $pData['quarterly'], 'annually' => $pData['annually']];
            unset($pData['monthly'], $pData['quarterly'], $pData['annually']);

            $product = Product::firstOrCreate(['slug' => $pData['slug']], $pData);
            $createdProducts[] = $product;

            // Create pricing for USD
            Pricing::firstOrCreate(
                ['type' => 'product', 'rel_id' => $product->id, 'currency_id' => $usd->id],
                [
                    'monthly' => $prices['monthly'],
                    'quarterly' => $prices['quarterly'],
                    'annually' => $prices['annually'],
                    'monthly_setup' => 0,
                    'quarterly_setup' => 0,
                    'annually_setup' => 0,
                ]
            );
        }

        // 13. Promotions
        Promotion::firstOrCreate(['code' => 'SAVE10'], [
            'type' => 'percentage',
            'recurring' => false,
            'value' => 10.00,
            'cycles' => 1,
            'max_uses' => 100,
            'uses' => 12,
            'new_signups_only' => true,
            'existing_client' => false,
            'start_date' => now()->subDays(30),
            'expiration_date' => now()->addDays(60),
            'notes' => '10% off first month for new customers',
        ]);
        Promotion::firstOrCreate(['code' => 'SUMMER20'], [
            'type' => 'percentage',
            'recurring' => false,
            'value' => 20.00,
            'cycles' => 1,
            'max_uses' => 50,
            'uses' => 7,
            'new_signups_only' => false,
            'existing_client' => false,
            'start_date' => now()->subDays(15),
            'expiration_date' => now()->addDays(45),
            'notes' => 'Summer sale — 20% off any plan',
        ]);
        Promotion::firstOrCreate(['code' => 'WELCOME'], [
            'type' => 'fixed_amount',
            'recurring' => false,
            'value' => 5.00,
            'cycles' => 1,
            'max_uses' => 0,
            'uses' => 34,
            'new_signups_only' => true,
            'existing_client' => false,
            'lifetime_promo' => false,
            'notes' => '$5 off welcome discount for new signups',
        ]);

        // 14. Announcements
        Announcement::firstOrCreate(['title' => 'Welcome to PNLCS Hosting'], [
            'announcement' => '<p>Welcome to PNLCS Hosting — the fastest growing hosting provider powered by Panelica. We offer shared, VPS, and dedicated hosting solutions with 99.9% uptime guarantee.</p><p>Sign up today and get started in minutes!</p>',
            'published' => true,
        ]);
        Announcement::firstOrCreate(['title' => 'Scheduled Maintenance - March 15, 2026'], [
            'announcement' => '<p>We will be performing scheduled maintenance on our network infrastructure on March 15, 2026 between 02:00-04:00 UTC. Expect brief service interruptions during this window.</p><p>We apologize for any inconvenience.</p>',
            'published' => true,
        ]);
        Announcement::firstOrCreate(['title' => 'New: VPS Hosting Plans Available'], [
            'announcement' => '<p>We are excited to announce the launch of our new VPS hosting plans! Starting from just $24.99/month, get dedicated resources with NVMe storage and guaranteed bandwidth.</p>',
            'published' => true,
        ]);

        // 15. Knowledge Base
        $kbGeneral = KbCategory::firstOrCreate(['name' => 'Getting Started'], [
            'description' => 'Guides for new customers', 'hidden' => false, 'sort_order' => 1,
        ]);
        $kbTech = KbCategory::firstOrCreate(['name' => 'Technical Support'], [
            'description' => 'Technical troubleshooting articles', 'hidden' => false, 'sort_order' => 2,
        ]);
        $kbBilling = KbCategory::firstOrCreate(['name' => 'Billing & Payments'], [
            'description' => 'Billing and payment guides', 'hidden' => false, 'sort_order' => 3,
        ]);

        KbArticle::firstOrCreate(['title' => 'How to Get Started with Your Hosting Account'], [
            'category_id' => $kbGeneral->id,
            'article' => '<p>Welcome to PNLCS Hosting! This guide will walk you through the first steps after signing up.</p><h3>Step 1: Access Your Control Panel</h3><p>Log in to your account at <strong>hosting.panelica.com</strong> using your email and password.</p><h3>Step 2: Upload Your Website</h3><p>Use the File Manager or FTP to upload your website files to the <code>public_html</code> directory.</p><h3>Step 3: Point Your Domain</h3><p>Update your domain DNS to point to our nameservers: ns1.pnlcs.com and ns2.pnlcs.com.</p>',
            'views' => 248, 'useful' => 45, 'votes' => 50, 'private' => false, 'sort_order' => 1,
        ]);
        KbArticle::firstOrCreate(['title' => 'How to Create an Email Account'], [
            'category_id' => $kbGeneral->id,
            'article' => '<p>Creating email accounts is easy with PNLCS Hosting.</p><h3>Steps</h3><ol><li>Log in to your control panel</li><li>Navigate to Email Accounts</li><li>Click "Create Email Account"</li><li>Enter the desired email address and password</li><li>Click Save</li></ol>',
            'views' => 182, 'useful' => 38, 'votes' => 42, 'private' => false, 'sort_order' => 2,
        ]);
        KbArticle::firstOrCreate(['title' => 'How to Install WordPress'], [
            'category_id' => $kbTech->id,
            'article' => '<p>WordPress can be installed with one click from your control panel.</p><h3>One-Click Install</h3><ol><li>Navigate to Software in your panel</li><li>Click WordPress</li><li>Select your domain and installation path</li><li>Enter admin details and click Install</li></ol><p>Your WordPress site will be ready in under 2 minutes.</p>',
            'views' => 421, 'useful' => 89, 'votes' => 96, 'private' => false, 'sort_order' => 1,
        ]);
        KbArticle::firstOrCreate(['title' => 'Troubleshooting 500 Internal Server Errors'], [
            'category_id' => $kbTech->id,
            'article' => '<p>A 500 Internal Server Error usually indicates a problem with your application code or configuration.</p><h3>Common Causes</h3><ul><li>Incorrect file permissions (should be 755 for directories, 644 for files)</li><li>Syntax errors in .htaccess</li><li>PHP errors in your application</li></ul><h3>How to Fix</h3><p>Check your error logs in the control panel under Logs for specific error messages.</p>',
            'views' => 315, 'useful' => 67, 'votes' => 78, 'private' => false, 'sort_order' => 2,
        ]);
        KbArticle::firstOrCreate(['title' => 'Payment Methods Accepted'], [
            'category_id' => $kbBilling->id,
            'article' => '<p>PNLCS Hosting accepts the following payment methods:</p><ul><li>Credit/Debit Cards (Visa, Mastercard, American Express)</li><li>PayPal</li><li>Bank Transfer</li><li>Cryptocurrency (Bitcoin, Ethereum)</li></ul><p>Payments are processed securely. All prices are shown in USD unless otherwise specified.</p>',
            'views' => 156, 'useful' => 29, 'votes' => 33, 'private' => false, 'sort_order' => 1,
        ]);

        // 16. Todo items
        TodoItem::firstOrCreate(['title' => 'Review pending orders'], [
            'description' => 'Check and approve all pending orders in the orders queue',
            'status' => 'New',
            'due_date' => now()->addDays(1)->toDateString(),
            'admin' => 'admin',
        ]);
        TodoItem::firstOrCreate(['title' => 'Update server capacity limits'], [
            'description' => 'Web Server 2 is reaching 80% capacity - increase limits or add new server',
            'status' => 'In Progress',
            'due_date' => now()->addDays(3)->toDateString(),
            'admin' => 'admin',
        ]);
        TodoItem::firstOrCreate(['title' => 'Send renewal reminder emails'], [
            'description' => 'Send renewal reminders to clients with invoices due in the next 7 days',
            'status' => 'New',
            'due_date' => now()->addDays(2)->toDateString(),
            'admin' => 'admin',
        ]);
        TodoItem::firstOrCreate(['title' => 'Configure payment gateway SSL certificates'], [
            'description' => 'Stripe SSL certificates need renewal before end of month',
            'status' => 'New',
            'due_date' => now()->addDays(14)->toDateString(),
            'admin' => 'admin',
        ]);
        TodoItem::firstOrCreate(['title' => 'Backup verification'], [
            'description' => 'Verify last week database and file backups are valid and restorable',
            'status' => 'Completed',
            'due_date' => now()->subDays(2)->toDateString(),
            'admin' => 'admin',
        ]);

        // 17. Banned IPs
        BannedIp::firstOrCreate(['ip' => '192.0.2.100'], ['reason' => 'Repeated fraudulent order attempts']);
        BannedIp::firstOrCreate(['ip' => '203.0.113.45'], ['reason' => 'Spam submissions via contact form']);
        BannedIp::firstOrCreate(['ip' => '198.51.100.22'], ['reason' => 'Brute force login attempts']);

        // 18. Demo clients (15 realistic ones)
        $clientData = [
            ['first_name' => 'James', 'last_name' => 'Anderson', 'email' => 'james.anderson@techcorp.io', 'company_name' => 'TechCorp Solutions', 'country' => 'US', 'city' => 'New York', 'state' => 'NY', 'postcode' => '10001', 'group_id' => $groupVip->id],
            ['first_name' => 'Emma', 'last_name' => 'Wilson', 'email' => 'emma.wilson@designstudio.com', 'company_name' => 'Wilson Design Studio', 'country' => 'GB', 'city' => 'London', 'state' => 'ENG', 'postcode' => 'EC1A 1BB', 'group_id' => null],
            ['first_name' => 'Michael', 'last_name' => 'Johnson', 'email' => 'mjohnson@cloudventures.net', 'company_name' => 'Cloud Ventures LLC', 'country' => 'US', 'city' => 'San Francisco', 'state' => 'CA', 'postcode' => '94102', 'group_id' => $groupPartner->id],
            ['first_name' => 'Sofia', 'last_name' => 'Martinez', 'email' => 'sofia.m@webagency.es', 'company_name' => 'WebAgency Spain', 'country' => 'ES', 'city' => 'Madrid', 'state' => 'MAD', 'postcode' => '28001', 'group_id' => null],
            ['first_name' => 'Liam', 'last_name' => 'Thompson', 'email' => 'liam@digitalwave.ca', 'company_name' => 'Digital Wave Inc', 'country' => 'CA', 'city' => 'Toronto', 'state' => 'ON', 'postcode' => 'M5V 3A8', 'group_id' => $groupPartner->id],
            ['first_name' => 'Olivia', 'last_name' => 'Brown', 'email' => 'olivia.brown@freelancer.dev', 'company_name' => '', 'country' => 'AU', 'city' => 'Sydney', 'state' => 'NSW', 'postcode' => '2000', 'group_id' => null],
            ['first_name' => 'Noah', 'last_name' => 'Davis', 'email' => 'noah.davis@startuplab.io', 'company_name' => 'StartupLab', 'country' => 'US', 'city' => 'Austin', 'state' => 'TX', 'postcode' => '78701', 'group_id' => null],
            ['first_name' => 'Ava', 'last_name' => 'Garcia', 'email' => 'ava.garcia@ecommerceplus.com', 'company_name' => 'EcommercePlus', 'country' => 'MX', 'city' => 'Mexico City', 'state' => 'CDMX', 'postcode' => '06600', 'group_id' => null],
            ['first_name' => 'William', 'last_name' => 'Miller', 'email' => 'w.miller@mediagroup.de', 'company_name' => 'Miller Media Group', 'country' => 'DE', 'city' => 'Berlin', 'state' => 'BE', 'postcode' => '10115', 'group_id' => $groupVip->id],
            ['first_name' => 'Isabella', 'last_name' => 'Taylor', 'email' => 'itaylor@blognetwork.co', 'company_name' => 'Blog Network Co', 'country' => 'US', 'city' => 'Chicago', 'state' => 'IL', 'postcode' => '60601', 'group_id' => null],
            ['first_name' => 'Ethan', 'last_name' => 'Lee', 'email' => 'ethan.lee@devshop.kr', 'company_name' => 'DevShop Korea', 'country' => 'KR', 'city' => 'Seoul', 'state' => '', 'postcode' => '04524', 'group_id' => null],
            ['first_name' => 'Charlotte', 'last_name' => 'White', 'email' => 'charlotte@creativeminds.fr', 'company_name' => 'Creative Minds', 'country' => 'FR', 'city' => 'Paris', 'state' => 'IDF', 'postcode' => '75001', 'group_id' => null],
            ['first_name' => 'Alexander', 'last_name' => 'Harris', 'email' => 'aharris@enterpriseit.com', 'company_name' => 'Enterprise IT Solutions', 'country' => 'US', 'city' => 'Seattle', 'state' => 'WA', 'postcode' => '98101', 'group_id' => $groupVip->id],
            ['first_name' => 'Mia', 'last_name' => 'Clark', 'email' => 'mia.clark@onlineshop.nl', 'company_name' => 'Online Shop NL', 'country' => 'NL', 'city' => 'Amsterdam', 'state' => 'NH', 'postcode' => '1012 AB', 'group_id' => null],
            ['first_name' => 'Lucas', 'last_name' => 'Rodriguez', 'email' => 'lucas@webmaster.br', 'company_name' => 'WebMaster Brasil', 'country' => 'BR', 'city' => 'São Paulo', 'state' => 'SP', 'postcode' => '01310-100', 'group_id' => null],
        ];

        $clients = [];
        foreach ($clientData as $cd) {
            $client = Client::firstOrCreate(['email' => $cd['email']], array_merge($cd, [
                'uuid' => (string) Str::uuid(),
                'status' => 'active',
                'credit' => 0,
                'language' => 'en',
                'currency_id' => $usd->id,
                'phone_number' => '+1 555 ' . rand(100, 999) . '-' . rand(1000, 9999),
                'address1' => rand(10, 999) . ' ' . ['Main St', 'Oak Ave', 'Cedar Blvd', 'Elm Rd', 'Park Lane'][rand(0,4)],
            ]));
            $clients[] = $client;
        }

        // 19. Products array reference
        $productList = Product::all();
        $sharedProducts = $productList->where('group_id', $grpShared->id)->values();
        $vpsProducts = $productList->where('group_id', $grpVps->id)->values();
        $allProducts = $productList->values();

        // 20. Orders + Services + Invoices
        $billingCycles = ['Monthly', 'Quarterly', 'Annually'];
        $paymentMethods = ['banktransfer', 'paypal', 'stripe'];
        $gateways = ['Stripe', 'PayPal', 'Bank Transfer'];

        $invoiceNum = Invoice::max('id') ?? 0;
        $orderNum = Order::max('id') ?? 0;

        foreach ($clients as $idx => $client) {
            // Each client gets 1-3 services
            $numServices = rand(1, 3);
            for ($s = 0; $s < $numServices; $s++) {
                $product = $allProducts->random();
                $billing = $billingCycles[array_rand($billingCycles)];
                $payMethod = $paymentMethods[array_rand($paymentMethods)];

                // Determine amount from product pricing
                $pricing = Pricing::where('type', 'product')
                    ->where('rel_id', $product->id)
                    ->where('currency_id', $usd->id)
                    ->first();

                $amount = 9.99; // fallback
                if ($pricing) {
                    $amount = match($billing) {
                        'Monthly' => (float)$pricing->monthly,
                        'Quarterly' => (float)$pricing->quarterly,
                        'Annually' => (float)$pricing->annually,
                        default => (float)$pricing->monthly,
                    };
                }

                $registrationDate = now()->subDays(rand(10, 365));
                $nextDueDate = $registrationDate->copy()->addDays(match($billing) {
                    'Monthly' => 30,
                    'Quarterly' => 90,
                    'Annually' => 365,
                    default => 30,
                });

                $orderNum++;
                $order = Order::create([
                    'order_num' => str_pad($orderNum, 8, '0', STR_PAD_LEFT),
                    'client_id' => $client->id,
                    'date' => $registrationDate->toDateString(),
                    'amount' => $amount,
                    'payment_method' => $payMethod,
                    'status' => 'Active',
                    'ip_address' => rand(1,254) . '.' . rand(1,254) . '.' . rand(1,254) . '.' . rand(1,254),
                ]);

                $domains = ['example.com', 'mysite.net', 'webproject.org', 'business.io', 'store.co', 'app.dev', 'portfolio.me'];
                $domainName = $client->last_name ? strtolower($client->last_name) . rand(1,99) . '.' . ['com','net','org','io'][rand(0,3)] : $domains[array_rand($domains)];

                $service = Service::create([
                    'client_id' => $client->id,
                    'order_id' => $order->id,
                    'product_id' => $product->id,
                    'server_id' => rand(0,1) ? $server1->id : $server2->id,
                    'domain' => $domainName,
                    'payment_method' => $payMethod,
                    'qty' => 1,
                    'first_payment_amount' => $amount,
                    'amount' => $amount,
                    'billing_cycle' => $billing,
                    'registration_date' => $registrationDate->toDateString(),
                    'next_due_date' => $nextDueDate->toDateString(),
                    'status' => rand(0, 9) < 8 ? 'Active' : (rand(0,1) ? 'Suspended' : 'Pending'),
                    'username' => strtolower($client->first_name) . rand(100, 999),
                    'disk_usage' => rand(100, 8000),
                    'disk_limit' => 10240,
                    'bw_usage' => rand(500, 50000),
                    'bw_limit' => 102400,
                ]);

                // Create invoice for this service
                $invoiceNum++;
                $invoiceDate = $registrationDate->copy();
                $dueDate = $invoiceDate->copy()->addDays(7);
                $isPaid = rand(0, 9) < 7; // 70% paid
                $isOverdue = !$isPaid && $dueDate->isPast();

                $status = $isPaid ? 'paid' : ($isOverdue ? 'overdue' : 'unpaid');
                $subtotal = $amount;
                $taxAmount = round($subtotal * 0.10, 2);
                $total = $subtotal + $taxAmount;

                $invoice = Invoice::create([
                    'client_id' => $client->id,
                    'invoice_num' => 'INV-' . str_pad($invoiceNum, 6, '0', STR_PAD_LEFT),
                    'date' => $invoiceDate->toDateString(),
                    'due_date' => $dueDate->toDateString(),
                    'date_paid' => $isPaid ? $invoiceDate->copy()->addDays(rand(1, 6)) : null,
                    'subtotal' => $subtotal,
                    'credit' => 0,
                    'tax' => $taxAmount,
                    'tax2' => 0,
                    'total' => $total,
                    'tax_rate' => 10.00000,
                    'tax_rate2' => 0,
                    'status' => $status,
                    'payment_method' => $payMethod,
                    'notes' => null,
                ]);

                // Invoice item
                InvoiceItem::create([
                    'invoice_id' => $invoice->id,
                    'client_id' => $client->id,
                    'type' => 'Hosting',
                    'rel_id' => $service->id,
                    'description' => $product->name . ' - ' . $billing . ' (' . $domainName . ')',
                    'amount' => $subtotal,
                    'taxed' => true,
                    'due_date' => $dueDate->toDateString(),
                ]);

                // Update order with invoice_id
                $order->update(['invoice_id' => $invoice->id]);

                // Transaction for paid invoices
                if ($isPaid) {
                    $gwIdx = array_search($payMethod, $paymentMethods);
                    Transaction::create([
                        'client_id' => $client->id,
                        'currency_id' => $usd->id,
                        'gateway' => $gateways[$gwIdx !== false ? $gwIdx : 0],
                        'date' => $invoice->date_paid ? $invoice->date_paid->toDateString() : $invoiceDate->toDateString(),
                        'description' => 'Payment for ' . $invoice->invoice_num,
                        'amount_in' => $total,
                        'fees' => round($total * 0.029 + 0.30, 2),
                        'amount_out' => 0,
                        'rate' => 1.00000,
                        'transaction_id' => strtoupper(Str::random(10)),
                        'invoice_id' => $invoice->id,
                    ]);
                }
            }

            // Domain registrations for some clients
            if ($idx % 3 === 0 || $idx < 5) {
                $tlds = ['com', 'net', 'org', 'io', 'co'];
                $tld = $tlds[array_rand($tlds)];
                $domainBase = preg_replace('/[^a-z0-9]/', '', strtolower($client->company_name ?: $client->last_name));
                $domainBase = $domainBase ?: 'domain' . $idx;
                Domain::firstOrCreate(['domain' => $domainBase . '.' . $tld], [
                    'client_id' => $client->id,
                    'type' => 'Register',
                    'registrar' => 'enom',
                    'registration_period' => 1,
                    'registration_date' => now()->subDays(rand(30, 500))->toDateString(),
                    'expiry_date' => now()->addDays(rand(10, 365))->format('Y-m'),
                    'next_due_date' => now()->addDays(rand(10, 365))->toDateString(),
                    'status' => rand(0,9) < 8 ? 'Active' : 'Expired',
                    'dns_management' => true,
                    'email_forwarding' => false,
                    'id_protection' => rand(0,1) === 1,
                    'is_premium' => false,
                    'payment_method' => 'banktransfer',
                    'first_payment_amount' => 12.99,
                    'recurring_amount' => 12.99,
                    'nameservers' => json_encode(['ns1.pnlcs.com', 'ns2.pnlcs.com']),
                ]);
            }
        }

        // 21. Tickets
        $ticketTitles = [
            ['title' => 'Cannot access my control panel', 'dept' => $deptTech, 'status' => 'Open', 'priority' => 'High', 'msg' => 'I am unable to log into my hosting control panel since this morning. It shows a 403 Forbidden error.'],
            ['title' => 'Invoice #INV-000012 shows incorrect amount', 'dept' => $deptBilling, 'status' => 'Open', 'priority' => 'Medium', 'msg' => 'The latest invoice shows $49.99 but my plan is $24.99/month. Please check.'],
            ['title' => 'How do I add a subdomain?', 'dept' => $deptGeneral, 'status' => 'Answered', 'priority' => 'Low', 'msg' => 'I need to create a subdomain blog.example.com for my main domain. How do I do this?'],
            ['title' => 'MySQL connection refused - urgent', 'dept' => $deptTech, 'status' => 'In Progress', 'priority' => 'High', 'msg' => 'My application cannot connect to MySQL. Error: Connection refused on port 3306. This is affecting production.'],
            ['title' => 'Request for upgrade to Professional Plan', 'dept' => $deptSales, 'status' => 'Customer-Reply', 'priority' => 'Medium', 'msg' => 'I would like to upgrade my current Starter Plan to Professional. What is the prorated cost?'],
            ['title' => 'Email bouncing for all recipients', 'dept' => $deptTech, 'status' => 'Open', 'priority' => 'High', 'msg' => 'Outbound emails from my domain are bouncing with "550 5.1.1 User unknown". This started 2 hours ago.'],
            ['title' => 'Request invoice copy for Q1 2026', 'dept' => $deptBilling, 'status' => 'Closed', 'priority' => 'Low', 'msg' => 'Please send me all invoice copies for Q1 2026 (January-March) for accounting purposes.'],
            ['title' => 'SSL certificate not renewing', 'dept' => $deptTech, 'status' => 'Answered', 'priority' => 'Medium', 'msg' => 'My SSL certificate expired yesterday and the auto-renewal did not work. Users are seeing security warnings.'],
            ['title' => 'Cancellation request for service', 'dept' => $deptBilling, 'status' => 'On Hold', 'priority' => 'Low', 'msg' => 'I would like to cancel my VPS Basic subscription. Please confirm the cancellation and refund policy.'],
            ['title' => 'Website loading slow since yesterday', 'dept' => $deptTech, 'status' => 'Customer-Reply', 'priority' => 'Medium', 'msg' => 'My website is loading extremely slow (10+ seconds). GTmetrix shows TTFB over 3 seconds. Please investigate.'],
            ['title' => 'Pre-sales question: dedicated server specs', 'dept' => $deptSales, 'status' => 'Answered', 'priority' => 'Low', 'msg' => 'I am evaluating your dedicated server offering. Can you provide detailed hardware specs and SLA?'],
            ['title' => 'FTP credentials not working', 'dept' => $deptTech, 'status' => 'Closed', 'priority' => 'Medium', 'msg' => 'My FTP credentials stopped working after I changed my account password. I am using FileZilla.'],
        ];

        foreach ($ticketTitles as $i => $td) {
            $client = $clients[$i % count($clients)];
            Ticket::firstOrCreate(['title' => $td['title']], [
                'tid' => str_pad(rand(100000, 999999), 6, '0', STR_PAD_LEFT),
                'department_id' => $td['dept']->id,
                'client_id' => $client->id,
                'name' => $client->full_name,
                'email' => $client->email,
                'message' => $td['msg'],
                'status' => $td['status'],
                'priority' => $td['priority'],
                'admin' => 'admin',
                'last_reply' => now()->subHours(rand(1, 72)),
            ]);
        }

        // Add replies to some tickets
        $openTickets = Ticket::whereIn('status', ['Answered', 'Customer-Reply', 'Closed'])->take(5)->get();
        foreach ($openTickets as $ticket) {
            if (TicketReply::where('ticket_id', $ticket->id)->count() === 0) {
                TicketReply::create([
                    'ticket_id' => $ticket->id,
                    'client_id' => null,
                    'message' => 'Thank you for contacting support. We have reviewed your issue and are working on a resolution. Please allow 24-48 hours for a full investigation.',
                    'admin' => 'admin',
                ]);
            }
        }

        $this->command->info('DemoSeeder completed successfully!');
        $this->command->info('Created: ' . count($clients) . ' clients, products, services, invoices, tickets, KB articles, and more.');
    }
}
