<?php

namespace App\Services;

use App\Models\ConfigOptionSub;
use App\Models\InvoiceItem;
use App\Models\Service;
use App\Models\ServiceConfigOption;
use App\Models\Upgrade;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Raising a service's configurable options (more RAM, a bigger disk, extra
 * IPs) without changing its product.
 *
 * Options could only be chosen when ordering; afterwards the only move was to
 * another product. The change is charged like a product upgrade: the
 * difference in the recurring price, prorated over the days left in the
 * cycle, on an "Upgrade" invoice line. Once paid (ApplyUpgradeListener ->
 * UpgradeService::apply) the options are written to the service, its
 * recurring amount follows, and the module's changePackage() is called - a
 * module that reads the service's options (Proxmox does) resizes the guest.
 *
 * Only raises: no option may go below what the service has now. A smaller
 * disk cannot be given back by the hypervisor, and a lower price would need a
 * credit this does not handle.
 */
class OptionUpgradeService
{
    public const TYPE = 'configoptions';

    public function __construct(private ConfigOptionService $options) {}

    /** The service's current selection in the shape the order form posts. */
    public function current(Service $service): array
    {
        $service->loadMissing('product');
        $raw = [];
        foreach ($this->options->groupsFor($service->product) as $group) {
            foreach ($group->options as $option) {
                $row = ServiceConfigOption::where('service_id', $service->id)->where('config_id', $option->id)->first();
                if (! $row) {
                    continue;
                }
                $raw[$option->id] = $option->isQuantity() ? (int) $row->qty : ($option->isCheckbox() ? 1 : $row->option_id);
            }
        }

        return $raw;
    }

    /** @return array{success: bool, message: ?string, invoice: mixed, applied: bool} */
    public function request(Service $service, array $raw): array
    {
        $refuse = fn (string $m) => ['success' => false, 'message' => $m, 'invoice' => null, 'applied' => false];
        $service->loadMissing('product', 'client');

        if (strtolower((string) $service->status) !== 'active' || ! $service->product) {
            return $refuse(__('client.services.options_not_available'));
        }

        foreach (Upgrade::where('type', self::TYPE)->where('rel_id', $service->id)->where('status', 'pending')->get() as $pending) {
            if (InvoiceItem::where('type', 'Upgrade')->where('rel_id', $pending->id)
                ->whereHas('invoice', fn ($q) => $q->whereIn('status', ['draft', 'unpaid', 'overdue']))->exists()) {
                return $refuse(__('messages.error.upgrade_already_pending'));
            }
            $pending->update(['status' => 'cancelled']);
        }

        $cycle = $service->billing_cycle ?: 'Monthly';
        $current = $this->current($service);
        $normalised = $this->options->normalise($service->product, $raw, $cycle);

        foreach ($normalised as $row) {
            $option = $row['option'];
            $was = $current[$option->id] ?? null;
            $lower = match (true) {
                $was === null => false,
                $option->isQuantity() => $row['qty'] < (int) $was,
                $option->isCheckbox() => false,
                default => $row['unit_price'] < (float) (ConfigOptionSub::find($was)?->priceFor($cycle) ?? 0),
            };
            if ($lower) {
                return $refuse(__('client.services.options_only_raise'));
            }
        }
        // An option that was on and is now missing from the selection is a lowering too.
        $kept = array_map(fn ($r) => $r['option']->id, $normalised);
        if (array_diff(array_keys($current), $kept) !== []) {
            return $refuse(__('client.services.options_only_raise'));
        }

        // Only the options' part of the price moves: a custom or discounted
        // amount the service has is kept, not replaced by the list price.
        $newOptions = $this->options->priceOf($normalised);
        $paidOptions = (float) ServiceConfigOption::where('service_id', $service->id)->get()
            ->sum(fn ($r) => (float) $r->unit_price * max(1, (int) $r->qty));
        $delta = round($newOptions - $paidOptions, 2);
        $newRecurring = round((float) $service->amount + $delta, 2);
        $payload = $this->options->toCartPayload($normalised);

        if ($payload === $this->options->toCartPayload($this->options->normalise($service->product, $current, $cycle))) {
            return $refuse(__('client.services.options_unchanged'));
        }

        $cycleDays = BillingCycleHelper::cycleDays($cycle);
        $due = $service->next_due_date ? Carbon::parse($service->next_due_date)->startOfDay() : now()->startOfDay();
        $remaining = max(0, (int) now()->startOfDay()->diffInDays($due, false));
        $factor = $cycleDays > 0 ? min(1.0, $remaining / $cycleDays) : 1.0;
        $prorated = max(0.0, round($delta * $factor, 2));

        $upgrade = Upgrade::create([
            'client_id' => $service->client_id,
            'type' => self::TYPE,
            'rel_id' => $service->id,
            'original_value' => json_encode($this->options->toCartPayload($this->options->normalise($service->product, $current, $cycle))),
            'new_value' => json_encode(['options' => $payload, 'recurring' => $newRecurring]),
            'amount' => $prorated,
            'status' => 'pending',
        ]);

        if ($prorated > 0.009) {
            $invoice = app(InvoiceService::class)->createInvoice($service->client, [[
                'type' => 'Upgrade',
                'rel_id' => $upgrade->id,
                'description' => __('client.services.options_line', [
                    'options' => $this->options->summarise($payload),
                    'days' => $remaining,
                    'service' => $service->domain ?: $service->product->name,
                ]),
                'amount' => $prorated,
                'taxed' => $service->product->tax ?? true,
            ]], ['notes' => 'Prorated configurable option upgrade.']);

            return ['success' => true, 'message' => null, 'invoice' => $invoice, 'applied' => false];
        }

        // Nothing left to charge for this cycle (the last day): it applies now,
        // and the next renewal is billed at the new amount.
        $this->apply($upgrade);

        return ['success' => true, 'message' => null, 'invoice' => null, 'applied' => true];
    }

    /** Called by UpgradeService::apply() for an upgrade of this type. */
    public function apply(Upgrade $upgrade): array
    {
        $service = Service::with('product')->find($upgrade->rel_id);
        $new = json_decode((string) $upgrade->new_value, true);
        if (! $service || ! is_array($new) || ! isset($new['options'])) {
            return ['success' => false, 'message' => 'Service or options not found.'];
        }

        DB::transaction(function () use ($service, $new, $upgrade) {
            ServiceConfigOption::where('service_id', $service->id)->delete();
            $this->options->attachToService($service, $new['options']);
            $service->update(['amount' => (float) $new['recurring']]);
            $upgrade->update(['status' => 'completed']);
        });

        // Best effort, as for a product change: billing is authoritative once paid.
        $moduleOk = false;
        try {
            $result = app(ProvisioningService::class)->changePackage($service->fresh(), $service->product);
            $moduleOk = (bool) ($result['success'] ?? false);
            if (! $moduleOk) {
                Log::warning('OptionUpgradeService::apply - changePackage did not succeed', ['upgrade' => $upgrade->id, 'result' => $result]);
            }
        } catch (\Throwable $e) {
            Log::error('OptionUpgradeService::apply - changePackage threw: '.$e->getMessage(), ['upgrade' => $upgrade->id]);
        }

        return ['success' => true, 'module' => $moduleOk];
    }
}
