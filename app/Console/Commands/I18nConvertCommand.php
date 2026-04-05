<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class I18nConvertCommand extends Command
{
    protected $signature = 'pnlcs:i18n-convert {--seed-only} {--blades-only} {--controllers-only} {--layouts-only} {--emails-only} {--dry-run}';
    protected $description = 'Convert PNLCS hardcoded English to __() i18n calls';

    private int $replacements = 0;
    private int $filesModified = 0;
    private string $basePath;

    public function handle(): int
    {
        $this->basePath = base_path();

        if ($this->option('seed-only')) {
            $this->call('db:seed', ['--class' => 'Database\\Seeders\\TranslationSeeder', '--force' => true]);
            return 0;
        }

        if (!$this->option('blades-only') && !$this->option('controllers-only') && !$this->option('layouts-only') && !$this->option('emails-only')) {
            // Run everything
            $this->info('=== Phase 1: Seeding translations ===');
            $this->call('db:seed', ['--class' => 'Database\\Seeders\\TranslationSeeder', '--force' => true]);

            $this->info('=== Phase 2: Converting layouts ===');
            $this->convertLayouts();

            $this->info('=== Phase 3: Converting blade files ===');
            $this->convertBladeFiles();

            $this->info('=== Phase 4: Converting controllers ===');
            $this->convertControllers();

            $this->info('=== Phase 5: Converting email templates ===');
            $this->convertEmails();
        } else {
            if ($this->option('blades-only')) $this->convertBladeFiles();
            if ($this->option('controllers-only')) $this->convertControllers();
            if ($this->option('layouts-only')) $this->convertLayouts();
            if ($this->option('emails-only')) $this->convertEmails();
        }

        $this->info("Done! {$this->replacements} replacements in {$this->filesModified} files.");
        return 0;
    }

    // ─────────────────────────────────────────────
    // LAYOUT CONVERSION
    // ─────────────────────────────────────────────
    private function convertLayouts(): void
    {
        // Admin layout - update <html> tag
        $this->replaceInFile(
            'resources/views/admin/layouts/app.blade.php',
            [
                // html tag already has app()->getLocale() - good
                // Add dir attribute
                ['<html lang="{{ str_replace("_", "-", app()->getLocale()) }}">', '<html lang="{{ str_replace("_", "-", app()->getLocale()) }}" dir="{{ $textDirection ?? \'ltr\' }}">'],
            ]
        );

        // Client layout - update <html> tag
        $this->replaceInFile(
            'resources/views/client/layouts/app.blade.php',
            [
                ['<html lang="en"', '<html lang="{{ $currentLocale ?? \'en\' }}"'],
            ]
        );
    }

    // ─────────────────────────────────────────────
    // BLADE FILE CONVERSION
    // ─────────────────────────────────────────────
    private function convertBladeFiles(): void
    {
        // Get all blade files
        $files = $this->getBladeFiles();
        $this->info("Found " . count($files) . " blade files to process.");

        foreach ($files as $file) {
            $relativePath = str_replace($this->basePath . '/', '', $file);
            $content = file_get_contents($file);
            $original = $content;

            // Determine group from file path
            $group = $this->getGroupFromPath($relativePath);

            // Apply common replacements
            $content = $this->applyCommonBladeReplacements($content);

            // Apply section title replacements
            $content = $this->applySectionTitleReplacements($content, $group, $relativePath);

            // Apply contextual replacements based on path
            $content = $this->applyContextualReplacements($content, $relativePath);

            if ($content !== $original) {
                if (!$this->option('dry-run')) {
                    file_put_contents($file, $content);
                }
                $this->filesModified++;
                $changes = substr_count($content, "__(") - substr_count($original, "__(");
                $this->info("  Modified: {$relativePath} (+{$changes} keys)");
            }
        }
    }

    private function applyCommonBladeReplacements(string $content): string
    {
        // ── BUTTONS ──
        $buttonMap = [
            'Save Changes' => "{{ __('common.actions.save_changes') }}",
            'Save' => "{{ __('common.actions.save') }}",
            'Cancel' => "{{ __('common.actions.cancel') }}",
            'Delete' => "{{ __('common.actions.delete') }}",
            'Edit' => "{{ __('common.actions.edit') }}",
            'View' => "{{ __('common.actions.view') }}",
            'Create' => "{{ __('common.actions.create') }}",
            'Update' => "{{ __('common.actions.update') }}",
            'Search' => "{{ __('common.actions.search') }}",
            'Filter' => "{{ __('common.actions.filter') }}",
            'Reset' => "{{ __('common.actions.reset') }}",
            'Back' => "{{ __('common.actions.back') }}",
            'Close' => "{{ __('common.actions.close') }}",
            'Submit' => "{{ __('common.actions.submit') }}",
            'Confirm' => "{{ __('common.actions.confirm') }}",
            'Export CSV' => "{{ __('common.actions.export_csv') }}",
            'Add New' => "{{ __('common.actions.add_new') }}",
            'Remove' => "{{ __('common.actions.remove') }}",
            'Enable' => "{{ __('common.actions.enable') }}",
            'Disable' => "{{ __('common.actions.disable') }}",
            'Download' => "{{ __('common.actions.download') }}",
            'Upload' => "{{ __('common.actions.upload') }}",
            'Copy' => "{{ __('common.actions.copy') }}",
            'Refresh' => "{{ __('common.actions.refresh') }}",
            'Send' => "{{ __('common.actions.send') }}",
            'Apply' => "{{ __('common.actions.apply') }}",
            'Clear' => "{{ __('common.actions.clear') }}",
            'Reply' => "{{ __('common.actions.reply') }}",
            'Add' => "{{ __('common.actions.add') }}",
            'Configure' => "{{ __('common.actions.configure') }}",
            'Manage' => "{{ __('common.actions.manage') }}",
            'Activate' => "{{ __('common.actions.activate') }}",
            'Details' => "{{ __('common.actions.details') }}",
            'Login' => "{{ __('common.actions.login') }}",
            'Logout' => "{{ __('common.actions.logout') }}",
            'Register' => "{{ __('common.actions.register') }}",
            'Continue' => "{{ __('common.actions.continue') }}",
            'Proceed' => "{{ __('common.actions.proceed') }}",
            'Pay Now' => "{{ __('common.actions.pay_now') }}",
            'Checkout' => "{{ __('common.actions.checkout') }}",
            'Add to Cart' => "{{ __('common.actions.add_to_cart') }}",
            'Test Connection' => "{{ __('common.actions.test_connection') }}",
            'Mark as Paid' => "{{ __('common.actions.mark_paid') }}",
            'Mark as Fraud' => "{{ __('common.actions.mark_fraud') }}",
            'Accept' => "{{ __('common.actions.accept') }}",
            'Decline' => "{{ __('common.actions.decline') }}",
            'Resend' => "{{ __('common.actions.resend') }}",
        ];

        // Replace button text: >Text</button> and >Text</a> patterns
        // Process longer strings first to avoid partial matches
        $sortedMap = $buttonMap;
        uksort($sortedMap, fn($a, $b) => strlen($b) - strlen($a));

        foreach ($sortedMap as $text => $replacement) {
            $escaped = preg_quote($text, '/');
            // Match text inside buttons and links (but not already translated)
            $content = preg_replace(
                '/(?<=>)\s*' . $escaped . '\s*(?=<\/(?:button|a)>)/i',
                $replacement,
                $content,
                -1,
                $count
            );
            $this->replacements += $count;

            // Match standalone text in spans, labels, etc.
            // Only exact match between > and </ (not inside {{ }})
            $content = preg_replace(
                '/(?<=>)\s*' . $escaped . '\s*(?=<\/(?:span|label|h[1-6]|p|th|td|li|strong|em|div)>)/i',
                $replacement,
                $content,
                -1,
                $count
            );
            $this->replacements += $count;
        }

        // ── TABLE HEADERS ──
        $thMap = [
            'ID' => 'common.table.id',
            'Name' => 'common.table.name',
            'Email' => 'common.table.email',
            'Company' => 'common.table.company',
            'Status' => 'common.table.status',
            'Created' => 'common.table.created',
            'Actions' => 'common.table.actions',
            'Date' => 'common.table.date',
            'Amount' => 'common.table.amount',
            'Total' => 'common.table.total',
            'Type' => 'common.table.type',
            'Description' => 'common.table.description',
            'Domain' => 'common.table.domain',
            'Product' => 'common.table.product',
            'Due Date' => 'common.table.due_date',
            'Payment Method' => 'common.table.payment_method',
            'Priority' => 'common.table.priority',
            'Department' => 'common.table.department',
            'Subject' => 'common.table.subject',
            'Last Reply' => 'common.table.last_reply',
            'Client' => 'common.table.client',
            'Invoice #' => 'common.table.invoice_num',
            'Order #' => 'common.table.order_num',
            'Price' => 'common.table.price',
            'Quantity' => 'common.table.quantity',
            'Subtotal' => 'common.table.subtotal',
            'Tax' => 'common.table.tax',
            'Discount' => 'common.table.discount',
            'Recurring' => 'common.table.recurring',
            'Next Due Date' => 'common.table.next_due',
            'Billing Cycle' => 'common.table.billing_cycle',
            'Server' => 'common.table.server',
            'Username' => 'common.table.username',
            'Registrar' => 'common.table.registrar',
            'Expiry Date' => 'common.table.expiry_date',
            'Auto Renew' => 'common.table.auto_renew',
            'Assigned To' => 'common.table.assigned_to',
            'Group' => 'common.table.group',
            'Role' => 'common.table.role',
            'Last Login' => 'common.table.last_login',
            'IP Address' => 'common.table.ip_address',
            'Notes' => 'common.table.notes',
            'Value' => 'common.table.value',
            'Code' => 'common.table.code',
            'Rate' => 'common.table.rate',
            'Country' => 'common.table.country',
            'State' => 'common.table.state',
        ];

        // Sort by length descending
        uksort($thMap, fn($a, $b) => strlen($b) - strlen($a));

        foreach ($thMap as $text => $key) {
            $escaped = preg_quote($text, '/');
            $content = preg_replace(
                '/<th([^>]*)>\s*' . $escaped . '\s*<\/th>/i',
                "<th$1>{{ __('{$key}') }}</th>",
                $content,
                -1,
                $count
            );
            $this->replacements += $count;
        }

        // ── STATUS BADGES ──
        $statusMap = [
            'Active' => 'common.status.active',
            'Inactive' => 'common.status.inactive',
            'Closed' => 'common.status.closed',
            'Pending' => 'common.status.pending',
            'Suspended' => 'common.status.suspended',
            'Terminated' => 'common.status.terminated',
            'Cancelled' => 'common.status.cancelled',
            'Paid' => 'common.status.paid',
            'Unpaid' => 'common.status.unpaid',
            'Overdue' => 'common.status.overdue',
            'Open' => 'common.status.open',
            'Answered' => 'common.status.answered',
            'In Progress' => 'common.status.in_progress',
            'Customer-Reply' => 'common.status.customer_reply',
            'Fraud' => 'common.status.fraud',
            'Completed' => 'common.status.completed',
            'Expired' => 'common.status.expired',
            'Draft' => 'common.status.draft',
            'Sent' => 'common.status.sent',
            'Accepted' => 'common.status.accepted',
            'Declined' => 'common.status.declined',
            'Refunded' => 'common.status.refunded',
        ];

        // ── FORM LABELS ──
        $labelMap = [
            'First Name' => 'common.form.first_name',
            'Last Name' => 'common.form.last_name',
            'Email Address' => 'common.form.email_address',
            'Email' => 'common.form.email',
            'Company Name' => 'common.form.company_name',
            'Address Line 1' => 'common.form.address1',
            'Address Line 2' => 'common.form.address2',
            'Address' => 'common.form.address',
            'City' => 'common.form.city',
            'State/Region' => 'common.form.state',
            'Postal/Zip Code' => 'common.form.postcode',
            'Phone Number' => 'common.form.phone_number',
            'Password' => 'common.form.password',
            'Confirm Password' => 'common.form.confirm_password',
            'Current Password' => 'common.form.current_password',
            'New Password' => 'common.form.new_password',
            'Language' => 'common.form.language',
            'Tax ID' => 'common.form.tax_id',
            'Username' => 'common.form.username',
            'Subject' => 'common.form.subject',
            'Message' => 'common.form.message',
            'Description' => 'common.form.description',
        ];

        uksort($labelMap, fn($a, $b) => strlen($b) - strlen($a));

        foreach ($labelMap as $text => $key) {
            $escaped = preg_quote($text, '/');
            // <label...>Text</label>
            $content = preg_replace(
                '/<label([^>]*)>\s*' . $escaped . '\s*<\/label>/i',
                "<label$1>{{ __('{$key}') }}</label>",
                $content,
                -1,
                $count
            );
            $this->replacements += $count;

            // class="form-label">Text</...>  (some labels use div/span)
            $content = preg_replace(
                '/(class="form-label"[^>]*>)\s*' . $escaped . '\s*(?=<)/i',
                "$1{{ __('{$key}') }}",
                $content,
                -1,
                $count
            );
            $this->replacements += $count;
        }

        // ── PLACEHOLDERS ──
        $placeholderMap = [
            'Search...' => 'common.placeholder.search',
            'Name, email, company...' => 'common.placeholder.search_clients',
            'Enter email address' => 'common.placeholder.enter_email',
            'Enter email' => 'common.placeholder.enter_email',
            'Enter name' => 'common.placeholder.enter_name',
            'Enter password' => 'common.placeholder.enter_password',
            'Type your message...' => 'common.placeholder.type_message',
            'Promo code' => 'common.placeholder.promo_code',
        ];

        uksort($placeholderMap, fn($a, $b) => strlen($b) - strlen($a));

        foreach ($placeholderMap as $text => $key) {
            $escaped = preg_quote($text, '/');
            $content = preg_replace(
                '/placeholder="' . $escaped . '"/i',
                "placeholder=\"{{ __('{$key}') }}\"",
                $content,
                -1,
                $count
            );
            $this->replacements += $count;
        }

        // ── EMPTY STATES ──
        $emptyMap = [
            'No clients found.' => 'admin.clients.no_clients',
            'No products found.' => 'admin.products.no_products',
            'No orders found.' => 'admin.orders.no_orders',
            'No invoices found.' => 'admin.invoices.no_invoices',
            'No services found.' => 'admin.services.no_services',
            'No domains found.' => 'admin.domains.no_domains',
            'No tickets found.' => 'admin.tickets.no_tickets',
            'No quotes found.' => 'admin.quotes.no_quotes',
            'No projects found.' => 'admin.projects.no_projects',
            'No log entries found.' => 'admin.logs.no_logs',
            'No SSL orders found.' => 'admin.ssl.no_orders',
            'No records found.' => 'common.empty.no_records',
            'No results found.' => 'common.empty.no_results',
            'No data available.' => 'common.empty.no_data',
            'No items found.' => 'common.empty.no_items',
        ];

        uksort($emptyMap, fn($a, $b) => strlen($b) - strlen($a));

        foreach ($emptyMap as $text => $key) {
            $escaped = preg_quote($text, '/');
            $content = preg_replace(
                '/(?<![_\w\'])' . $escaped . '(?![_\w\'])/i',
                "{{ __('{$key}') }}",
                $content,
                -1,
                $count
            );
            $this->replacements += $count;
        }

        // ── SELECT OPTIONS ──
        $content = preg_replace(
            '/<option value="">All Statuses<\/option>/',
            '<option value="">{{ __(\'common.misc.all_statuses\') }}</option>',
            $content, -1, $count
        );
        $this->replacements += $count;

        $content = preg_replace(
            '/<option value="">All Groups<\/option>/',
            '<option value="">{{ __(\'common.misc.all_groups\') }}</option>',
            $content, -1, $count
        );
        $this->replacements += $count;

        $content = preg_replace(
            '/<option value="">All Departments<\/option>/',
            '<option value="">{{ __(\'common.misc.all_departments\') }}</option>',
            $content, -1, $count
        );
        $this->replacements += $count;

        return $content;
    }

    private function applySectionTitleReplacements(string $content, string $group, string $path): string
    {
        // @section("title", "Page Title") → @section("title", __("group.page.title"))
        $content = preg_replace_callback(
            '/@section\(\s*"title"\s*,\s*"([^"]+)"\s*\)/',
            function ($matches) use ($group) {
                $title = $matches[1];
                $key = $group . '.' . $this->textToKey($title) . '.title';
                // Use the most appropriate key
                $key = $this->resolveTitleKey($title, $group);
                $this->replacements++;
                return '@section("title", __("' . $key . '"))';
            },
            $content
        );
        return $content;
    }

    private function resolveTitleKey(string $title, string $group): string
    {
        $map = [
            'Clients' => 'admin.clients.title',
            'Add New Client' => 'admin.clients.add_new',
            'Create Client' => 'admin.clients.create_client',
            'Edit Client' => 'admin.clients.edit_client',
            'Products/Services' => 'admin.products.title',
            'Create Product' => 'admin.products.create',
            'Edit Product' => 'admin.products.edit',
            'Orders' => 'admin.orders.title',
            'Invoices' => 'admin.invoices.title',
            'Create Invoice' => 'admin.invoices.create_invoice',
            'Services' => 'admin.services.title',
            'Domains' => 'admin.domains.title',
            'Support Tickets' => 'admin.tickets.title',
            'Reports' => 'admin.reports.title',
            'Settings' => 'admin.settings.title',
            'General Settings' => 'admin.settings.general',
            'Appearance' => 'admin.settings.appearance',
            'My Account' => 'admin.settings.my_account',
            'Dashboard' => $group === 'client' ? 'client.dashboard.title' : 'admin.dashboard.title',
            'My Services' => 'client.services.title',
            'My Domains' => 'client.domains.title',
            'My Invoices' => 'client.invoices.title',
            'My Tickets' => 'client.tickets.title',
            'Open New Ticket' => 'client.tickets.create',
            'Knowledge Base' => 'client.kb.title',
            'Announcements' => 'client.announcements.title',
            'Downloads' => 'client.downloads.title',
            'Affiliate Program' => 'client.affiliates.title',
            'SSL Certificates' => $group === 'client' ? 'client.ssl.title' : 'admin.ssl.title',
            'Add Funds' => 'client.funds.title',
            'Contact Us' => 'client.contact.title',
            'Shopping Cart' => 'client.cart.title',
            'Checkout' => 'client.checkout.title',
            'Store' => 'client.store.title',
            'Quotes' => 'admin.quotes.title',
            'Create Quote' => 'admin.quotes.create',
            'Edit Quote' => 'admin.quotes.edit',
            'Projects' => 'admin.projects.title',
            'Create Project' => 'admin.projects.create',
            'Edit Project' => 'admin.projects.edit',
            'System Logs' => 'admin.logs.title',
            'Affiliates' => 'admin.affiliates.title',
            'WHOIS Lookup' => 'admin.whois.title',
            'Mass Email' => 'admin.bulk.mass_email',
            'Calendar' => 'admin.calendar.title',
            'Admin Login' => 'auth.login.admin_title',
            'Sign In' => 'auth.login.title',
            'Create Account' => 'auth.register.title',
            'Forgot Password' => 'auth.forgot.title',
            'Reset Password' => 'auth.reset.title',
            'Two-Factor Authentication' => 'auth.2fa.title',
            'Enable Two-Factor Authentication' => 'auth.2fa.enable_title',
            'Profile' => 'client.account.profile',
            'Change Password' => 'client.account.change_password',
            'Contacts' => 'client.account.contacts',
            'Security' => 'client.account.security',
            'Payment Methods' => 'client.account.payment_methods',
            'Request Cancellation' => 'client.cancel.title',
            'Upgrade/Downgrade' => 'client.services.upgrade',
            'Domain Search' => 'client.domain_search.title',
            'Domain Pricing' => 'client.domain_search.pricing_title',
        ];

        if (isset($map[$title])) {
            return $map[$title];
        }

        // Fallback: generate key from title
        return $group . '.' . $this->textToKey($title);
    }

    private function applyContextualReplacements(string $content, string $path): string
    {
        // page-specific replacements based on file path
        // This handles headings like <h1>Clients</h1> in specific files

        $fileReplacements = [
            'resources/views/admin/clients/index.blade.php' => [
                ['<h1>Clients</h1>', '<h1>{{ __(\'admin.clients.title\') }}</h1>'],
                ['Add New Client', "{{ __('admin.clients.add_new') }}"],
            ],
            'resources/views/admin/dashboard.blade.php' => [
                ['<h1>Dashboard</h1>', '<h1>{{ __(\'admin.dashboard.title\') }}</h1>'],
                ['Income Today', "{{ __('admin.dashboard.income_today') }}"],
                ['Income This Month', "{{ __('admin.dashboard.income_month') }}"],
                ['Pending Orders', "{{ __('admin.dashboard.pending_orders') }}"],
                ['Active Services', "{{ __('admin.dashboard.active_services') }}"],
                ['Overdue Invoices', "{{ __('admin.dashboard.overdue_invoices') }}"],
                ['Open Tickets', "{{ __('admin.dashboard.open_tickets') }}"],
                ['Total Clients', "{{ __('admin.dashboard.total_clients') }}"],
                ['Recent Orders', "{{ __('admin.dashboard.recent_orders') }}"],
                ['Recent Tickets', "{{ __('admin.dashboard.recent_tickets') }}"],
            ],
        ];

        if (isset($fileReplacements[$path])) {
            foreach ($fileReplacements[$path] as [$search, $replace]) {
                $newContent = str_replace($search, $replace, $content, $count);
                if ($count > 0) {
                    $content = $newContent;
                    $this->replacements += $count;
                }
            }
        }

        return $content;
    }

    // ─────────────────────────────────────────────
    // CONTROLLER CONVERSION
    // ─────────────────────────────────────────────
    private function convertControllers(): void
    {
        $controllerPath = $this->basePath . '/app/Http/Controllers';
        $files = glob($controllerPath . '/{Admin,Client}/*.php', GLOB_BRACE);
        $this->info("Found " . count($files) . " controller files to process.");

        // Build flash message replacement map
        $messageMap = $this->getControllerMessageMap();

        foreach ($files as $file) {
            $relativePath = str_replace($this->basePath . '/', '', $file);
            $content = file_get_contents($file);
            $original = $content;

            // Replace ->with('success', 'Message') patterns
            $content = preg_replace_callback(
                "/->with\(\s*'(success|error)'\s*,\s*'([^']+)'\s*\)/",
                function ($matches) use ($messageMap) {
                    $type = $matches[1];
                    $message = $matches[2];
                    $fullKey = $this->resolveMessageKey($type, $message, $messageMap);
                    $this->replacements++;
                    return "->with('{$type}', __('{$fullKey}'))";
                },
                $content
            );

            // Replace ->with('success', "Message $var") patterns (double quotes with variables)
            $content = preg_replace_callback(
                '/->with\(\s*\'(success|error)\'\s*,\s*"([^"]+)"\s*\)/',
                function ($matches) use ($messageMap) {
                    $type = $matches[1];
                    $message = $matches[2];
                    // Skip if already contains __( 
                    if (str_contains($message, '__(')) return $matches[0];
                    $fullKey = $this->resolveMessageKey($type, $message, $messageMap);
                    $this->replacements++;
                    // If message contains variables, keep as interpolated
                    if (preg_match('/\$\w+/', $message)) {
                        return $matches[0]; // Skip complex interpolation
                    }
                    return "->with('{$type}', __('{$fullKey}'))";
                },
                $content
            );

            if ($content !== $original) {
                if (!$this->option('dry-run')) {
                    file_put_contents($file, $content);
                }
                $this->filesModified++;
                $this->info("  Modified: {$relativePath}");
            }
        }
    }

    private function getControllerMessageMap(): array
    {
        return [
            // Success messages
            'Client created successfully.' => 'messages.success.client_created',
            'Client updated.' => 'messages.success.client_updated',
            'Client deleted.' => 'messages.success.client_deleted',
            'Note added.' => 'messages.success.note_added',
            'Product created successfully.' => 'messages.success.product_created',
            'Product updated.' => 'messages.success.product_updated',
            'Product deleted.' => 'messages.success.product_deleted',
            'Group created successfully.' => 'messages.success.group_created',
            'Group created.' => 'messages.success.group_created',
            'Order accepted and provisioning started.' => 'messages.success.order_accepted',
            'Order cancelled.' => 'messages.success.order_cancelled',
            'Order marked as fraud.' => 'messages.success.order_fraud',
            'Order deleted.' => 'messages.success.order_deleted',
            'Invoice created successfully.' => 'messages.success.invoice_created',
            'Invoice marked as paid.' => 'messages.success.invoice_paid',
            'Invoice cancelled.' => 'messages.success.invoice_cancelled',
            'Settings saved successfully.' => 'messages.success.settings_saved',
            'Settings saved.' => 'messages.success.settings_saved',
            'Password changed successfully.' => 'messages.success.password_changed',
            'Password updated.' => 'messages.success.password_changed',
            'Profile updated successfully.' => 'messages.success.profile_updated',
            'Profile updated.' => 'messages.success.profile_updated',
            'Ticket created successfully.' => 'messages.success.ticket_created',
            'Ticket submitted successfully.' => 'messages.success.ticket_created',
            'Reply added successfully.' => 'messages.success.ticket_replied',
            'Reply added.' => 'messages.success.ticket_replied',
            'Ticket closed.' => 'messages.success.ticket_closed',
            'Cancellation request submitted.' => 'messages.success.cancellation_submitted',
            'Nameservers updated successfully.' => 'messages.success.nameservers_updated',
            'Nameservers updated.' => 'messages.success.nameservers_updated',
            'Domain lock status updated.' => 'messages.success.domain_lock_toggled',
            'Auto-renew status updated.' => 'messages.success.auto_renew_toggled',
            'Promo code applied successfully.' => 'messages.success.promo_applied',
            'Promo code applied.' => 'messages.success.promo_applied',
            'Item added to cart.' => 'messages.success.item_added_to_cart',
            'Item removed from cart.' => 'messages.success.item_removed_from_cart',
            'Order placed successfully!' => 'messages.success.order_placed',
            'Order placed successfully.' => 'messages.success.order_placed',
            'Contact added successfully.' => 'messages.success.contact_created',
            'Contact added.' => 'messages.success.contact_created',
            'Contact removed.' => 'messages.success.contact_deleted',
            'Contact deleted.' => 'messages.success.contact_deleted',
            'Message sent successfully.' => 'messages.success.message_sent',
            'Message sent.' => 'messages.success.message_sent',
            'Funds added successfully.' => 'messages.success.funds_added',
            'Affiliate account activated.' => 'messages.success.affiliate_activated',
            'Withdrawal request submitted.' => 'messages.success.withdrawal_requested',
            'Payout processed.' => 'messages.success.payout_processed',
            'Quote created successfully.' => 'messages.success.quote_created',
            'Quote updated.' => 'messages.success.quote_updated',
            'Quote sent to client.' => 'messages.success.quote_sent',
            'Quote converted to invoice.' => 'messages.success.quote_converted',
            'Quote accepted.' => 'messages.success.quote_accepted',
            'Quote declined.' => 'messages.success.quote_declined',
            'Quote deleted.' => 'messages.success.quote_deleted',
            'Project created successfully.' => 'messages.success.project_created',
            'Project created.' => 'messages.success.project_created',
            'Project updated.' => 'messages.success.project_updated',
            'Project deleted.' => 'messages.success.project_deleted',
            'Task added.' => 'messages.success.task_added',
            'Task updated.' => 'messages.success.task_updated',
            'Task deleted.' => 'messages.success.task_deleted',
            'Message added.' => 'messages.success.message_added',
            'Logo uploaded.' => 'messages.success.logo_uploaded',
            'Logo uploaded successfully.' => 'messages.success.logo_uploaded',
            'Logo removed.' => 'messages.success.logo_removed',
            'Favicon uploaded.' => 'messages.success.favicon_uploaded',
            'Favicon uploaded successfully.' => 'messages.success.favicon_uploaded',
            'Favicon removed.' => 'messages.success.favicon_removed',
            'Theme activated.' => 'messages.success.theme_activated',
            'Theme activated successfully.' => 'messages.success.theme_activated',
            'Theme installed.' => 'messages.success.theme_installed',
            'Theme installed successfully.' => 'messages.success.theme_installed',
            'Theme deleted.' => 'messages.success.theme_deleted',
            'Test email sent.' => 'messages.success.test_email_sent',
            'Test email sent successfully.' => 'messages.success.test_email_sent',
            'Returned to admin panel.' => 'messages.success.impersonation_stopped',
            'Admin created.' => 'messages.success.admin_created',
            'Admin account created.' => 'messages.success.admin_created',
            'Admin updated.' => 'messages.success.admin_updated',
            'Admin account updated.' => 'messages.success.admin_updated',
            'Admin deleted.' => 'messages.success.admin_deleted',
            'Admin account deleted.' => 'messages.success.admin_deleted',
            'Role created.' => 'messages.success.role_created',
            'Role updated.' => 'messages.success.role_updated',
            'Role deleted.' => 'messages.success.role_deleted',
            'Currency added.' => 'messages.success.currency_created',
            'Currency created.' => 'messages.success.currency_created',
            'Currency updated.' => 'messages.success.currency_updated',
            'Currency removed.' => 'messages.success.currency_deleted',
            'Currency deleted.' => 'messages.success.currency_deleted',
            'Default currency updated.' => 'messages.success.currency_set_default',
            'Tax rule created.' => 'messages.success.tax_created',
            'Tax rule updated.' => 'messages.success.tax_updated',
            'Tax rule deleted.' => 'messages.success.tax_deleted',
            'Server added.' => 'messages.success.server_created',
            'Server created.' => 'messages.success.server_created',
            'Server updated.' => 'messages.success.server_updated',
            'Server removed.' => 'messages.success.server_deleted',
            'Server deleted.' => 'messages.success.server_deleted',
            'Server group created.' => 'messages.success.server_group_created',
            'Server group updated.' => 'messages.success.server_group_updated',
            'Server group deleted.' => 'messages.success.server_group_deleted',
            'Promotion created.' => 'messages.success.promotion_created',
            'Promotion updated.' => 'messages.success.promotion_updated',
            'Promotion deleted.' => 'messages.success.promotion_deleted',
            'Department created.' => 'messages.success.department_created',
            'Department updated.' => 'messages.success.department_updated',
            'Department deleted.' => 'messages.success.department_deleted',
            'Status created.' => 'messages.success.status_created',
            'Status updated.' => 'messages.success.status_updated',
            'Status deleted.' => 'messages.success.status_deleted',
            'Email template updated.' => 'messages.success.template_updated',
            'Template updated.' => 'messages.success.template_updated',
            'Announcement created.' => 'messages.success.announcement_created',
            'Announcement updated.' => 'messages.success.announcement_updated',
            'Announcement deleted.' => 'messages.success.announcement_deleted',
            'Article created.' => 'messages.success.article_created',
            'Article updated.' => 'messages.success.article_updated',
            'Article deleted.' => 'messages.success.article_deleted',
            'Category created.' => 'messages.success.category_created',
            'Download added.' => 'messages.success.download_created',
            'Download removed.' => 'messages.success.download_deleted',
            'Download deleted.' => 'messages.success.download_deleted',
            'Network issue created.' => 'messages.success.network_issue_created',
            'Network issue updated.' => 'messages.success.network_issue_updated',
            'Network issue deleted.' => 'messages.success.network_issue_deleted',
            'IP address banned.' => 'messages.success.banned_ip_created',
            'IP banned.' => 'messages.success.banned_ip_created',
            'IP ban removed.' => 'messages.success.banned_ip_deleted',
            'Email domain banned.' => 'messages.success.banned_email_created',
            'Email ban removed.' => 'messages.success.banned_email_deleted',
            'To-do item added.' => 'messages.success.todo_created',
            'Todo added.' => 'messages.success.todo_created',
            'To-do item updated.' => 'messages.success.todo_updated',
            'Todo updated.' => 'messages.success.todo_updated',
            'To-do item deleted.' => 'messages.success.todo_deleted',
            'Todo deleted.' => 'messages.success.todo_deleted',
            'API credential created.' => 'messages.success.api_credential_created',
            'API credential deleted.' => 'messages.success.api_credential_deleted',
            'Gateway settings updated.' => 'messages.success.gateway_updated',
            'Registrar settings updated.' => 'messages.success.registrar_updated',
            'TLD pricing added.' => 'messages.success.tld_created',
            'TLD added.' => 'messages.success.tld_created',
            'TLD pricing updated.' => 'messages.success.tld_updated',
            'TLD updated.' => 'messages.success.tld_updated',
            'TLD pricing removed.' => 'messages.success.tld_deleted',
            'TLD removed.' => 'messages.success.tld_deleted',
            'TLD deleted.' => 'messages.success.tld_deleted',
            'White-label settings saved.' => 'messages.success.whitelabel_saved',
            'Dark mode settings saved.' => 'messages.success.darkmode_saved',
            'Sections reordered.' => 'messages.success.sections_reordered',
            'Section updated.' => 'messages.success.section_updated',
            'Section content saved.' => 'messages.success.section_content_saved',
            'Two-factor authentication enabled.' => 'messages.success.2fa_enabled',
            '2FA enabled.' => 'messages.success.2fa_enabled',
            'Two-factor authentication disabled.' => 'messages.success.2fa_disabled',
            '2FA disabled.' => 'messages.success.2fa_disabled',
            'Escalation rule created.' => 'messages.success.escalation_created',
            'Escalation rule updated.' => 'messages.success.escalation_updated',
            'Escalation rule deleted.' => 'messages.success.escalation_deleted',
            'Addon created.' => 'messages.success.addon_created',
            'Addon updated.' => 'messages.success.addon_updated',
            'Addon deleted.' => 'messages.success.addon_deleted',
            'Bundle created.' => 'messages.success.bundle_created',
            'Bundle deleted.' => 'messages.success.bundle_deleted',
            'Billable item created.' => 'messages.success.billable_item_created',
            'Billable item deleted.' => 'messages.success.billable_item_deleted',
            'Client group created.' => 'messages.success.client_group_created',
            'Client group updated.' => 'messages.success.client_group_updated',
            'Client group deleted.' => 'messages.success.client_group_deleted',
            'Option group created.' => 'messages.success.config_option_group_created',
            'Option group updated.' => 'messages.success.config_option_group_updated',
            'Option group deleted.' => 'messages.success.config_option_group_deleted',
            'Option created.' => 'messages.success.config_option_created',
            'Option deleted.' => 'messages.success.config_option_deleted',
            'Sub-option created.' => 'messages.success.config_sub_created',
            'Sub-option deleted.' => 'messages.success.config_sub_deleted',
            'Notification provider added.' => 'messages.success.notification_provider_created',
            'Notification provider updated.' => 'messages.success.notification_provider_updated',
            'Notification provider removed.' => 'messages.success.notification_provider_deleted',
            'Notification rule added.' => 'messages.success.notification_rule_created',
            'Notification rule removed.' => 'messages.success.notification_rule_deleted',
            'Spam filter settings updated.' => 'messages.success.spam_settings_updated',
            'Spam filter rule added.' => 'messages.success.spam_filter_created',
            'Spam filter rule removed.' => 'messages.success.spam_filter_deleted',
            'SSL module settings updated.' => 'messages.success.ssl_module_updated',
            'SSL configuration submitted.' => 'messages.success.ssl_config_submitted',
            'Validation email resent.' => 'messages.success.ssl_validation_resent',
            'Password reset link sent.' => 'messages.success.reset_link_sent',
            'Password reset successfully.' => 'messages.success.password_reset',
            'Registration successful! Welcome.' => 'messages.success.registered',
            'Registration successful.' => 'messages.success.registered',
            'Welcome!' => 'messages.success.registered',

            // Error messages
            'No user account linked to this client.' => 'messages.error.no_user_linked',
            'Invalid credentials.' => 'messages.error.invalid_credentials',
            'Your account has been disabled.' => 'messages.error.account_disabled',
            'Invalid authentication code.' => 'messages.error.2fa_invalid',
            'Invalid code.' => 'messages.error.2fa_invalid',
            'Invalid or expired promo code.' => 'messages.error.promo_invalid',
            'Your cart is empty.' => 'messages.error.cart_empty',
            'Already activated.' => 'messages.error.already_activated',
            'Insufficient balance.' => 'messages.error.insufficient_balance',
        ];
    }

    private function resolveMessageKey(string $type, string $message, array $map): string
    {
        // Direct match
        if (isset($map[$message])) {
            return $map[$message];
        }

        // Fuzzy match (case-insensitive)
        foreach ($map as $text => $key) {
            if (strcasecmp($text, $message) === 0) {
                return $key;
            }
        }

        // Generate key from message text
        $key = $this->textToKey($message);
        return "messages.{$type}.{$key}";
    }

    // ─────────────────────────────────────────────
    // EMAIL TEMPLATE CONVERSION
    // ─────────────────────────────────────────────
    private function convertEmails(): void
    {
        $emailPath = $this->basePath . '/resources/views/emails';
        $files = glob($emailPath . '/*.blade.php');
        $this->info("Found " . count($files) . " email templates to process.");

        foreach ($files as $file) {
            $relativePath = str_replace($this->basePath . '/', '', $file);
            $content = file_get_contents($file);
            $original = $content;

            // Common email greeting pattern
            $content = preg_replace(
                '/Dear\s*\{\{\s*(\$\w+(?:->\w+(?:\?->\w+)?)*(?:\s*\?\?\s*\'[^\']+\')?)?\s*\}\}\s*,/',
                "{{ __('email.common.greeting', ['name' => $1]) }}",
                $content
            );

            // "Dear Customer" standalone
            $content = str_replace(
                "Dear Customer,",
                "{{ __('email.common.greeting', ['name' => __('email.common.customer')]) }}",
                $content
            );

            // "Please log in to your account" pattern
            $content = str_replace(
                'Please log in to your account to review and pay this invoice.',
                "{{ __('email.common.login_link') }}",
                $content
            );
            $content = str_replace(
                'Please log in to your account to view the details.',
                "{{ __('email.common.login_link') }}",
                $content
            );
            $content = str_replace(
                'Please log in to your account',
                "{{ __('email.common.login_link') }}",
                $content
            );

            if ($content !== $original) {
                if (!$this->option('dry-run')) {
                    file_put_contents($file, $content);
                }
                $this->filesModified++;
                $changes = substr_count($content, "__(") - substr_count($original, "__(");
                $this->info("  Modified: {$relativePath} (+{$changes} keys)");
            }
        }
    }

    // ─────────────────────────────────────────────
    // HELPERS
    // ─────────────────────────────────────────────
    private function getBladeFiles(): array
    {
        $dirs = [
            $this->basePath . '/resources/views/admin',
            $this->basePath . '/resources/views/client',
            $this->basePath . '/resources/views/sections',
            $this->basePath . '/resources/views/errors',
            $this->basePath . '/resources/views/components',
        ];

        $files = [];
        foreach ($dirs as $dir) {
            if (is_dir($dir)) {
                $iterator = new \RecursiveIteratorIterator(
                    new \RecursiveDirectoryIterator($dir)
                );
                foreach ($iterator as $file) {
                    if ($file->isFile() && str_ends_with($file->getFilename(), '.blade.php')) {
                        $files[] = $file->getPathname();
                    }
                }
            }
        }

        // Add welcome.blade.php
        $welcome = $this->basePath . '/resources/views/welcome.blade.php';
        if (file_exists($welcome)) $files[] = $welcome;

        // Add pdf/invoice.blade.php
        $pdf = $this->basePath . '/resources/views/pdf/invoice.blade.php';
        if (file_exists($pdf)) $files[] = $pdf;

        return $files;
    }

    private function getGroupFromPath(string $path): string
    {
        if (str_contains($path, '/admin/')) return 'admin';
        if (str_contains($path, '/client/')) return 'client';
        if (str_contains($path, '/emails/')) return 'email';
        if (str_contains($path, '/sections/')) return 'sections';
        if (str_contains($path, '/errors/')) return 'errors';
        return 'common';
    }

    private function textToKey(string $text): string
    {
        // Remove trailing punctuation
        $text = rtrim($text, '.!?');
        // Convert to snake_case
        $key = strtolower($text);
        $key = preg_replace('/[^a-z0-9\s]/', '', $key);
        $key = preg_replace('/\s+/', '_', trim($key));
        // Truncate to reasonable length
        if (strlen($key) > 50) {
            $key = substr($key, 0, 50);
        }
        return $key;
    }

    private function replaceInFile(string $relativePath, array $replacements): void
    {
        $fullPath = $this->basePath . '/' . $relativePath;
        if (!file_exists($fullPath)) {
            $this->warn("  File not found: {$relativePath}");
            return;
        }

        $content = file_get_contents($fullPath);
        $original = $content;

        foreach ($replacements as [$search, $replace]) {
            $content = str_replace($search, $replace, $content, $count);
            $this->replacements += $count;
        }

        if ($content !== $original) {
            if (!$this->option('dry-run')) {
                file_put_contents($fullPath, $content);
            }
            $this->filesModified++;
            $this->info("  Modified: {$relativePath}");
        }
    }
}
