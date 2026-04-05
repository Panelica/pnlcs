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

        $limit = min((int) $request->get('limit', 25), 100);
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
            'service_id' => 'nullable|exists:services,id',
            'module' => 'required|string',
            'cert_type' => 'nullable|string',
            'domain' => 'nullable|string',
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

        $config = $request->only([
            'csr', 'webserver_type', 'validation_method', 'approver_email',
            'admin_first_name', 'admin_last_name', 'admin_email', 'admin_phone',
            'admin_org', 'admin_address', 'admin_city', 'admin_state',
            'admin_zip', 'admin_country', 'domain', 'domains',
        ]);

        $result = $this->sslService->submitConfiguration($order, $config);

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

        $result = $this->sslService->revokeCertificate($order, $request->input('reason', ''));

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

        $result = $this->sslService->reissueCertificate($order, $csr);

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

        $result = $this->sslService->resendValidation($order);

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

        $emails = $module->getApproverEmails($domain);

        return response()->json([
            'result' => 'success',
            'emails' => $emails,
        ]);
    }
}
