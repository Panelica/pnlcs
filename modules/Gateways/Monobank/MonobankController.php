<?php

declare(strict_types=1);

namespace Modules\Gateways\Monobank;

use App\Models\Invoice;
use App\Services\Module\ModuleRegistry;
use Illuminate\Http\Request;

final class MonobankController
{
    /** Authenticated, owner-checked checkout. Only the module decides the amount; we only choose where to redirect. */
    public function capture(Request $request, Invoice $invoice)
    {
        // Same rule as the core's authoriseInvoice(): the user must belong to the client that owns the invoice.
        $clientIds = $request->user()?->clients()->pluck('clients.id');
        abort_unless(MonobankModule::ownsInvoice($clientIds, $invoice->client_id), 403);

        $registry = app(ModuleRegistry::class);
        abort_unless(in_array('monobank', $registry->usableGateways(), true), 404); // switched on and configured
        $module = $registry->getGatewayModule('monobank');
        abort_unless($module instanceof MonobankModule, 404);

        $result = $module->capture($invoice, (float) $invoice->amountDue());
        if (!($result['success'] ?? false)) {
            return back()->withErrors(['payment' => $result['message'] ?? 'Payment unavailable']);
        }
        $checkout = $result['checkout_url'] ?? '';
        abort_unless(is_string($checkout) && MonobankModule::isAllowedCheckoutUrl($checkout), 502);
        return redirect()->away($checkout);
    }

    /** Public webhook. Exact raw body and headers go to the module, which verifies the signature first. */
    public function webhook(Request $request)
    {
        // Deliberately NOT gated on "usable": a payment already made must still be credited if an admin toggles the gateway.
        $module = app(ModuleRegistry::class)->getGatewayModule('monobank');
        if (!$module instanceof MonobankModule) {
            $module = new MonobankModule();
        }
        $reply = $module->handleWebhookRequest([
            '_raw_payload' => $request->getContent(),
            '_headers' => array_change_key_case($request->headers->all(), CASE_LOWER),
        ]);
        return response($reply['body'], $reply['status']);
    }
}
