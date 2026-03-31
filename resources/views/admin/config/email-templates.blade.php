@extends('admin.layouts.app')
@section('title', 'Email Templates')
@section('content')

<div x-data="{
    showModal: false,
    form: {
        id: null, name: '', subject: '', from_name: '', from_email: '',
        message: '', disabled: false
    },
    openEdit(template) {
        this.form = { ...template };
        this.showModal = true;
    }
}">

    <x-flash-message/>

    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 dark:text-white">Email Templates</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">Customise automated emails sent to clients</p>
        </div>
    </div>

    @php
        $grouped = $templates->groupBy('type');
        $typeOrder = ['general', 'product', 'domain', 'invoice', 'support', 'affiliate'];
        $typeColors = [
            'general'   => 'bg-slate-100 text-slate-700 dark:bg-slate-700 dark:text-slate-300',
            'product'   => 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
            'domain'    => 'bg-indigo-100 text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-400',
            'invoice'   => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400',
            'support'   => 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400',
            'affiliate' => 'bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-400',
        ];
        $allTypes = collect($typeOrder)->merge($grouped->keys()->diff($typeOrder))->unique();
    @endphp

    @if($templates->isEmpty())
        <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700">
            <x-empty-state icon="document" title="No email templates" description="Email templates will appear here once seeded."/>
        </div>
    @else

    {{-- Merge field reference --}}
    <details class="mb-4 bg-indigo-50 dark:bg-indigo-900/10 border border-indigo-200 dark:border-indigo-800 rounded-xl overflow-hidden">
        <summary class="px-5 py-3 text-sm font-medium text-indigo-700 dark:text-indigo-400 cursor-pointer flex items-center gap-2 select-none">
            <x-heroicon-o-variable class="w-4 h-4"/>
            Merge Field Reference
            <x-heroicon-o-chevron-down class="w-4 h-4 ml-auto"/>
        </summary>
        <div class="px-5 py-4 border-t border-indigo-200 dark:border-indigo-800">
            <div class="grid grid-cols-2 md:grid-cols-4 gap-x-6 gap-y-1 text-xs">
                @foreach([
                    '{company_name}' => 'Your company name',
                    '{client_name}' => 'Client full name',
                    '{client_email}' => 'Client email address',
                    '{client_first_name}' => 'Client first name',
                    '{invoice_num}' => 'Invoice number',
                    '{invoice_date}' => 'Invoice date',
                    '{invoice_due_date}' => 'Invoice due date',
                    '{invoice_total}' => 'Invoice total amount',
                    '{service_name}' => 'Product/service name',
                    '{domain}' => 'Domain name',
                    '{username}' => 'Client login username',
                    '{password}' => 'Auto-generated password',
                    '{ticket_id}' => 'Support ticket ID',
                    '{ticket_subject}' => 'Ticket subject',
                    '{ticket_url}' => 'Direct ticket link',
                    '{support_url}' => 'Support portal URL',
                ] as $tag => $desc)
                <div class="flex items-start gap-1.5 py-0.5">
                    <code class="font-mono text-indigo-600 dark:text-indigo-400 whitespace-nowrap">{{ $tag }}</code>
                    <span class="text-slate-500 dark:text-slate-400">{{ $desc }}</span>
                </div>
                @endforeach
            </div>
        </div>
    </details>

    @foreach($allTypes as $type)
        @if($grouped->has($type))
        <div class="mb-6">
            <div class="flex items-center gap-3 mb-3">
                <span class="px-3 py-1 text-xs font-semibold rounded-full uppercase {{ $typeColors[$type] ?? 'bg-slate-100 text-slate-700 dark:bg-slate-700 dark:text-slate-300' }}">{{ ucfirst($type) }}</span>
                <div class="flex-1 h-px bg-slate-200 dark:bg-slate-700"></div>
            </div>
            <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden">
                <table class="min-w-full divide-y divide-slate-100 dark:divide-slate-700">
                    <thead class="bg-slate-50 dark:bg-slate-700/50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">Name</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">Subject</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">Custom</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                        @foreach($grouped[$type] as $template)
                        <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/30 transition cursor-pointer"
                            @click="openEdit({{ json_encode(['id' => $template->id, 'name' => $template->name, 'subject' => $template->subject, 'from_name' => $template->from_name ?? '', 'from_email' => $template->from_email ?? '', 'message' => $template->message, 'disabled' => (bool)$template->disabled]) }})">
                            <td class="px-6 py-4 text-sm font-medium text-slate-900 dark:text-white">{{ $template->name }}</td>
                            <td class="px-6 py-4 text-sm text-slate-600 dark:text-slate-300 max-w-xs truncate">{{ $template->subject }}</td>
                            <td class="px-6 py-4">
                                @if($template->disabled)
                                    <x-status-badge status="cancelled"/>
                                @else
                                    <x-status-badge status="active"/>
                                @endif
                            </td>
                            <td class="px-6 py-4">
                                @if($template->custom)
                                    <span class="px-2 py-0.5 text-xs rounded bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400">Custom</span>
                                @else
                                    <span class="text-slate-400 text-xs">Default</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-right" @click.stop>
                                <button @click="openEdit({{ json_encode(['id' => $template->id, 'name' => $template->name, 'subject' => $template->subject, 'from_name' => $template->from_name ?? '', 'from_email' => $template->from_email ?? '', 'message' => $template->message, 'disabled' => (bool)$template->disabled]) }})"
                                    type="button" class="text-indigo-600 hover:text-indigo-800 dark:text-indigo-400 dark:hover:text-indigo-300">
                                    <x-heroicon-o-pencil-square class="w-4 h-4"/>
                                </button>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        @endif
    @endforeach
    @endif

    {{-- Edit Modal --}}
    <div x-show="showModal" x-cloak class="fixed inset-0 z-50 overflow-y-auto" style="display:none">
        <div class="flex items-start justify-center min-h-screen px-4 pt-6 pb-8">
            <div @click="showModal = false" class="fixed inset-0 bg-black/50"></div>
            <div class="relative bg-white dark:bg-slate-800 rounded-xl shadow-xl max-w-3xl w-full p-6 z-10">
                <div class="flex items-center justify-between mb-5">
                    <h3 class="text-lg font-semibold text-slate-900 dark:text-white">Edit Email Template</h3>
                    <button @click="showModal = false" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-300">
                        <x-heroicon-o-x-mark class="w-5 h-5"/>
                    </button>
                </div>

                <form :action="'/admin/config/email-templates/' + form.id" method="POST">
                    @csrf
                    @method('PUT')

                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Template Name</label>
                            <input type="text" name="name" x-model="form.name"
                                class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 text-slate-900 dark:text-white text-sm focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Subject <span class="text-red-500">*</span></label>
                            <input type="text" name="subject" x-model="form.subject" required
                                class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 text-slate-900 dark:text-white text-sm focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">From Name <span class="text-xs text-slate-400">(optional override)</span></label>
                                <input type="text" name="from_name" x-model="form.from_name" placeholder="Your Company"
                                    class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 text-slate-900 dark:text-white text-sm focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">From Email <span class="text-xs text-slate-400">(optional override)</span></label>
                                <input type="email" name="from_email" x-model="form.from_email" placeholder="noreply@yourdomain.com"
                                    class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 text-slate-900 dark:text-white text-sm focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Message Body</label>
                            <textarea name="message" x-model="form.message" rows="16"
                                class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 text-slate-900 dark:text-white text-sm font-mono focus:ring-2 focus:ring-indigo-500 focus:border-transparent resize-y"></textarea>
                            <p class="mt-1 text-xs text-slate-400">HTML is supported. Use merge fields like <code class="bg-slate-100 dark:bg-slate-700 px-1 rounded">{client_name}</code> for dynamic content.</p>
                        </div>
                        <div class="flex items-center gap-3 pt-1">
                            <label class="flex items-center gap-2 cursor-pointer select-none">
                                <input type="hidden" name="disabled" value="0">
                                <input type="checkbox" name="disabled" value="1" x-model="form.disabled"
                                    class="w-4 h-4 rounded border-slate-300 text-red-500 focus:ring-red-400">
                                <span class="text-sm text-slate-700 dark:text-slate-300">Disable this template (email will not be sent)</span>
                            </label>
                        </div>
                    </div>

                    <div class="flex items-center justify-end gap-3 mt-6 pt-4 border-t border-slate-200 dark:border-slate-700">
                        <button type="button" @click="showModal = false"
                            class="px-4 py-2 text-sm font-medium text-slate-700 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white transition">
                            Cancel
                        </button>
                        <button type="submit"
                            class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-lg transition">
                            Save Template
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
