<?php

namespace Modules\Servers\PanelicaLicense\Http;

use App\Models\Service;
use Illuminate\Http\Request;
use Modules\Servers\PanelicaLicense\PanelicaLicenseModule;

/** Staff: tie a license that already exists at Central to a service. */
class AttachLicenseController
{
    public function __invoke(Request $request, Service $service)
    {
        $request->validate(['license_id' => ['required', 'string', 'max:64']]);

        abort_unless(strtolower((string) $service->product?->server_type) === PanelicaLicenseModule::NAME, 404);

        $result = app(PanelicaLicenseModule::class)->attachLicense($service, (string) $request->input('license_id'));

        return back()->with(($result['success'] ?? false) ? 'success' : 'error', (string) ($result['message'] ?? ''));
    }
}
