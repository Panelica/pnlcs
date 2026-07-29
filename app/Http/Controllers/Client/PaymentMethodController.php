<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\ResolvesClient;
use App\Models\PaymentMethod;
use Illuminate\Http\Request;

class PaymentMethodController extends Controller
{
    use ResolvesClient;

    public function index()
    {
        $methods = PaymentMethod::where('client_id', $this->getClientId())
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->get();

        return view('client.payment-methods.index', compact('methods'));
    }

    /**
     * Store a bank account reference (no secrets — account holder + masked
     * digits only). Card storage requires a tokenising gateway and is added
     * per-gateway.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'description'  => ['required', 'string', 'max:255'],
            'payment_type' => ['required', 'in:BankAccount'],
            'last_four'    => ['nullable', 'digits:4'],
        ]);

        PaymentMethod::create([
            'client_id'    => $this->getClientId(),
            'description'  => $validated['description'],
            'gateway_name' => 'banktransfer',
            'payment_type' => $validated['payment_type'],
            'last_four'    => $validated['last_four'] ?? null,
        ]);

        return back()->with('success', __('client.payment_methods.added'));
    }

    public function setDefault(PaymentMethod $paymentMethod)
    {
        abort_if($paymentMethod->client_id !== $this->getClientId(), 403);

        PaymentMethod::where('client_id', $this->getClientId())->update(['is_default' => false]);
        $paymentMethod->update(['is_default' => true]);

        return back()->with('success', __('client.payment_methods.default_updated'));
    }

    public function destroy(PaymentMethod $paymentMethod)
    {
        abort_if($paymentMethod->client_id !== $this->getClientId(), 403);

        $paymentMethod->delete();

        return back()->with('success', __('client.payment_methods.removed'));
    }

}
