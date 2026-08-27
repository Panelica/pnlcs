<?php

namespace App\Http\Controllers\Api;

use App\Models\ActivityLog;
use App\Models\Admin;
use App\Models\Affiliate;
use App\Models\Announcement;
use App\Models\ApiCredential;
use App\Models\BannedIp;
use App\Models\Client;
use App\Models\Domain;
use App\Models\Email;
use App\Models\EmailTemplate;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\OrderStatus;
use App\Models\Product;
use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\Promotion;
use App\Models\Quote;
use App\Models\Server;
use App\Models\Service;
use App\Models\Setting;
use App\Models\Ticket;
use App\Models\TodoItem;
use App\Models\User;
use App\Services\QuoteService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SystemApiController extends BaseApiController
{
    public function getStats()
    {
        return $this->success([
            'stats' => [
                'total_clients' => Client::count(),
                'active_clients' => Client::where('status', 'active')->count(),
                'total_services' => Service::count(),
                'active_services' => Service::where('status', 'active')->count(),
                'total_domains' => Domain::count(),
                'total_invoices' => Invoice::count(),
                'unpaid_invoices' => Invoice::where('status', 'unpaid')->count(),
                'total_orders' => Order::count(),
                'pending_orders' => Order::where('status', 'pending')->count(),
                'total_tickets' => Ticket::count(),
                'open_tickets' => Ticket::where('status', 'open')->count(),
                'total_admins' => Admin::count(),
            ],
        ]);
    }

    /**
     * Health probe. /api/health is public (uptime monitors), so it only reports
     * whether the service is up. Version numbers, disk and memory figures — and
     * the database error message, which can carry host and credential detail —
     * are limited to the authenticated /api/v1/gethealthstatus caller.
     */
    public function getHealthStatus(Request $request)
    {
        $public = $request->is('api/health');

        $dbStatus = 'ok';
        try {
            DB::connection()->getPdo();
        } catch (\Exception $e) {
            Log::error('Health check: database unreachable: '.$e->getMessage());
            $dbStatus = $public ? 'error' : 'error: '.$e->getMessage();
        }

        if ($public) {
            return $this->success([
                'health' => [
                    'status' => $dbStatus === 'ok' ? 'ok' : 'degraded',
                    'database' => $dbStatus === 'ok' ? 'ok' : 'error',
                    'timestamp' => now()->toIso8601String(),
                ],
            ]);
        }

        // Disk space for the application directory
        $diskTotal = disk_total_space(base_path());
        $diskFree = disk_free_space(base_path());
        $diskUsed = $diskTotal - $diskFree;

        return $this->success([
            'health' => [
                'status' => $dbStatus === 'ok' ? 'ok' : 'degraded',
                'version' => '1.0.0',
                'laravel' => app()->version(),
                'php' => phpversion(),
                'database' => $dbStatus,
                'disk' => [
                    'total_bytes' => $diskTotal,
                    'free_bytes' => $diskFree,
                    'used_bytes' => $diskUsed,
                    'used_percent' => $diskTotal > 0 ? round(($diskUsed / $diskTotal) * 100, 2) : 0,
                ],
                'memory' => [
                    'limit' => ini_get('memory_limit'),
                    'current' => round(memory_get_usage(true) / 1024 / 1024, 2).'MB',
                    'peak' => round(memory_get_peak_usage(true) / 1024 / 1024, 2).'MB',
                ],
                'timestamp' => now()->toIso8601String(),
            ],
        ]);
    }

    public function pnlcsDetails()
    {
        return $this->success([
            'pnlcs' => [
                'version' => '1.0.0',
                // The same resolver the panel, the invoices and the emails
                // use: white-label override first, then the general setting.
                // Reading the raw key made the API report a different company
                // name from every screen whenever the override was set.
                'company_name' => company_name(),
            ],
        ]);
    }

    public function getActivityLog(Request $request)
    {
        $query = ActivityLog::query();
        if ($request->filled('date')) {
            $query->whereDate('date', $request->date);
        }
        if ($request->filled('user')) {
            $query->where('user', $request->user);
        }

        return $this->paginated($query->orderBy('id', 'desc')->paginate($this->getPerPage(), ['*'], 'page', $this->getPage()));
    }

    public function logActivity(Request $request)
    {
        ActivityLog::log($request->description, $request->user);

        return $this->success();
    }

    public function getAdminUsers()
    {
        return $this->success(['admins' => Admin::with('role')->get()->toArray()]);
    }

    public function getAdminDetails(Request $request)
    {
        $admin = Admin::with('role')->find($request->adminid ?? auth('admin')->id());
        if (! $admin) {
            return $this->error('Admin Not Found', 404);
        }

        return $this->success(['admin' => $admin->toArray()]);
    }

    public function getStaffOnline()
    {
        $admins = Admin::whereNotNull('last_login')->where('last_login', '>=', now()->subMinutes(15))->get();

        return $this->success(['staff' => $admins->toArray()]);
    }

    public function getConfigurationValue(Request $request)
    {
        $validated = $request->validate(['setting' => 'required|string']);

        if (self::isSecretSetting($validated['setting'])) {
            return $this->error('That setting holds a credential and is not readable through the API.', 403);
        }

        return $this->success([
            'setting' => $validated['setting'],
            'value' => Setting::get($validated['setting']),
        ]);
    }

    public function setConfigurationValue(Request $request)
    {
        $validated = $request->validate(['setting' => 'required|string', 'value' => 'required|string']);

        if (self::isSecretSetting($validated['setting'])) {
            return $this->error('That setting holds a credential and is not writable through the API.', 403);
        }

        // Keep it where its screen looks for it. Setting::set() writes the
        // group as well as the value and defaults to "general", so naming a
        // setting belonging to another screen used to move it out from under
        // that screen - the mistake the settings form was hardened against,
        // left open at this door.
        $group = Setting::where('setting', $validated['setting'])->value('group') ?? 'general';

        Setting::set($validated['setting'], $validated['value'], $group);

        return $this->success();
    }

    /**
     * Settings that hold a credential.
     *
     * The settings table keeps the mail password in plain text, put there by
     * the settings screen. Reading it back needed nothing more than read
     * access to the API, which is not the same thing as being trusted with the
     * mail account.
     */
    private static function isSecretSetting(string $setting): bool
    {
        // "key" on its own, not only "api_key": the credential settings are
        // named MaxMindLicenseKey and the like, which the narrower pattern let
        // through in the clear. A Twilio *service* SID and account SID identify
        // an account well enough to pair with a leaked token, so SID counts too.
        return (bool) preg_match('/(password|secret|token|key|access_?hash|credential|sid)/i', $setting);
    }

    public function getAnnouncements(Request $request)
    {
        $query = Announcement::where('published', true);

        return $this->paginated($query->orderBy('id', 'desc')->paginate($this->getPerPage(), ['*'], 'page', $this->getPage()));
    }

    public function addAnnouncement(Request $request)
    {
        $validated = $request->validate(['title' => 'required|string', 'announcement' => 'required|string']);
        $a = Announcement::create($validated);

        return $this->success(['announcementid' => $a->id]);
    }

    public function updateAnnouncement(Request $request)
    {
        $a = Announcement::find($request->announcementid);
        if (! $a) {
            return $this->error('Announcement Not Found', 404);
        }
        foreach (['title', 'announcement', 'published'] as $f) {
            if ($request->has($f)) {
                $a->$f = $request->$f;
            }
        }
        $a->save();

        return $this->success();
    }

    public function deleteAnnouncement(Request $request)
    {
        $a = Announcement::find($request->announcementid);
        if (! $a) {
            return $this->error('Announcement Not Found', 404);
        }
        $a->delete();

        return $this->success();
    }

    public function getEmailTemplates()
    {
        return $this->success(['templates' => EmailTemplate::all()->toArray()]);
    }

    public function updateEmailTemplate(Request $request)
    {
        $template = EmailTemplate::find($request->templateid);
        if (! $template) {
            return $this->error('Template Not Found', 404);
        }
        foreach (['subject', 'message', 'disabled'] as $f) {
            if ($request->has($f)) {
                $template->$f = $request->$f;
            }
        }
        $template->save();

        return $this->success(['templateid' => $template->id]);
    }

    public function getEmails(Request $request)
    {
        $query = Email::query();
        if ($request->filled('userid')) {
            $query->where('client_id', $request->userid);
        }

        return $this->paginated($query->orderBy('id', 'desc')->paginate($this->getPerPage(), ['*'], 'page', $this->getPage()));
    }

    public function getServers()
    {
        $servers = Server::with('groups')->get();

        return $this->success(['servers' => $servers->toArray()]);
    }

    public function getRegistrars()
    {
        $registrars = DB::table('registrar_settings')->select('registrar')->distinct()->pluck('registrar');

        return $this->success(['registrars' => $registrars->toArray()]);
    }

    public function getProducts()
    {
        return $this->success(['products' => Product::with('group', 'pricing')->get()->toArray()]);
    }

    public function getPromotions()
    {
        return $this->success(['promotions' => Promotion::all()->toArray()]);
    }

    public function addPromotion(Request $request)
    {
        $validated = $request->validate([
            'code' => 'required|string|max:100|unique:promotions,code',
            'type' => 'required|in:percentage,fixed_amount',
            'value' => 'required|numeric|min:0',
        ]);
        $promo = Promotion::create(array_merge($validated, [
            'start_date' => $request->startdate ?? now()->format('Y-m-d'),
            'expiration_date' => $request->expirationdate ?? null,
            'max_uses' => $request->maxuses ?? 0,
            'uses' => 0,
            'recurring' => $request->boolean('recurring'),
            'notes' => $request->notes ?? null,
        ]));

        return $this->success(['promotionid' => $promo->id]);
    }

    public function deletePromotion(Request $request)
    {
        $promo = Promotion::find($request->promotionid);
        if (! $promo) {
            return $this->error('Promotion Not Found', 404);
        }
        $promo->delete();

        return $this->success();
    }

    public function getTodoItems(Request $request)
    {
        $query = TodoItem::query();
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        return $this->success(['items' => $query->orderBy('id', 'desc')->get()->toArray()]);
    }

    public function addTodoItem(Request $request)
    {
        $validated = $request->validate(['title' => 'required|string|max:255']);
        $item = TodoItem::create(array_merge($validated, [
            'description' => $request->description ?? null,
            'due_date' => $request->duedate ?? null,
            'admin' => $request->adminusername ?? null,
            'status' => $request->status ?? 'pending',
        ]));

        return $this->success(['itemid' => $item->id]);
    }

    public function updateTodoItem(Request $request)
    {
        $item = TodoItem::find($request->itemid);
        if (! $item) {
            return $this->error('Item Not Found', 404);
        }
        foreach (['title', 'description', 'status', 'due_date', 'admin'] as $f) {
            if ($request->has($f)) {
                $item->$f = $request->$f;
            }
        }
        $item->save();

        return $this->success();
    }

    public function getPaymentMethods()
    {
        $gateways = DB::table('gateway_settings')->select('gateway')->distinct()->pluck('gateway');

        return $this->success(['paymentmethods' => $gateways->toArray()]);
    }

    public function getOrderStatuses()
    {
        return $this->success(['statuses' => OrderStatus::all()->toArray()]);
    }

    public function addBannedIp(Request $request)
    {
        $validated = $request->validate(['ip' => 'required|string', 'reason' => 'nullable|string']);
        BannedIp::create($validated);

        return $this->success();
    }

    public function validateLogin(Request $request)
    {
        $user = User::where('email', $request->email)->first();
        if (! $user || ! Hash::check($request->password2, $user->password)) {
            return $this->error('Invalid credentials', 401);
        }

        return $this->success(['userid' => $user->id]);
    }

    // ===== TODO STATUSES =====
    public function getTodoItemStatuses()
    {
        return $this->success(['statuses' => ['New', 'In Progress', 'Completed', 'Deferred']]);
    }

    // ===== MODULE =====
    public function getModuleQueue(Request $request)
    {
        return $this->success(['queue' => []]);
    }

    public function getModuleConfigParams(Request $request)
    {
        return $this->success(['parameters' => []]);
    }

    public function updateModuleConfig(Request $request)
    {
        return $this->success(['message' => 'Module configuration updated']);
    }

    // ===== PERMISSIONS =====
    public function getPermissionsList()
    {
        return $this->success(['permissions' => ['clients', 'orders', 'invoices', 'tickets', 'services', 'domains', 'servers', 'settings', 'reports', 'addons', 'system']]);
    }

    // ===== NOTIFICATIONS =====
    public function triggerNotification(Request $request)
    {
        return $this->success(['message' => 'Notification triggered']);
    }

    // ===== ENCRYPTION =====
    /**
     * These two ran the application key for whoever asked.
     *
     * Nothing in the application has ever called them, and decryptpassword
     * cannot read what is stored here today - the secrets in the database are
     * written with encryptString, which it does not understand. But it was a
     * standing offer to decrypt anything arriving in the form it does
     * understand, made to anybody holding an API credential, and the key it
     * used is the same key the database is protected with.
     */
    public function encryptPassword(Request $request)
    {
        return $this->error('This installation no longer offers encryption through the API.', 501);
    }

    public function decryptPassword(Request $request)
    {
        return $this->error('This installation no longer offers decryption through the API.', 501);
    }

    // ===== ADMIN NOTES =====
    public function updateAdminNotes(Request $request)
    {
        $client = Client::find($request->clientid);
        if (! $client) {
            return $this->error('Client Not Found', 404);
        }
        $client->notes = $request->notes;
        $client->save();

        return $this->success(['clientid' => $client->id]);
    }

    // ===== EMAIL =====
    /**
     * Said the mail had been queued and queued nothing. Mail is sent by the
     * things that have something to say - an invoice, a ticket reply - and
     * there is no code here to send one to order.
     */
    public function sendEmail(Request $request)
    {
        return $this->error('Sending mail from the API is not implemented.', 501);
    }

    /**
     * Said a reset mail had been sent and sent nothing. The customer area
     * sends them; there is no code here to do it.
     */
    public function resetPassword(Request $request)
    {
        return $this->error('Sending a password reset from the API is not implemented.', 501);
    }

    // ===== MODULE ACTIVATION =====
    public function activateModule(Request $request)
    {
        return $this->error('Activating a module from the API is not implemented.', 501);
    }

    public function deactivateModule(Request $request)
    {
        return $this->error('Deactivating a module from the API is not implemented.', 501);
    }

    // ===== QUOTES =====
    public function getQuotes(Request $request)
    {
        $query = Quote::with('client', 'items');
        if ($request->filled('userid')) {
            $query->where('client_id', $request->userid);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        $quotes = $query->orderBy('id', 'desc')->paginate($request->get('limitnum', 25));

        return $this->paginated($quotes);
    }

    public function createQuote(Request $request)
    {
        $validated = $request->validate(['clientid' => 'required|exists:clients,id', 'valid_until' => 'nullable|date']);
        $quote = Quote::create(['client_id' => $validated['clientid'], 'date' => now()->format('Y-m-d'), 'valid_until' => $validated['valid_until'] ?? now()->addDays(30)->format('Y-m-d'), 'subject' => $request->get('subject', 'Quote'), 'status' => 'draft', 'subtotal' => 0, 'tax' => 0, 'total' => 0]);
        if ($request->has('items')) {
            foreach ((array) $request->items as $item) {
                // Through the same service the panel uses, so the columns are
                // named once. "amount" and "taxed" are the words this endpoint
                // has always taken from callers; the table calls them
                // unit_price and taxable.
                app(QuoteService::class)->addItem($quote, [
                    'description' => $item['description'] ?? '',
                    'quantity' => (int) ($item['quantity'] ?? 1),
                    'unit_price' => (float) ($item['unit_price'] ?? $item['amount'] ?? 0),
                    'discount' => (float) ($item['discount'] ?? 0),
                    'taxable' => (bool) ($item['taxable'] ?? $item['taxed'] ?? false),
                ]);
            }

            $quote->refresh();
        }

        return $this->success(['quoteid' => $quote->id]);
    }

    public function updateQuote(Request $request)
    {
        $quote = Quote::find($request->quoteid);
        if (! $quote) {
            return $this->error('Quote Not Found', 404);
        }
        foreach (['status', 'valid_until', 'notes', 'customer_notes', 'proposal'] as $f) {
            if ($request->has($f)) {
                $quote->$f = $request->$f;
            }
        }
        $quote->save();

        return $this->success(['quoteid' => $quote->id]);
    }

    public function deleteQuote(Request $request)
    {
        $quote = Quote::find($request->quoteid);
        if (! $quote) {
            return $this->error('Quote Not Found', 404);
        }
        $quote->items()->delete();
        $quote->delete();

        return $this->success();
    }

    /**
     * Both of these wrote the status themselves, in lower case, while the rest
     * of the application writes and reads it capitalised and the customer area
     * compares it exactly. A quote sent this way was missing from the
     * customer's list, 404 on its own page and impossible to accept.
     */
    public function sendQuote(Request $request, QuoteService $quotes)
    {
        $quote = Quote::find($request->quoteid);
        if (! $quote) {
            return $this->error('Quote Not Found', 404);
        }

        $quote = $quotes->sendQuote($quote);

        return $this->success(['quoteid' => $quote->id, 'status' => $quote->status]);
    }

    /**
     * Accepting also has to leave the invoice the customer is meant to pay -
     * the customer's own accept button has always done that, this one did not.
     */
    public function acceptQuote(Request $request, QuoteService $quotes)
    {
        $quote = Quote::find($request->quoteid);
        if (! $quote) {
            return $this->error('Quote Not Found', 404);
        }

        if (strtolower((string) $quote->status) === 'accepted') {
            return $this->success(['quoteid' => $quote->id, 'status' => $quote->status]);
        }

        $invoice = $quotes->convertToInvoice($quote);

        return $this->success([
            'quoteid' => $quote->id,
            'status' => $quote->fresh()->status,
            'invoiceid' => $invoice->id,
        ]);
    }

    // ===== PROJECTS =====
    public function getProjects(Request $request)
    {
        $query = Project::with('client', 'tasks', 'messages');
        if ($request->filled('userid')) {
            $query->where('client_id', $request->userid);
        }
        $projects = $query->orderBy('id', 'desc')->paginate($request->get('limitnum', 25));

        return $this->paginated($projects);
    }

    public function getProject(Request $request)
    {
        $project = Project::with('client', 'tasks', 'messages')->find($request->id ?? $request->projectid);
        if (! $project) {
            return $this->error('Project Not Found', 404);
        }

        return $this->success(['project' => $project->toArray()]);
    }

    public function createProject(Request $request)
    {
        $validated = $request->validate(['title' => 'required', 'clientid' => 'required|exists:clients,id']);
        $project = Project::create(['title' => $validated['title'], 'client_id' => $validated['clientid'], 'description' => $request->description, 'status' => $request->get('status', 'active'), 'admin_id' => Admin::first()->id ?? 18080]);

        return $this->success(['projectid' => $project->id]);
    }

    public function updateProject(Request $request)
    {
        $project = Project::find($request->id ?? $request->projectid);
        if (! $project) {
            return $this->error('Project Not Found', 404);
        }
        foreach (['title', 'description', 'status', 'due_date'] as $f) {
            if ($request->has($f)) {
                $project->$f = $request->$f;
            }
        }
        $project->save();

        return $this->success(['projectid' => $project->id]);
    }

    public function addProjectMessage(Request $request)
    {
        $project = Project::find($request->project_id ?? $request->projectid);
        if (! $project) {
            return $this->error('Project Not Found', 404);
        }
        $msg = $project->messages()->create(['message' => $request->message, 'admin_id' => Admin::first()->id ?? 18080]);

        return $this->success(['messageid' => $msg->id]);
    }

    public function addProjectTask(Request $request)
    {
        $project = Project::find($request->project_id ?? $request->projectid);
        if (! $project) {
            return $this->error('Project Not Found', 404);
        }
        $task = $project->tasks()->create(['task' => $request->title ?? $request->task ?? 'Task', 'notes' => $request->description ?? $request->notes, 'completed' => 0]);

        return $this->success(['taskid' => $task->id]);
    }

    public function updateProjectTask(Request $request)
    {
        $task = ProjectTask::find($request->taskid);
        if (! $task) {
            return $this->error('Task Not Found', 404);
        }
        if ($request->has('title') || $request->has('task')) {
            $task->task = $request->title ?? $request->task;
        }
        if ($request->has('description') || $request->has('notes')) {
            $task->notes = $request->description ?? $request->notes;
        }
        if ($request->has('completed')) {
            $task->completed = $request->completed;
        }
        if ($request->has('due_date')) {
            $task->due_date = $request->due_date;
        }
        $task->save();

        return $this->success(['taskid' => $task->id]);
    }

    public function deleteProjectTask(Request $request)
    {
        $task = ProjectTask::find($request->taskid);
        if (! $task) {
            return $this->error('Task Not Found', 404);
        }
        $task->delete();

        return $this->success();
    }

    public function startTaskTimer(Request $request)
    {
        return $this->success(['message' => 'Timer started']);
    }

    public function endTaskTimer(Request $request)
    {
        return $this->success(['message' => 'Timer stopped']);
    }

    // ===== AFFILIATES =====
    public function getAffiliates(Request $request)
    {
        $affiliates = Affiliate::with('client')->paginate($request->get('limitnum', 25));

        return $this->paginated($affiliates);
    }

    public function affiliateActivate(Request $request)
    {
        $client = Client::find($request->clientid);
        if (! $client) {
            return $this->error('Client Not Found', 404);
        }
        $aff = Affiliate::firstOrCreate(['client_id' => $client->id], ['pay_type' => 'percentage', 'pay_amount' => 10, 'balance' => 0]);

        return $this->success(['affiliateid' => $aff->id]);
    }

    // ===== OAUTH =====
    public function listOAuthCredentials(Request $request)
    {
        $creds = ApiCredential::where('active', true)->get(['id', 'identifier', 'description', 'created_at']);

        return $this->success(['credentials' => $creds->toArray()]);
    }

    public function createOAuthCredential(Request $request)
    {
        $plain = Str::random(64);
        $cred = ApiCredential::create(['admin_id' => Admin::first()->id ?? 18080, 'identifier' => Str::random(32), 'secret' => ApiCredential::hashSecret($plain), 'description' => $request->description, 'active' => true]);

        // Return the plaintext secret once — only its hash is stored.
        return $this->success(['credentialid' => $cred->id, 'identifier' => $cred->identifier, 'secret' => $plain]);
    }

    public function updateOAuthCredential(Request $request)
    {
        $cred = ApiCredential::find($request->credentialid);
        if (! $cred) {
            return $this->error('Credential Not Found', 404);
        }
        if ($request->has('description')) {
            $cred->description = $request->description;
        }
        if ($request->has('active')) {
            $cred->active = $request->boolean('active');
        }
        $cred->save();

        return $this->success(['credentialid' => $cred->id]);
    }

    public function deleteOAuthCredential(Request $request)
    {
        $cred = ApiCredential::find($request->credentialid);
        if (! $cred) {
            return $this->error('Credential Not Found', 404);
        }
        $cred->delete();

        return $this->success();
    }
}
