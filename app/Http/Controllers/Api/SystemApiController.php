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
use App\Models\ModuleQueue;
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
use App\Constants\Permissions;
use App\Services\Module\ModuleRegistry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class SystemApiController extends BaseApiController
{
    /** What the project screens offer and filter by. */
    private const PROJECT_STATUSES = ['pending', 'in_progress', 'completed', 'cancelled'];

    /** The to-do statuses offered by gettodoitemstatuses and accepted by updatetodoitem. */
    private const TODO_STATUSES = ['New', 'In Progress', 'Completed', 'Deferred'];

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

    /**
     * The entry is written in the caller's name. The actor used to be whatever
     * the request said in "user", so a script could put any member of staff's
     * name on the log - the record that is meant to say who did what. An empty
     * description reached a typed parameter and answered with a 500.
     */
    public function logActivity(Request $request)
    {
        $validated = $request->validate([
            'description' => 'required|string|max:1000',
            'clientid' => 'nullable|integer|exists:clients,id',
        ]);

        ActivityLog::log($validated['description'], $this->callerName(), $validated['clientid'] ?? null);

        return $this->success();
    }

    /** The username of the member of staff the credential answers for. */
    private function callerName(): string
    {
        return (string) (auth('admin')->user()?->username ?? 'API');
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
        $validated = $request->validate(['title' => 'required|string|max:255', 'announcement' => 'required|string', 'published' => 'sometimes|boolean']);
        // "published" was documented and dropped, so an announcement sent with
        // published=0 to be reviewed first went straight onto the site. Left
        // out, it is published - the column's default, as before.
        $a = Announcement::create(['title' => $validated['title'], 'announcement' => $validated['announcement'], 'published' => $request->boolean('published', true)]);

        return $this->success(['announcementid' => $a->id]);
    }

    public function updateAnnouncement(Request $request)
    {
        $a = Announcement::find($request->announcementid);
        if (! $a) {
            return $this->error('Announcement Not Found', 404);
        }
        $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'announcement' => 'sometimes|required|string',
            'published' => 'sometimes|boolean',
        ]);
        foreach (['title', 'announcement'] as $f) {
            if ($request->has($f)) {
                $a->$f = $request->$f;
            }
        }
        if ($request->has('published')) {
            $a->published = $request->boolean('published');
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

    /**
     * The registrar modules installed here, and whether each is offered in the
     * domain search. This listed the names that had a settings row, which left
     * out every installed registrar nobody had opened yet and said nothing
     * about which were switched on.
     */
    public function getRegistrars(ModuleRegistry $registry)
    {
        // Through the model: the values are stored encrypted.
        $visible = \App\Models\RegistrarSettings::where('setting', 'visible')->get()->pluck('value', 'registrar');

        $registrars = collect($registry->getRegistrarModules())
            ->map(function (string $key) use ($registry, $visible) {
                $value = isset($visible[$key]) ? (string) $visible[$key] : null;

                return [
                    'module' => $key,
                    'displayname' => $registry->getRegistrarModule($key)?->getModuleName() ?? ucfirst($key),
                    'active' => $key === 'manual' ? $value !== '0' : $value === '1',
                ];
            })
            ->values();

        return $this->success(['registrars' => $registrars->all()]);
    }

    /**
     * The catalogue, filtered the way the reference screen says it can be: by
     * product id, group id or server module. The filters were documented and
     * ignored, so a caller asking for one product got all of them.
     */
    public function getProducts(Request $request)
    {
        $query = Product::with('group', 'pricing');
        if ($request->filled('pid')) {
            $query->whereKey($request->pid);
        }
        if ($request->filled('gid')) {
            $query->where('group_id', $request->gid);
        }
        if ($request->filled('module')) {
            $query->whereRaw('LOWER(server_type) = ?', [strtolower((string) $request->module)]);
        }

        return $this->success(['products' => $query->get()->toArray()]);
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
        // A date that is not one reached the column and came back as a 500.
        // The status is one of the four gettodoitemstatuses offers; anything
        // else was stored and matched no filter the to-do screen has.
        $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'status' => ['sometimes', 'required', 'string', function ($attribute, $value, $fail) {
                if (! in_array(strtolower((string) $value), array_map('strtolower', self::TODO_STATUSES), true)) {
                    $fail('The status must be one of: '.implode(', ', self::TODO_STATUSES).'.');
                }
            }],
            'due_date' => 'sometimes|nullable|date',
        ]);
        foreach (['title', 'description', 'due_date', 'admin'] as $f) {
            if ($request->has($f)) {
                $item->$f = $request->$f;
            }
        }
        if ($request->has('status')) {
            $item->status = collect(self::TODO_STATUSES)->first(fn ($s) => strtolower($s) === strtolower((string) $request->status));
        }
        $item->save();

        return $this->success();
    }

    /**
     * The gateways a customer can actually pay with - switched on and holding
     * the keys they need. This listed every gateway that had ever had a
     * setting saved, switched off or not, so an integration offered methods
     * checkout would refuse.
     */
    public function getPaymentMethods(ModuleRegistry $registry)
    {
        $methods = collect($registry->usableGateways())
            ->map(fn (string $name) => [
                'module' => $name,
                'displayname' => payment_method_label($name),
            ])
            ->values();

        return $this->success(['totalresults' => $methods->count(), 'paymentmethods' => $methods->all()]);
    }

    public function getOrderStatuses()
    {
        return $this->success(['statuses' => OrderStatus::all()->toArray()]);
    }

    public function addBannedIp(Request $request)
    {
        // Something the ban check can match - a full address, or a prefix
        // ending in * (BlockBannedIp::matches). Anything else was stored,
        // listed as banned, and never blocked anyone.
        $validated = $request->validate([
            'ip' => ['required', 'string', 'max:64', function ($attribute, $value, $fail) {
                $ok = filter_var($value, FILTER_VALIDATE_IP) !== false
                    || preg_match('/^[0-9a-fA-F:.]+\*$/', (string) $value);
                if (! $ok) {
                    $fail('The ip must be an IP address or a prefix ending in *, such as 203.0.113.*');
                }
            }],
            'reason' => 'nullable|string|max:255',
        ]);
        BannedIp::create($validated);

        return $this->success();
    }

    public function validateLogin(Request $request)
    {
        $request->validate(['email' => 'required|email', 'password2' => 'required|string']);

        $user = User::where('email', $request->email)->first();
        if (! $user || ! Hash::check($request->password2, $user->password)) {
            return $this->error('Invalid credentials', 401);
        }

        return $this->success(['userid' => $user->id]);
    }

    // ===== TODO STATUSES =====
    public function getTodoItemStatuses()
    {
        return $this->success(['statuses' => self::TODO_STATUSES]);
    }

    // ===== MODULE =====
    /**
     * The server actions waiting to be retried. It answered an empty list
     * while the queue table held work, so a monitor built on it never saw a
     * provisioning backlog. The payload is left out: a password change carries
     * the new password in it.
     */
    public function getModuleQueue(Request $request)
    {
        $request->validate(['status' => 'nullable|in:pending,completed,failed,cancelled']);

        $query = ModuleQueue::query()
            ->select(['id', 'service_id', 'action', 'status', 'attempts', 'max_attempts', 'next_attempt_at', 'last_error', 'completed_at', 'created_at'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status), fn ($q) => $q->whereIn('status', ['pending', 'failed']));

        return $this->paginated($query->orderBy('id', 'desc')->paginate($this->getPerPage(), ['*'], 'page', $this->getPage()));
    }

    /**
     * The settings a module asks for (WHMCS GetModuleConfigurationParameters):
     * each field's name, label, type and whether it is required - never the
     * stored values.
     */
    public function getModuleConfigParams(Request $request, \App\Services\Module\ModuleSwitchboard $switchboard)
    {
        [$type, $name, $fields] = $this->moduleFields($request, $switchboard);
        if ($fields instanceof \Illuminate\Http\JsonResponse) {
            return $fields;
        }

        $params = collect($fields)->map(fn ($field, $key) => [
            'name' => $field['name'] ?? (is_string($key) ? $key : null),
            'label' => $field['label'] ?? null,
            'type' => $field['type'] ?? 'text',
            'required' => (bool) ($field['required'] ?? false),
            'options' => $field['options'] ?? null,
        ])->filter(fn ($f) => is_string($f['name']))->values();

        return $this->success(['moduleType' => $type, 'moduleName' => $name, 'parameters' => $params->all()]);
    }

    /**
     * Change a module's settings (WHMCS UpdateModuleConfiguration), under the
     * same rules as its settings screen (ModuleSettings): a blank password
     * keeps the stored one, and nothing that is not a string is written.
     */
    public function updateModuleConfig(Request $request, \App\Services\Module\ModuleSwitchboard $switchboard)
    {
        $request->validate([
            'parameters' => 'required|array|max:'.\App\Support\ModuleSettings::MAX_KEYS,
            'parameters.*' => 'nullable|string|max:8192',
        ]);

        [$type, $name, $fields] = $this->moduleFields($request, $switchboard);
        if ($fields instanceof \Illuminate\Http\JsonResponse) {
            return $fields;
        }
        if ($type === 'server') {
            return $this->error('Server modules keep their settings on each server (Setup -> Servers), not on the module.', 422);
        }

        // The permission each settings screen asks for. "manage settings" alone
        // is not enough to rewrite a gateway's live keys in the panel, and must
        // not be here either.
        $needed = match ($type) {
            'gateway' => Permissions::MANAGE_GATEWAYS,
            'registrar' => Permissions::MANAGE_REGISTRARS,
            'ssl' => Permissions::MANAGE_SERVERS,
            'addon' => Permissions::MANAGE_PRODUCTS,
        };
        if (! auth('admin')->user()?->hasPermission($needed)) {
            return $this->error('Your account does not have permission for this action.', 403);
        }

        $settings = \App\Support\ModuleSettings::sanitise(
            (array) $request->input('parameters'),
            \App\Support\ModuleSettings::secretFieldNames($fields)
        );

        foreach ($settings as $key => $value) {
            match ($type) {
                'gateway' => \App\Models\GatewaySettings::updateOrCreate(['gateway' => $name, 'setting' => $key], ['value' => $value]),
                'registrar' => \App\Models\RegistrarSettings::updateOrCreate(['registrar' => $name, 'setting' => $key], ['value' => $value]),
                'ssl' => \App\Models\SslModuleSettings::setSetting($name, $key, $value),
                'addon' => app(\App\Services\AddonManager::class)->saveSettings($name, [$key => $value]),
            };
        }

        return $this->success(['moduleType' => $type, 'moduleName' => $name, 'updated' => array_keys($settings)]);
    }

    /** @return array{0: string, 1: string, 2: array|\Illuminate\Http\JsonResponse} */
    private function moduleFields(Request $request, \App\Services\Module\ModuleSwitchboard $switchboard): array
    {
        $request->validate([
            'moduleType' => ['required', 'string', Rule::in(\App\Services\Module\ModuleSwitchboard::TYPES)],
            'moduleName' => 'required|string|max:100',
        ]);
        $type = (string) $request->moduleType;
        $name = strtolower((string) $request->moduleName);

        if (! $switchboard->exists($type, $name)) {
            return [$type, $name, $this->error('No '.$type.' module named '.$name.' is installed.', 404)];
        }

        $registry = app(ModuleRegistry::class);
        $fields = match ($type) {
            'server' => $registry->getServerModule($name)?->getConfigFields() ?? [],
            'gateway' => $registry->getGatewayModule($name)?->getConfigFields() ?? [],
            'registrar' => $registry->getRegistrarModule($name)?->getConfigFields() ?? [],
            'ssl' => $registry->getSslModule($name)?->getConfigFields() ?? [],
            'addon' => app(\App\Services\AddonManager::class)->find($name)?->config() ?? [],
        };

        return [$type, $name, $fields];
    }

    // ===== PERMISSIONS =====
    /** The permission keys a role can be given - the real list, not a made-up one. */
    public function getPermissionsList()
    {
        return $this->success(['permissions' => Permissions::all()]);
    }

    // ===== NOTIFICATIONS =====
    /**
     * Send a custom notification (WHMCS TriggerNotificationEvent) through the
     * channels the operator set up for the "api.custom" event under Setup ->
     * Notification Channels - email, Slack, a webhook or Telegram.
     */
    public function triggerNotification(Request $request, \App\Services\NotificationService $notifications)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'message' => 'required|string|max:4000',
            'url' => 'nullable|url|max:2048',
            'notification_identifier' => 'nullable|string|max:100',
        ]);

        $rules = \App\Models\NotificationRule::where('event', 'api.custom')->where('active', true)->count();

        $notifications->dispatch('api.custom', [
            'subject' => $validated['title'],
            'message' => $validated['title']."\n\n".$validated['message'].(isset($validated['url']) ? "\n\n".$validated['url'] : ''),
            'identifier' => $validated['notification_identifier'] ?? null,
            'url' => $validated['url'] ?? null,
        ]);

        return $this->success(['event' => 'api.custom', 'rules' => $rules]);
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
     * Send a customer an email (WHMCS SendEmail).
     *
     * Two shapes. messagename names one of the email templates below and id
     * the record it is about; the mail is built exactly as the event that
     * normally sends it builds it, in the customer's language, with their
     * contacts copied as their preferences say. Or customsubject and
     * custommessage write the mail on the spot, to the customer id points at
     * (customtype says whether id is a client, an invoice, a service or a
     * domain). Mail switched off in the settings stays off.
     */
    public function sendEmail(Request $request)
    {
        $request->validate([
            'messagename' => 'required_without:customsubject|nullable|string|max:100',
            'customtype' => 'nullable|in:general,invoice,product,domain',
            'customsubject' => 'required_without:messagename|nullable|string|max:255',
            'custommessage' => 'required_with:customsubject|nullable|string|max:65535',
            'id' => 'required|integer',
        ]);

        if ($request->filled('messagename')) {
            $builders = self::templateMails();
            $name = strtolower(trim((string) $request->messagename));
            if (! isset($builders[$name])) {
                return $this->error('That template cannot be sent on demand. Templates that can: '.implode(', ', array_keys($builders)).'.', 422);
            }

            $built = $builders[$name]((int) $request->id);
            if ($built === null) {
                return $this->error('No record with that id for this template.', 404);
            }
            [$to, $mail] = $built;
        } else {
            $client = match ($request->input('customtype', 'general')) {
                'invoice' => Invoice::find($request->id)?->client,
                'product' => Service::find($request->id)?->client,
                'domain' => Domain::find($request->id)?->client,
                default => Client::find($request->id),
            };
            if (! $client) {
                return $this->error('No customer found for that id.', 404);
            }
            $to = $client->email;
            $mail = new \App\Mail\BulkMassMail((string) $request->customsubject, (string) $request->custommessage, trim($client->first_name.' '.$client->last_name));
        }

        if (! $to) {
            return $this->error('The customer has no email address.', 422);
        }

        \Illuminate\Support\Facades\Mail::to($to)->queue($mail);

        return $this->success(['recipient' => $to]);
    }

    /**
     * The templates sendemail can build from an id - the ones whose mail is
     * about one record. The others need context an id cannot carry (a payment
     * notification, a verification token) and are sent by their own events.
     *
     * @return array<string, \Closure(int): ?array{0: string, 1: \Illuminate\Mail\Mailable}>
     */
    private static function templateMails(): array
    {
        $invoice = fn (int $id) => Invoice::with('client')->find($id);
        $service = fn (int $id) => Service::with('client')->find($id);
        $domain = fn (int $id) => Domain::with('client')->find($id);
        $days = fn ($date) => $date ? (int) now()->startOfDay()->diffInDays(\Illuminate\Support\Carbon::parse($date)->startOfDay(), false) : 0;

        return [
            'invoice created' => fn (int $id) => ($i = $invoice($id)) ? [$i->client?->billingEmail(), new \App\Mail\InvoiceCreatedMail($i)] : null,
            'invoice payment confirmation' => fn (int $id) => ($i = $invoice($id)) ? [$i->client?->billingEmail(), new \App\Mail\InvoicePaidMail($i)] : null,
            'invoice reminder' => fn (int $id) => ($i = $invoice($id)) ? [$i->client?->billingEmail(), new \App\Mail\PaymentReminderMail($i, $days($i->due_date))] : null,
            'invoice overdue' => fn (int $id) => ($i = $invoice($id)) ? [$i->client?->billingEmail(), new \App\Mail\InvoiceOverdueMail($i, max(0, -$days($i->due_date)))] : null,
            'service welcome email' => fn (int $id) => ($v = $service($id)) ? [$v->client?->email, new \App\Mail\ServiceWelcomeMail($v)] : null,
            'service suspension' => fn (int $id) => ($v = $service($id)) ? [$v->client?->email, new \App\Mail\ServiceSuspensionMail($v, (string) ($v->suspension_reason ?? ''))] : null,
            'service unsuspension' => fn (int $id) => ($v = $service($id)) ? [$v->client?->email, new \App\Mail\ServiceUnsuspensionMail($v)] : null,
            'service termination' => fn (int $id) => ($v = $service($id)) ? [$v->client?->email, new \App\Mail\ServiceTerminationMail($v)] : null,
            'order confirmation' => fn (int $id) => ($o = Order::with('client')->find($id)) ? [$o->client?->email, new \App\Mail\OrderConfirmationMail($o)] : null,
            'domain registration confirmation' => fn (int $id) => ($d = $domain($id)) ? [$d->client?->email, new \App\Mail\DomainRegistrationMail($d)] : null,
            'domain renewal reminder' => fn (int $id) => ($d = $domain($id)) ? [$d->client?->email, new \App\Mail\DomainRenewalReminderMail($d, max(0, $days($d->expiry_date)))] : null,
            'account signup email' => fn (int $id) => ($c = Client::find($id)) ? [$c->email, new \App\Mail\AccountSignupMail($c)] : null,
        ];
    }

    /**
     * Email the staff (WHMCS SendAdminEmail): every active member of staff, or
     * with deptid those who handle that support department.
     */
    public function sendAdminEmail(Request $request)
    {
        $validated = $request->validate([
            'customsubject' => 'required|string|max:255',
            'custommessage' => 'required|string|max:65535',
            'deptid' => 'nullable|integer|exists:ticket_departments,id',
        ]);

        $admins = Admin::where('is_disabled', false)->whereNotNull('email')->get()
            ->filter(fn (Admin $a) => ! $request->filled('deptid')
                || in_array((int) $request->deptid, array_map('intval', (array) ($a->support_departments ?? [])), true));

        foreach ($admins as $admin) {
            \Illuminate\Support\Facades\Mail::to($admin->email)->queue(
                new \App\Mail\BulkMassMail($validated['customsubject'], $validated['custommessage'], trim($admin->first_name.' '.$admin->last_name))
            );
        }

        return $this->success(['recipients' => $admins->count()]);
    }

    /**
     * Send a customer login the link to choose a new password, through the
     * same sender the forgot-password form uses. It used to say a mail had
     * been sent and send nothing, then to refuse; the form's code was here to
     * be used all along. Staff are told plainly when there is no such login.
     */
    public function resetPassword(Request $request, \App\Services\PasswordResetSender $sender)
    {
        $request->validate([
            'email' => 'required_without:id|nullable|email',
            'id' => 'required_without:email|nullable|integer',
        ]);

        $email = $request->filled('email')
            ? (string) $request->email
            : (string) User::whereKey($request->id)->value('email');

        if ($email === '' || ! $sender->send($email)) {
            return $this->error('No customer login has that address.', 404);
        }

        return $this->success(['email' => $email]);
    }

    // ===== MODULE ACTIVATION =====
    /**
     * Switch a module on or off - the same switch the Setup -> Modules screen
     * flips, with the same rules (a server or SSL module in use stays on).
     */
    public function activateModule(Request $request, \App\Services\Module\ModuleSwitchboard $switchboard)
    {
        return $this->switchModule($request, $switchboard, true);
    }

    public function deactivateModule(Request $request, \App\Services\Module\ModuleSwitchboard $switchboard)
    {
        return $this->switchModule($request, $switchboard, false);
    }

    private function switchModule(Request $request, \App\Services\Module\ModuleSwitchboard $switchboard, bool $on)
    {
        $validated = $request->validate([
            'moduleType' => ['required', 'string', Rule::in(\App\Services\Module\ModuleSwitchboard::TYPES)],
            'moduleName' => 'required|string|max:100',
        ]);
        $type = $validated['moduleType'];
        $name = strtolower($validated['moduleName']);

        if (! $switchboard->exists($type, $name)) {
            return $this->error('No '.$type.' module named '.$name.' is installed.', 404);
        }

        $result = $switchboard->setActive($type, $name, $on);

        return $result['success']
            ? $this->success(['moduleType' => $type, 'moduleName' => $name, 'active' => $on])
            : $this->error($result['message'], 422);
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
        $quotes = $query->orderBy('id', 'desc')->paginate($this->getPerPage(), ['*'], 'page', $this->getPage());

        return $this->paginated($quotes);
    }

    public function createQuote(Request $request)
    {
        // The documented (WHMCS) names. lineitems in WHMCS is a serialized PHP
        // array; it is not unserialized here - items[] carries the lines.
        $this->alias($request, 'userid', 'clientid');
        $this->alias($request, 'validuntil', 'valid_until');
        $validated = $request->validate(['clientid' => 'required|exists:clients,id', 'valid_until' => 'nullable|date', 'subject' => 'nullable|string|max:255', 'items' => 'nullable|array']);
        $quote = Quote::create(['client_id' => $validated['clientid'], 'date' => now()->format('Y-m-d'), 'valid_until' => $validated['valid_until'] ?? now()->addDays(30)->format('Y-m-d'), 'subject' => $request->get('subject', 'Quote'), 'status' => 'Draft', 'subtotal' => 0, 'tax' => 0, 'total' => 0]);
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
        $this->alias($request, 'validuntil', 'valid_until');
        // The status is compared exactly by the customer area and the quote
        // screens: a lower-case "sent" is the bug sendquote was fixed for,
        // and here it was still open.
        $request->validate([
            'status' => ['sometimes', 'required', Rule::in(['Draft', 'Sent', 'Accepted', 'Declined'])],
            'valid_until' => 'sometimes|nullable|date',
        ]);
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
        $projects = $query->orderBy('id', 'desc')->paginate($this->getPerPage(), ['*'], 'page', $this->getPage());

        return $this->paginated($projects);
    }

    public function getProject(Request $request)
    {
        $project = Project::with('client', 'tasks.timers', 'messages')->find($request->id ?? $request->projectid);
        if (! $project) {
            return $this->error('Project Not Found', 404);
        }

        return $this->success(['project' => $project->toArray()]);
    }

    public function createProject(Request $request)
    {
        // In the caller's name, not the first administrator's, and with a
        // status the project screens know: "active" is not one of them, so
        // every project opened here was missing from every status filter.
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'clientid' => 'required|exists:clients,id',
            'status' => ['nullable', Rule::in(self::PROJECT_STATUSES)],
            'due_date' => 'nullable|date',
        ]);
        $project = Project::create(['title' => $validated['title'], 'client_id' => $validated['clientid'], 'description' => $request->description, 'status' => $validated['status'] ?? 'pending', 'due_date' => $validated['due_date'] ?? null, 'admin_id' => auth('admin')->id()]);

        return $this->success(['projectid' => $project->id]);
    }

    public function updateProject(Request $request)
    {
        $project = Project::find($request->id ?? $request->projectid);
        if (! $project) {
            return $this->error('Project Not Found', 404);
        }
        $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'status' => ['sometimes', 'required', Rule::in(self::PROJECT_STATUSES)],
            'due_date' => 'sometimes|nullable|date',
        ]);
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
        // project_messages keeps the author's name in "admin"; the id this
        // wrote into a column that does not exist was dropped, so every message
        // posted here had no author at all.
        $validated = $request->validate(['message' => 'required|string']);
        $msg = $project->messages()->create(['message' => $validated['message'], 'admin' => $this->callerName()]);

        return $this->success(['messageid' => $msg->id]);
    }

    public function addProjectTask(Request $request)
    {
        $project = Project::find($request->project_id ?? $request->projectid);
        if (! $project) {
            return $this->error('Project Not Found', 404);
        }
        $request->validate([
            'task' => 'required_without:title|nullable|string|max:255',
            'title' => 'required_without:task|nullable|string|max:255',
            'due_date' => 'nullable|date',
        ]);
        $task = $project->tasks()->create(['task' => $request->title ?? $request->task, 'notes' => $request->description ?? $request->notes, 'completed' => 0, 'due_date' => $request->due_date, 'admin' => $this->callerName()]);

        return $this->success(['taskid' => $task->id]);
    }

    public function updateProjectTask(Request $request)
    {
        $task = ProjectTask::find($request->taskid);
        if (! $task) {
            return $this->error('Task Not Found', 404);
        }
        $request->validate([
            'task' => 'sometimes|required|string|max:255',
            'title' => 'sometimes|required|string|max:255',
            'completed' => 'sometimes|boolean',
            'due_date' => 'sometimes|nullable|date',
        ]);
        if ($request->has('title') || $request->has('task')) {
            $task->task = $request->title ?? $request->task;
        }
        if ($request->has('description') || $request->has('notes')) {
            $task->notes = $request->description ?? $request->notes;
        }
        if ($request->has('completed')) {
            $task->completed = $request->boolean('completed');
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

    /**
     * Start timing a project task for the caller (WHMCS StartTaskTimer). One
     * running timer per task and member of staff: a second start would count
     * the same minutes twice.
     */
    public function startTaskTimer(Request $request)
    {
        $request->validate(['taskid' => 'required|integer', 'projectid' => 'nullable|integer']);

        $task = ProjectTask::find($request->taskid);
        if (! $task || ($request->filled('projectid') && (int) $task->project_id !== (int) $request->projectid)) {
            return $this->error('Task Not Found', 404);
        }

        $adminId = auth('admin')->id();
        $running = $task->timers()->where('admin_id', $adminId)->whereNull('ended_at')->first();
        if ($running) {
            return $this->error('A timer is already running for this task (timerid '.$running->id.').', 409);
        }

        $timer = $task->timers()->create(['admin_id' => $adminId, 'started_at' => now()]);

        return $this->success(['timerid' => $timer->id, 'taskid' => $task->id, 'started_at' => $timer->started_at->toIso8601String()]);
    }

    /** Stop a timer (WHMCS EndTaskTimer) and say how long it ran. */
    public function endTaskTimer(Request $request)
    {
        $request->validate(['timerid' => 'required|integer']);

        $timer = \App\Models\ProjectTaskTimer::find($request->timerid);
        if (! $timer) {
            return $this->error('Timer Not Found', 404);
        }
        if ($timer->ended_at === null) {
            $timer->update(['ended_at' => now()]);
        }

        $total = $timer->task->timers()->get()->sum(fn ($t) => $t->seconds());

        return $this->success(['timerid' => $timer->id, 'seconds' => $timer->seconds(), 'task_total_seconds' => $total]);
    }

    // ===== AFFILIATES =====
    public function getAffiliates(Request $request)
    {
        $affiliates = Affiliate::with('client')->orderBy('id', 'desc')->paginate($this->getPerPage(), ['*'], 'page', $this->getPage());

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
        $creds = ApiCredential::where('active', true)->get(['id', 'identifier', 'description', 'allowed_ips', 'created_at']);

        return $this->success(['credentials' => $creds->toArray()]);
    }

    public function createOAuthCredential(Request $request)
    {
        // Owned by the caller, as the staff screen does it. It used to belong to
        // whichever administrator was first in the table - usually the owner of
        // the installation - so anyone allowed to call this could mint a key
        // that answered with the owner's full access.
        $request->validate(['description' => 'nullable|string|max:255']);
        [$allowed, $bad] = \App\Support\IpAllowList::parse($request->input('allowed_ips'));
        if ($allowed === null) {
            return $this->error('Not an IP address or range: '.$bad, 422);
        }
        $plain = Str::random(64);
        $cred = ApiCredential::create(['admin_id' => auth('admin')->id(), 'identifier' => Str::random(32), 'secret' => ApiCredential::hashSecret($plain), 'description' => $request->description, 'allowed_ips' => $allowed ?: null, 'active' => true]);

        // Return the plaintext secret once — only its hash is stored.
        return $this->success(['credentialid' => $cred->id, 'identifier' => $cred->identifier, 'secret' => $plain, 'allowed_ips' => $allowed]);
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
        if ($request->has('allowed_ips')) {
            [$allowed, $bad] = \App\Support\IpAllowList::parse($request->input('allowed_ips'));
            if ($allowed === null) {
                return $this->error('Not an IP address or range: '.$bad, 422);
            }
            $cred->allowed_ips = $allowed ?: null;
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
