<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Service;
use App\Services\InvoiceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class BulkActionController extends Controller
{
    public function massEmailForm()
    {
        $clients = Client::orderBy("first_name")->get();
        return view("admin.bulk.mass-email", compact("clients"));
    }

    public function massEmail(Request $request)
    {
        $validated = $request->validate([
            "client_ids" => "required|array|min:1",
            "client_ids.*" => "exists:clients,id",
            "subject" => "required|string|max:255",
            "message" => "required|string",
        ]);

        $clients = Client::whereIn("id", $validated["client_ids"])->get();
        $sent = 0;

        foreach ($clients as $client) {
            if (!$client->email) continue;
            try {
                Mail::raw($validated["message"], function ($mail) use ($client, $validated) {
                    $mail->to($client->email)->subject($validated["subject"]);
                });
                $sent++;
            } catch (\Throwable $e) {
                Log::error("Bulk email failed for client #{$client->id}: " . $e->getMessage());
            }
        }

        return back()->with("success", __("admin.messages.emails_sent", ["count" => $sent]));
    }

    public function bulkInvoice(Request $request, InvoiceService $invoiceService)
    {
        $validated = $request->validate([
            "client_ids" => "required|array|min:1",
            "client_ids.*" => "exists:clients,id",
            "description" => "required|string|max:255",
            "amount" => "required|numeric|min:0.01",
            "due_date" => "required|date|after:today",
        ]);

        $created = 0;

        foreach ($validated["client_ids"] as $clientId) {
            $client = Client::find($clientId);
            if (!$client) continue;
            try {
                $invoiceService->createInvoice($client, [
                    ["description" => $validated["description"], "amount" => $validated["amount"], "qty" => 1],
                ], ["due_date" => $validated["due_date"]]);
                $created++;
            } catch (\Throwable $e) {
                Log::error("Bulk invoice failed for client #{$clientId}: " . $e->getMessage());
            }
        }

        return back()->with("success", __("admin.messages.invoices_created", ["count" => $created]));
    }

    public function bulkServiceUpdate(Request $request)
    {
        $validated = $request->validate([
            "service_ids" => "required|array|min:1",
            "service_ids.*" => "exists:services,id",
            "status" => "required|in:active,suspended,terminated,cancelled",
        ]);

        $updated = Service::whereIn("id", $validated["service_ids"])->update(["status" => $validated["status"]]);

        return back()->with("success", __("admin.messages.services_updated", ["count" => $updated, "status" => $validated["status"]]));
    }
}
