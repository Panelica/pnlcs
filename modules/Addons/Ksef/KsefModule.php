<?php

namespace Modules\Addons\Ksef;

use App\Contracts\AddonModuleInterface;
use App\Models\KsefInvoice;
use Illuminate\Http\Request;
use Modules\Ksef\KsefSettings;

/**
 * KSeF (Krajowy System e-Faktur) addon.
 *
 * Paid invoices are handed to the Polish national e-invoicing system; the
 * addon screen lists what has been sent, its acceptance state and offers a
 * retry for failed submissions and a correction marker.
 */
class KsefModule implements AddonModuleInterface
{
    public function getName(): string { return 'ksef'; }

    public function getDisplayName(): string { return __('messages.ksef.settings_title'); }

    public function getDescription(): string { return __('messages.ksef.addon_description'); }

    public function getVersion(): string { return '1.0.0'; }

    public function getAuthor(): string { return 'PNLCS'; }

    public function activate(): array
    {
        return ['success' => true, 'message' => __('messages.ksef.activated')];
    }

    public function deactivate(): array
    {
        return ['success' => true, 'message' => __('messages.ksef.deactivated')];
    }

    public function config(): array
    {
        return KsefSettings::fields();
    }

    public function sidebar(): array
    {
        return [];
    }

    public function upgrade(string $fromVersion): array
    {
        return ['success' => true, 'message' => 'KSeF upgraded from '.e($fromVersion)];
    }

    public function output(Request $request): string
    {
        $html = '<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">'
            .'<p style="font-size:13px;color:var(--pn-muted);margin:0;">'.__('messages.ksef.addon_output_hint').'</p>'
            .'<form method="POST" action="'.route('admin.ksef.test').'" style="margin:0;">'
            .'<input type="hidden" name="_token" value="'.csrf_token().'">'
            .'<button type="submit" class="btn btn-success btn-sm" style="font-size:13px;padding:6px 14px;font-weight:600;">'.__('messages.ksef.test').'</button>'
            .'</form>'
            .'</div>';

        $records = KsefInvoice::with(['invoice.client', 'correction'])
            ->orderByDesc('id')
            ->paginate(20);

        if ($records->isEmpty()) {
            return $html.'<p style="color:var(--pn-muted);font-size:13px;">'.__('messages.ksef.none_yet').'</p>';
        }

        $html .= '<table style="width:100%;font-size:13px;border-collapse:collapse;">';
        $html .= '<tr>'
            .'<th style="padding:8px;text-align:left;color:var(--pn-muted);border-bottom:1px solid var(--border);">'.__('messages.ksef.invoice').'</th>'
            .'<th style="padding:8px;text-align:left;color:var(--pn-muted);border-bottom:1px solid var(--border);">'.__('messages.ksef.client').'</th>'
            .'<th style="padding:8px;text-align:right;color:var(--pn-muted);border-bottom:1px solid var(--border);">'.__('messages.ksef.total').'</th>'
            .'<th style="padding:8px;text-align:left;color:var(--pn-muted);border-bottom:1px solid var(--border);">'.__('messages.ksef.status').'</th>'
            .'<th style="padding:8px;text-align:left;color:var(--pn-muted);border-bottom:1px solid var(--border);">'.__('messages.ksef.ksef_number').'</th>'
            .'<th style="padding:8px;text-align:left;color:var(--pn-muted);border-bottom:1px solid var(--border);">'.__('messages.ksef.sent_at').'</th>'
            .'<th style="padding:8px;text-align:right;color:var(--pn-muted);border-bottom:1px solid var(--border);">'.__('messages.ksef.actions').'</th>'
            .'</tr>';

        foreach ($records as $r) {
            $status = $this->statusBadge($r->status);
            $actions = $this->actions($r);

            $invoiceLink = $r->invoice
                ? '<a href="'.route('admin.invoices.show', $r->invoice).'" style="color:#337ab7;">#'.e($r->invoice->invoice_num ?? $r->invoice->id).'</a>'
                : '—';
            $client = $r->invoice?->client?->display_name ?? '—';
            $total = $r->invoice ? money_fmt($r->invoice->total) : '—';

            $html .= '<tr>'
                .'<td style="padding:8px;">'.$invoiceLink.'</td>'
                .'<td style="padding:8px;">'.e($client).'</td>'
                .'<td style="padding:8px;text-align:right;">'.$total.'</td>'
                .'<td style="padding:8px;">'.$status.'</td>'
                .'<td style="padding:8px;">'.e($r->ksef_number ?? '—').'</td>'
                .'<td style="padding:8px;">'.($r->sent_at ? $r->sent_at->format(date_fmt().' H:i') : '—').'</td>'
                .'<td style="padding:8px;text-align:right;white-space:nowrap;">'.$actions.'</td>'
                .'</tr>';

            if ($r->error_message) {
                $html .= '<tr><td colspan="7" style="padding:0 8px 8px;color:#c43c35;font-size:12px;">'.e($r->error_message).'</td></tr>';
            }
        }

        $html .= '</table>';
        $html .= $records->links();

        return $html;
    }

    protected function statusBadge(string $status): string
    {
        $map = [
            'pending' => ['label' => __('messages.ksef.status_pending'), 'color' => '#f59e0b'],
            'sent' => ['label' => __('messages.ksef.status_sent'), 'color' => '#337ab7'],
            'accepted' => ['label' => __('messages.ksef.status_accepted'), 'color' => '#46a546'],
            'rejected' => ['label' => __('messages.ksef.status_rejected'), 'color' => '#c43c35'],
            'error' => ['label' => __('messages.ksef.status_error'), 'color' => '#c43c35'],
            'corrected' => ['label' => __('messages.ksef.status_corrected'), 'color' => '#6c757d'],
        ];
        $m = $map[$status] ?? ['label' => $status, 'color' => '#6c757d'];

        return '<span style="display:inline-block;padding:2px 8px;border-radius:999px;background:'.$m['color'].'1a;color:'.$m['color'].';font-weight:600;font-size:12px;">'.e($m['label']).'</span>';
    }

    protected function actions(KsefInvoice $r): string
    {
        $html = '';

        if (in_array($r->status, ['pending', 'error', 'rejected'], true)) {
            $html .= '<form method="POST" action="'.route('admin.ksef.resend', $r).'" style="display:inline;margin:0;">'
                .'<input type="hidden" name="_token" value="'.csrf_token().'">'
                .'<button type="submit" class="btn btn-sm btn-outline" style="font-size:12px;padding:3px 10px;">'.__('messages.ksef.resend').'</button>'
                .'</form> ';
        }

        if (in_array($r->status, ['sent', 'accepted'], true)) {
            $html .= '<form method="POST" action="'.route('admin.ksef.mark-corrected', $r).'" style="display:inline;margin:0;" onsubmit="return confirm(\''.__('messages.ksef.confirm_corrected').'\');">'
                .'<input type="hidden" name="_token" value="'.csrf_token().'">'
                .'<button type="submit" class="btn btn-sm btn-outline" style="font-size:12px;padding:3px 10px;">'.__('messages.ksef.mark_corrected').'</button>'
                .'</form>';
        }

        return $html ?: '—';
    }
}
