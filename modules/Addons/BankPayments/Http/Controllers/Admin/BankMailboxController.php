<?php

namespace Modules\Addons\BankPayments\Http\Controllers\Admin;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Modules\Addons\BankPayments\Models\BankMailbox;
use Modules\Addons\BankPayments\Services\BankMailboxClient;

class BankMailboxController extends BankPaymentsController
{
    public function index(): View
    {
        $this->ensureActive();

        return view('bank-payments::admin.mailboxes.index', [
            'mailboxes' => BankMailbox::withCount('messages')->orderBy('name')->get(),
        ]);
    }

    public function create(): View
    {
        $this->ensureActive();

        return view('bank-payments::admin.mailboxes.form', [
            'mailbox' => new BankMailbox([
                'port' => 993,
                'encryption' => 'ssl',
                'folder' => 'INBOX',
                'bank' => 'nest_bank',
                'parser' => 'nest_bank',
                'active' => true,
                'validate_cert' => true,
            ]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->ensureActive();

        $data = $this->validated($request);
        $data['password'] = (string) $data['password'];
        $data['active'] = $request->boolean('active');
        $data['validate_cert'] = $request->boolean('validate_cert');

        BankMailbox::create($data);

        return redirect()->route('admin.bank-payments.mailboxes.index')
            ->with('success', __('bank-payments::mailboxes.created'));
    }

    public function edit(BankMailbox $mailbox): View
    {
        $this->ensureActive();

        return view('bank-payments::admin.mailboxes.form', compact('mailbox'));
    }

    public function update(Request $request, BankMailbox $mailbox): RedirectResponse
    {
        $this->ensureActive();

        $data = $this->validated($request, $mailbox);

        if (blank($data['password'] ?? null)) {
            unset($data['password']);
        }

        $data['active'] = $request->boolean('active');
        $data['validate_cert'] = $request->boolean('validate_cert');

        $mailbox->update($data);

        return redirect()->route('admin.bank-payments.mailboxes.index')
            ->with('success', __('bank-payments::mailboxes.updated'));
    }

    public function destroy(BankMailbox $mailbox): RedirectResponse
    {
        $this->ensureActive();

        $mailbox->delete();

        return redirect()->route('admin.bank-payments.mailboxes.index')
            ->with('success', __('bank-payments::mailboxes.deleted'));
    }

    public function test(BankMailbox $mailbox, BankMailboxClient $client): RedirectResponse
    {
        $this->ensureActive();

        try {
            $result = $client->test($mailbox);
        } catch (\Throwable $e) {
            $result = ['success' => false, 'message' => $e->getMessage()];
        } finally {
            $client->disconnect();
        }

        $mailbox->update(['last_error' => $result['success'] ? null : $result['message']]);

        return back()->with(
            $result['success'] ? 'success' : 'error',
            $result['message']
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?BankMailbox $mailbox = null): array
    {
        return $request->validate([
            'name' => [
                'required', 'string', 'max:100',
                Rule::unique('bank_mailboxes', 'name')->ignore($mailbox?->id),
            ],
            'host' => ['required', 'string', 'max:255'],
            'port' => ['required', 'integer', 'between:1,65535'],
            'encryption' => ['required', Rule::in(['ssl', 'tls', 'none'])],
            'username' => ['required', 'string', 'max:255'],
            'password' => [$mailbox ? 'nullable' : 'required', 'string', 'max:1000'],
            'folder' => ['required', 'string', 'max:255'],
            'bank' => ['required', 'string', 'max:50'],
            'parser' => ['required', 'string', 'max:50'],
            'active' => ['nullable', 'boolean'],
            'validate_cert' => ['nullable', 'boolean'],
        ]);
    }
}
