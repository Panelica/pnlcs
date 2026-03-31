@extends("client.layouts.app")
@section("title", "Contacts")
@section("content")

<div class="mb-6">
    <h1 class="text-2xl font-bold">Contacts</h1>
</div>

<div class="grid grid-cols-1 lg:grid-cols-4 gap-8">
    <div class="lg:col-span-1">
        <nav class="space-y-1">
            <a href="{{ route("client.account.profile") }}" class="flex items-center gap-2 px-3 py-2 text-sm rounded-lg text-slate-600 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-700">Profile Details</a>
            <a href="{{ route("client.account.password") }}" class="flex items-center gap-2 px-3 py-2 text-sm rounded-lg text-slate-600 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-700">Change Password</a>
            <a href="{{ route("client.account.contacts") }}" class="flex items-center gap-2 px-3 py-2 text-sm rounded-lg bg-indigo-50 text-indigo-700 font-medium dark:bg-indigo-900/30 dark:text-indigo-400">Contacts</a>
            <a href="{{ route("client.account.security") }}" class="flex items-center gap-2 px-3 py-2 text-sm rounded-lg text-slate-600 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-700">Security</a>
        </nav>
    </div>
    <div class="lg:col-span-3 space-y-6">
        <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6">
            <h2 class="font-semibold mb-4">Existing Contacts</h2>
            @if($contacts->isEmpty())
            <p class="text-slate-400 text-sm">No contacts added yet.</p>
            @else
            <div class="space-y-3">
                @foreach($contacts as $contact)
                <div class="flex items-center justify-between p-4 border border-slate-200 dark:border-slate-700 rounded-lg">
                    <div>
                        <p class="font-medium text-sm">{{ $contact->full_name }}</p>
                        <p class="text-xs text-slate-500">{{ $contact->email }}</p>
                        @if($contact->company_name)
                        <p class="text-xs text-slate-400">{{ $contact->company_name }}</p>
                        @endif
                    </div>
                </div>
                @endforeach
            </div>
            @endif
        </div>

        <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6">
            <h2 class="font-semibold mb-4">Add New Contact</h2>
            <form method="POST" action="{{ route("client.account.contacts.store") }}">
                @csrf
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium mb-1">First Name <span class="text-red-500">*</span></label>
                        <input type="text" name="first_name" value="{{ old("first_name") }}"
                            class="w-full border border-slate-300 dark:border-slate-600 rounded-lg px-3 py-2 text-sm bg-white dark:bg-slate-700 focus:outline-none focus:ring-2 focus:ring-indigo-400">
                        @error("first_name") <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">Last Name <span class="text-red-500">*</span></label>
                        <input type="text" name="last_name" value="{{ old("last_name") }}"
                            class="w-full border border-slate-300 dark:border-slate-600 rounded-lg px-3 py-2 text-sm bg-white dark:bg-slate-700 focus:outline-none focus:ring-2 focus:ring-indigo-400">
                        @error("last_name") <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium mb-1">Email Address <span class="text-red-500">*</span></label>
                        <input type="email" name="email" value="{{ old("email") }}"
                            class="w-full border border-slate-300 dark:border-slate-600 rounded-lg px-3 py-2 text-sm bg-white dark:bg-slate-700 focus:outline-none focus:ring-2 focus:ring-indigo-400">
                        @error("email") <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">Company Name</label>
                        <input type="text" name="company_name" value="{{ old("company_name") }}"
                            class="w-full border border-slate-300 dark:border-slate-600 rounded-lg px-3 py-2 text-sm bg-white dark:bg-slate-700 focus:outline-none focus:ring-2 focus:ring-indigo-400">
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">Phone Number</label>
                        <input type="text" name="phone_number" value="{{ old("phone_number") }}"
                            class="w-full border border-slate-300 dark:border-slate-600 rounded-lg px-3 py-2 text-sm bg-white dark:bg-slate-700 focus:outline-none focus:ring-2 focus:ring-indigo-400">
                    </div>
                </div>
                <div class="mt-5 flex justify-end">
                    <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white font-semibold px-6 py-2 rounded-lg transition-colors text-sm">
                        Add Contact
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
