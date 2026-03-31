<?php
namespace App\Http\Controllers\Client;
use App\Http\Controllers\Controller;
use App\Models\GatewaySettings;
use App\Models\Invoice;
use App\Services\Module\ModuleRegistry;

class InvoiceController extends Controller
{
    public function index() {
        $invoices = Invoice::with("items")->where("client_id", $this->getClientId())->orderBy("id","desc")->paginate(25);
        return view("client.invoices.index", compact("invoices"));
    }

    public function show(Invoice $invoice) {
        abort_if($invoice->client_id !== $this->getClientId(), 403);
        $invoice->load("items");

        // Gather available gateways
        $gateways = GatewaySettings::where("setting", "active")
            ->where("value", "1")
            ->pluck("gateway")
            ->unique()
            ->values()
            ->toArray();

        // Fallback: if no active gateways configured, show bank transfer
        if (empty($gateways)) {
            $gateways = ["banktransfer"];
        }

        // Build payment forms for unpaid invoices
        $gatewayForms = [];
        $gatewayLabels = [];
        if (in_array(strtolower($invoice->status), ["unpaid", "overdue"])) {
            $registry = app(ModuleRegistry::class);
            foreach ($gateways as $gw) {
                $module = $registry->getGatewayModule($gw);
                if ($module) {
                    try {
                        $gatewayForms[$gw] = $module->getPaymentForm($invoice);
                        $gatewayLabels[$gw] = $module->getModuleName();
                    } catch (\Throwable $e) {
                        $gatewayLabels[$gw] = ucfirst($gw);
                    }
                } else {
                    $gatewayLabels[$gw] = ucwords(str_replace("_", " ", $gw));
                }
            }
        }

        return view("client.invoices.show", compact("invoice", "gateways", "gatewayForms", "gatewayLabels"));
    }

    private function getClientId() { return auth()->user()->clients()->first()?->id ?? 0; }
}
