<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Service;
use App\Services\OrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OrderController extends Controller
{
    public function __construct(
        protected OrderService $orderService
    ) {}

    public function index(Request $request): View
    {
        $query = Order::with('client');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $orders = $query->orderBy('created_at', 'desc')->paginate(25);

        return view('admin.orders.index', compact('orders'));
    }

    /**
     * Place an order for a customer.
     *
     * Staff could accept, cancel and delete orders but not raise one: a sale
     * agreed on the phone meant logging in as the customer, or the API. The
     * order goes through OrderService exactly as the shop's and the API's do,
     * so the invoice, the confirmation mail and the provisioning are the same.
     */
    public function create(Request $request): View
    {
        $client = $request->filled('client') ? $this->findClient((string) $request->input('client')) : null;

        return view('admin.orders.create', [
            'client' => $client,
            'products' => \App\Models\Product::with('group:id,name', 'pricing')
                ->where('hidden', false)->where('retired', false)
                ->orderBy('group_id')->orderBy('name')->get(),
            'gateways' => app(\App\Services\Module\ModuleRegistry::class)->usableGateways(),
            'cycles' => ['monthly', 'quarterly', 'semiannually', 'annually', 'biennially', 'triennially'],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $v = $request->validate([
            'client' => 'required|string|max:255',
            'product_id' => 'required|exists:products,id',
            'billing_cycle' => 'required|in:monthly,quarterly,semiannually,annually,biennially,triennially',
            'domain' => 'nullable|string|max:255',
            'price' => 'nullable|numeric|min:0',
            'payment_method' => ['required', 'string', function ($attribute, $value, $fail) {
                if (! in_array((string) $value, app(\App\Services\Module\ModuleRegistry::class)->usableGateways(), true)) {
                    $fail(__('admin.orders.new_gateway_unusable'));
                }
            }],
            'promo_code' => 'nullable|string|max:255',
            'accept' => 'nullable|boolean',
        ]);

        $client = $this->findClient($v['client']);
        if (! $client) {
            return back()->withInput()->withErrors(['client' => __('admin.orders.new_client_unknown')]);
        }

        // The client's own currency, as their invoice is; a cycle the product
        // is not sold on is refused rather than billed at nothing, unless
        // staff set the price themselves.
        $product = \App\Models\Product::findOrFail($v['product_id']);
        $listed = $product->priceFor($v['billing_cycle'], $client->currency_id ?: null);
        $price = isset($v['price']) && $v['price'] !== '' ? (float) $v['price'] : $listed;
        if ($price === null) {
            return back()->withInput()->withErrors(['billing_cycle' => __('client.cart.cycle_unavailable')]);
        }

        $order = $this->orderService->processOrder($client, [[
            'type' => 'service',
            'product_id' => $product->id,
            'domain' => trim((string) ($v['domain'] ?? '')),
            'billing_cycle' => $v['billing_cycle'],
            'amount' => (float) $price,
        ]], (string) $v['payment_method'], $v['promo_code'] ?? null);

        \App\Models\ActivityLog::log('Order '.$order->order_num.' placed by staff', auth('admin')->user()?->username, $client->id, $order->invoice_id);

        // Accepting here is the same as the Accept button: services are set
        // up whatever the product's own setting, the invoice stays as it is.
        if ($request->boolean('accept')) {
            $order = $this->orderService->acceptOrder($order->fresh(), manual: true);
        }

        return redirect()->route('admin.orders.show', $order)->with('success', __('admin.orders.new_placed', ['number' => $order->order_num]));
    }

    private function findClient(string $input): ?\App\Models\Client
    {
        $input = trim($input);

        return ctype_digit($input) ? \App\Models\Client::find((int) $input) : \App\Models\Client::where('email', $input)->first();
    }

    public function show(Order $order): View
    {
        $order->load('client', 'services', 'domains', 'invoice');

        return view('admin.orders.show', compact('order'));
    }

    /**
     * Accept a pending order and activate its services.
     */
    public function accept(Order $order): RedirectResponse
    {
        if ($order->status === OrderStatus::Active->value) {
            return back()->with('info', __('admin.messages.order_already_active'));
        }

        if ($order->status !== OrderStatus::Pending->value) {
            return back()->with('error', __('admin.messages.order_pending_error', ['status' => $order->status]));
        }

        $this->orderService->acceptOrder($order, manual: true);

        return back()->with('success', __('admin.messages.order_accepted', ['num' => $order->order_num]));
    }

    /**
     * Accept or cancel several orders at once from the list, each the way its
     * own button does it: only a pending order is accepted, an order already
     * cancelled or marked fraud is left alone. One that fails is counted and
     * logged, and the rest still go through.
     */
    public function bulk(Request $request): RedirectResponse
    {
        $v = $request->validate([
            'order_ids' => 'required|array|min:1|max:200',
            'order_ids.*' => 'integer',
            'action' => 'required|in:accept,cancel',
        ]);

        $done = 0;
        $skipped = 0;
        foreach (Order::whereIn('id', $v['order_ids'])->get() as $order) {
            $eligible = $v['action'] === 'accept'
                ? $order->status === OrderStatus::Pending->value
                : ! in_array($order->status, [OrderStatus::Cancelled->value, OrderStatus::Fraud->value], true);
            if (! $eligible) {
                $skipped++;

                continue;
            }
            try {
                $v['action'] === 'accept'
                    ? $this->orderService->acceptOrder($order, manual: true)
                    : $this->orderService->cancelOrder($order);
                $done++;
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::error("Bulk order {$v['action']} failed for order #{$order->order_num}: {$e->getMessage()}");
                $skipped++;
            }
        }

        return back()->with($done > 0 ? 'success' : 'info', __('admin.orders.bulk_done_'.$v['action'], ['done' => $done, 'skipped' => $skipped]));
    }

    /**
     * Cancel an order and terminate related services.
     */
    public function cancel(Order $order): RedirectResponse
    {
        if (in_array($order->status, [OrderStatus::Cancelled->value, OrderStatus::Fraud->value])) {
            return back()->with('info', __('admin.messages.order_already_cancelled'));
        }

        $this->orderService->cancelOrder($order);

        return back()->with('success', __('admin.messages.order_cancelled', ['num' => $order->order_num]));
    }

    /**
     * Mark an order as fraud and suspend related services.
     */
    public function markFraud(Order $order): RedirectResponse
    {
        if ($order->status === OrderStatus::Fraud->value) {
            return back()->with('info', __('admin.messages.order_already_fraud'));
        }

        $this->orderService->markFraud($order);

        return back()->with('success', __('admin.messages.order_fraud', ['num' => $order->order_num]));
    }

    /**
     * Delete (soft-cancel) an order and all related items.
     */
    /**
     * Correct the domain on a service before it is provisioned. Once the panel
     * holds an account for it (username set) or it is past pending, changing the
     * domain here would desync the panel from the billing record, so it is only
     * allowed while the service is still pending and unprovisioned.
     */
    public function updateServiceDomain(Request $request, Order $order, Service $service): RedirectResponse
    {
        if ($service->order_id !== $order->id) {
            abort(404);
        }

        if (strtolower((string) $service->status) !== 'pending' || ! empty($service->username)) {
            return back()->with('error', __('admin.orders.domain_edit_locked'));
        }

        $data = $request->validate([
            'domain' => ['required', 'string', 'max:253', 'regex:/^(?=.{1,253}$)([a-z0-9](-?[a-z0-9])*\\.)+[a-z]{2,}$/i'],
        ]);

        $service->update(['domain' => strtolower(trim($data['domain']))]);

        return back()->with('success', __('admin.orders.domain_updated'));
    }

    public function delete(Order $order): RedirectResponse
    {
        $orderNum = $order->order_num;

        $this->orderService->deleteOrder($order);

        return redirect()
            ->route('admin.orders.index')
            ->with('success', __('admin.messages.order_deleted', ['num' => $orderNum]));
    }
}
