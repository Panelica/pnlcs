<?php

namespace App\Listeners;

use App\Events\InvoicePaid;
use App\Models\Upgrade;
use App\Services\UpgradeService;
use Illuminate\Support\Facades\Log;

/**
 * When an invoice carrying an "Upgrade" line item is paid, apply the pending
 * product upgrade it refers to (module ChangePackage + repoint service).
 */
class ApplyUpgradeListener
{
    public function __construct(private UpgradeService $upgrades) {}

    public function handleInvoicePaid(InvoicePaid $event): void
    {
        $items = $event->invoice->items->where('type', 'Upgrade');

        foreach ($items as $item) {
            $upgrade = Upgrade::find($item->rel_id);
            if (!$upgrade || $upgrade->status === 'completed') {
                continue;
            }

            try {
                $this->upgrades->apply($upgrade);
                Log::info("ApplyUpgrade: upgrade #{$upgrade->id} applied after invoice #{$event->invoice->id} paid");
            } catch (\Throwable $e) {
                Log::error("ApplyUpgrade failed for upgrade #{$upgrade->id}: " . $e->getMessage());
            }
        }
    }
}
