<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Client;
use App\Models\Credit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Add credit to a client's balance or take it away, with a reason.
 *
 * The balance was shown on the client page and nothing in the admin area
 * could change it: a goodwill credit, a refund to balance or a correction
 * meant calling the API's AddCredit (which can only add) or editing the
 * database.
 */
class ClientCreditController extends Controller
{
    public function index(Client $client)
    {
        return view('admin.clients.credit', [
            'client' => $client,
            'history' => Credit::where('client_id', $client->id)->latest('id')->paginate(25),
        ]);
    }

    public function store(Request $request, Client $client)
    {
        $v = $request->validate([
            'type' => 'required|in:add,remove',
            'amount' => 'required|numeric|min:0.01|max:1000000',
            'description' => 'required|string|max:255',
        ]);
        $amount = round((float) $v['amount'], 2);
        $admin = auth('admin')->user();

        // The ledger line and the balance move together, against a locked row,
        // so two people taking credit at once cannot both pass the check.
        $refused = DB::transaction(function () use ($client, $v, $amount, $admin) {
            $locked = Client::whereKey($client->id)->lockForUpdate()->first();
            if ($v['type'] === 'remove' && $amount > (float) $locked->credit) {
                return (float) $locked->credit;
            }

            $signed = $v['type'] === 'add' ? $amount : -$amount;
            Credit::create(['client_id' => $locked->id, 'admin_id' => $admin?->id, 'date' => now()->format('Y-m-d'),
                'description' => $v['description'], 'amount' => $signed]);
            $locked->increment('credit', $signed);

            return null;
        });

        if ($refused !== null) {
            return back()->withInput()->withErrors(['amount' => __('admin.clients.credit_too_much', ['balance' => money_fmt($refused)])]);
        }

        ActivityLog::log(($v['type'] === 'add' ? 'Credit added: ' : 'Credit removed: ').money_fmt($amount).' ('.$v['description'].')', $admin?->email, $client->id);

        return redirect()->route('admin.clients.credit', $client)
            ->with('success', $v['type'] === 'add' ? __('admin.clients.credit_added') : __('admin.clients.credit_removed'));
    }
}
