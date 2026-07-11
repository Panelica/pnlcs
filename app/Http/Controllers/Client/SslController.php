<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\SslOrder;
use App\Services\Module\ModuleRegistry;
use App\Services\SslProvisioningService;
use Illuminate\Http\Request;

class SslController extends Controller
{
    public function __construct(
        protected SslProvisioningService $sslService,
        protected ModuleRegistry $registry,
    ) {}

    public function index()
    {
        $orders = SslOrder::where('client_id', $this->getClientId())
            ->with('service.product')
            ->orderByDesc('created_at')
            ->paginate(15);

        return view('client.ssl.index', compact('orders'));
    }

    public function show(SslOrder $sslOrder)
    {
        if ($sslOrder->client_id !== $this->getClientId()) {
            abort(403);
        }

        $sslOrder->load('service.product');
        return view('client.ssl.show', ['order' => $sslOrder]);
    }

    public function configure(SslOrder $sslOrder)
    {
        if ($sslOrder->client_id !== $this->getClientId()) {
            abort(403);
        }

        if ($sslOrder->status !== 'Awaiting Configuration') {
            return redirect()->route('client.ssl.show', $sslOrder)
                ->with('error', __('messages.error.this_certificate_has_already_been_configured'));
        }

        $module = $this->sslService->getModuleForOrder($sslOrder);
        $webServerTypes = $module ? $module->getWebServerTypes() : [];

        return view('client.ssl.configure', [
            'order' => $sslOrder,
            'webServerTypes' => $webServerTypes,
        ]);
    }

    public function submitConfiguration(Request $request, SslOrder $sslOrder)
    {
        if ($sslOrder->client_id !== $this->getClientId()) {
            abort(403);
        }

        $validated = $request->validate([
            'csr' => 'required|string|min:100',
            'webserver_type' => 'required|string',
            'validation_method' => 'required|in:EMAIL,HTTP,DNS',
            'approver_email' => 'required_if:validation_method,EMAIL|nullable|email',
            'admin_first_name' => 'required|string|max:100',
            'admin_last_name' => 'required|string|max:100',
            'admin_email' => 'required|email|max:255',
            'admin_phone' => 'nullable|string|max:50',
            'admin_org' => 'nullable|string|max:255',
            'admin_address' => 'nullable|string|max:255',
            'admin_city' => 'nullable|string|max:100',
            'admin_state' => 'nullable|string|max:100',
            'admin_zip' => 'nullable|string|max:20',
            'admin_country' => 'nullable|string|size:2',
            'domains' => 'nullable|string',
        ]);

        // Extract domain from CSR
        $module = $this->sslService->getModuleForOrder($sslOrder);
        if ($module) {
            $csrDecode = $module->decodeCsr($validated['csr']);
            if (!empty($csrDecode['data']['cn'])) {
                $validated['domain'] = $csrDecode['data']['cn'];
            }
        }

        $result = $this->sslService->submitConfiguration($sslOrder, $validated);

        if ($result['success']) {
            return redirect()->route('client.ssl.show', $sslOrder)
                ->with('success', __('messages.success.ssl_certificate_configuration_submitted_successful'));
        }

        return back()->withInput()->with('error', $result['message']);
    }

    public function getApproverEmails(Request $request, SslOrder $sslOrder)
    {
        if ($sslOrder->client_id !== $this->getClientId()) {
            return response()->json(['emails' => []], 403);
        }

        $domain = $request->get('domain', $sslOrder->domain);
        if (empty($domain)) {
            return response()->json(['emails' => []]);
        }

        $module = $this->sslService->getModuleForOrder($sslOrder);
        $emails = $module ? $module->getApproverEmails($domain) : [];

        return response()->json(['emails' => $emails]);
    }

    public function downloadCert(SslOrder $sslOrder)
    {
        if ($sslOrder->client_id !== $this->getClientId()) {
            abort(403);
        }

        $result = $this->sslService->downloadCertificate($sslOrder);

        if (!$result['success']) {
            return back()->with('error', $result['message']);
        }

        $data = $result['data'];
        $domain = $data['domain'] ?? 'certificate';
        $zip = new \ZipArchive();
        $zipPath = tempnam(sys_get_temp_dir(), 'ssl_') . '.zip';

        if ($zip->open($zipPath, \ZipArchive::CREATE) === true) {
            if ($data['cert']) $zip->addFromString("{$domain}.crt", $data['cert']);
            if ($data['ca_cert']) $zip->addFromString("{$domain}.ca-bundle", $data['ca_cert']);
            if ($data['fullchain']) $zip->addFromString("{$domain}.fullchain.crt", $data['fullchain']);
            if ($data['private_key']) $zip->addFromString("{$domain}.key", $data['private_key']);
            $zip->close();
        }

        return response()->download($zipPath, "{$domain}-ssl.zip")->deleteFileAfterSend();
    }

    public function resendValidation(SslOrder $sslOrder)
    {
        if ($sslOrder->client_id !== $this->getClientId()) {
            abort(403);
        }

        $result = $this->sslService->resendValidation($sslOrder);

        if ($result['success']) {
            return back()->with('success', __('messages.success.validation_email_has_been_resent'));
        }

        return back()->with('error', $result['message']);
    }

    private function getClientId(): int
    {
        return auth()->user()->clients()->first()?->id ?? 0;
    }
}
