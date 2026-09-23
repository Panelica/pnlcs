<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SslOrder;
use App\Services\Module\ModuleRegistry;
use App\Services\SslProvisioningService;
use Illuminate\Http\Request;

class SslApiController extends Controller
{
    public function __construct(
        protected SslProvisioningService $sslService,
    ) {}

    public function getSslOrders(Request $request)
    {
        $query = SslOrder::with(['client', 'service.product']);

        if ($clientId = $request->get('client_id')) {
            $query->where('client_id', $clientId);
        }
        if ($status = $request->get('status')) {
            $query->where('status', $status);
        }

        // limitnum is the name every other list takes; a zero or negative
        // size is not a page.
        $limit = max(1, min((int) $request->get('limitnum', $request->get('limit', 25)), 100));
        $orders = $query->orderByDesc('id')->paginate($limit);

        return response()->json([
            'result' => 'success',
            'totalresults' => $orders->total(),
            'orders' => $orders->items(),
        ]);
    }

    public function getSslOrder(Request $request)
    {
        $id = $request->get('order_id') ?? $request->get('id');
        $order = SslOrder::with(['client', 'service.product'])->find($id);

        if (!$order) {
            return response()->json(['result' => 'error', 'message' => 'SSL order not found'], 404);
        }

        return response()->json([
            'result' => 'success',
            'order' => $order,
        ]);
    }

    public function addSslOrder(Request $request)
    {
        $validated = $request->validate([
            'client_id' => 'required|exists:clients,id',
            // The service has to be this client's, and the module one that is
            // installed: either mismatch left an order nothing could fulfil.
            'service_id' => ['nullable', \Illuminate\Validation\Rule::exists('services', 'id')->where('client_id', $request->input('client_id'))],
            'module' => ['required', 'string', function ($attribute, $value, $fail) {
                if (! app(ModuleRegistry::class)->getSslModule((string) $value)) {
                    $fail('The module is not an SSL module installed here.');
                }
            }],
            'cert_type' => 'nullable|string|max:100',
            // The name the certificate is issued for: anything that is not a
            // hostname was stored and then refused by the provider later.
            'domain' => ['nullable', 'string', 'max:253', self::hostnameRule()],
        ]);

        $order = SslOrder::create([
            'client_id' => $validated['client_id'],
            'service_id' => $validated['service_id'] ?? null,
            'module' => $validated['module'],
            'cert_type' => $validated['cert_type'] ?? null,
            'domain' => $validated['domain'] ?? null,
            'status' => 'Awaiting Configuration',
        ]);

        return response()->json([
            'result' => 'success',
            'order_id' => $order->id,
            'message' => 'SSL order created',
        ]);
    }

    public function configSslOrder(Request $request)
    {
        $order = SslOrder::find($request->input('order_id'));
        if (!$order) {
            return response()->json(['result' => 'error', 'message' => 'SSL order not found'], 404);
        }

        $request->validate([
            'domain' => ['sometimes', 'nullable', 'string', 'max:253', self::hostnameRule()],
            'domains' => 'sometimes|nullable',
            'approver_email' => 'sometimes|nullable|email',
            'admin_email' => 'sometimes|nullable|email',
        ]);

        $config = $request->only([
            'csr', 'webserver_type', 'validation_method', 'approver_email',
            'admin_first_name', 'admin_last_name', 'admin_email', 'admin_phone',
            'admin_org', 'admin_address', 'admin_city', 'admin_state',
            'admin_zip', 'admin_country', 'domain', 'domains',
        ]);

        $result = $this->provider(fn () => $this->sslService->submitConfiguration($order, $config));
        if ($result instanceof \Illuminate\Http\JsonResponse) {
            return $result;
        }

        return response()->json([
            'result' => $result['success'] ? 'success' : 'error',
            'message' => $result['message'],
        ]);
    }

    public function cancelSslOrder(Request $request)
    {
        $order = SslOrder::find($request->input('order_id'));
        if (!$order) {
            return response()->json(['result' => 'error', 'message' => 'SSL order not found'], 404);
        }

        $result = $this->provider(fn () => $this->sslService->revokeCertificate($order, $request->input('reason', '')));
        if ($result instanceof \Illuminate\Http\JsonResponse) {
            return $result;
        }

        return response()->json([
            'result' => $result['success'] ? 'success' : 'error',
            'message' => $result['message'],
        ]);
    }

    public function reissueSslOrder(Request $request)
    {
        $order = SslOrder::find($request->input('order_id'));
        if (!$order) {
            return response()->json(['result' => 'error', 'message' => 'SSL order not found'], 404);
        }

        $csr = $request->input('csr');
        if (empty($csr)) {
            return response()->json(['result' => 'error', 'message' => 'CSR is required'], 422);
        }

        $result = $this->provider(fn () => $this->sslService->reissueCertificate($order, $csr));
        if ($result instanceof \Illuminate\Http\JsonResponse) {
            return $result;
        }

        return response()->json([
            'result' => $result['success'] ? 'success' : 'error',
            'message' => $result['message'],
        ]);
    }

    public function resendSslValidation(Request $request)
    {
        $order = SslOrder::find($request->input('order_id'));
        if (!$order) {
            return response()->json(['result' => 'error', 'message' => 'SSL order not found'], 404);
        }

        $result = $this->provider(fn () => $this->sslService->resendValidation($order));
        if ($result instanceof \Illuminate\Http\JsonResponse) {
            return $result;
        }

        return response()->json([
            'result' => $result['success'] ? 'success' : 'error',
            'message' => $result['message'],
        ]);
    }

    public function getSslApproverEmails(Request $request)
    {
        $domain = $request->get('domain');
        if (empty($domain)) {
            return response()->json(['result' => 'error', 'message' => 'Domain is required'], 422);
        }

        $moduleName = $request->get('module', 'gogetssl');
        $module = app(\App\Services\Module\ModuleRegistry::class)->getSslModule($moduleName);

        if (!$module) {
            return response()->json(['result' => 'error', 'message' => 'SSL module not found'], 404);
        }

        try {
            $emails = $module->getApproverEmails($domain);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning("SSL approver email lookup failed for {$domain}: {$e->getMessage()}");

            return response()->json(['result' => 'error', 'message' => 'The SSL provider could not be reached.'], 502);
        }

        return response()->json([
            'result' => 'success',
            'emails' => $emails,
        ]);
    }

    /**
     * Run a call that reaches the SSL provider.
     *
     * The provisioning service lets the provider's errors through, so an
     * unreachable or failing provider answered the API caller with a 500 and
     * a stack trace in the log instead of a message.
     */
    private function provider(\Closure $call): array|\Illuminate\Http\JsonResponse
    {
        try {
            return $call();
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('SSL provider call failed: '.$e->getMessage());

            return response()->json(['result' => 'error', 'message' => 'The SSL provider could not be reached.'], 502);
        }
    }

    /** A hostname, wildcard allowed (*.example.com) - what a certificate is issued for. */
    private static function hostnameRule(): \Closure
    {
        return function ($attribute, $value, $fail) {
            if ($value === null || $value === '') {
                return;
            }
            $name = preg_replace('/^\*\./', '', (string) $value);
            if (! filter_var($name, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) || ! str_contains($name, '.')) {
                $fail('The '.$attribute.' must be a domain name.');
            }
        };
    }
}
