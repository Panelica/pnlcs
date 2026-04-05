<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SslOrder;
use App\Services\SslProvisioningService;
use Illuminate\Http\Request;

class SslOrderController extends Controller
{
    public function __construct(
        protected SslProvisioningService $sslService,
    ) {}

    public function index(Request $request)
    {
        $query = SslOrder::with(['client', 'service.product']);

        if ($status = $request->get('status')) {
            $query->where('status', $status);
        }
        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('domain', 'like', "%{$search}%")
                   ->orWhereHas('client', fn($c) => $c->where('first_name', 'like', "%{$search}%")->orWhere('last_name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%"));
            });
        }

        $orders = $query->orderByDesc('created_at')->paginate(25);

        return view('admin.ssl.index', compact('orders'));
    }

    public function show(SslOrder $sslOrder)
    {
        $sslOrder->load(['client', 'service.product']);
        return view('admin.ssl.show', ['order' => $sslOrder]);
    }

    public function moduleAction(Request $request, SslOrder $sslOrder)
    {
        $action = $request->input('action');

        $result = match ($action) {
            'poll' => $this->sslService->pollCertificateStatus($sslOrder),
            'revoke' => $this->sslService->revokeCertificate($sslOrder, $request->input('reason', '')),
            'reissue' => $this->sslService->reissueCertificate($sslOrder, $request->input('csr', '')),
            'resend' => $this->sslService->resendValidation($sslOrder),
            'renew' => $this->sslService->renewCertificate($sslOrder),
            default => ['success' => false, 'message' => 'Unknown action'],
        };

        if ($result['success']) {
            return redirect()->route('admin.ssl.show', $sslOrder)->with('success', $result['message']);
        }

        return redirect()->route('admin.ssl.show', $sslOrder)->with('error', $result['message']);
    }

    public function downloadCert(SslOrder $sslOrder)
    {
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
}
