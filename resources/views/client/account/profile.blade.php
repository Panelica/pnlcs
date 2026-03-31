@extends("client.layouts.app")
@section("title", "My Profile")
@section("content")

<div class="mb-6">
    <h1 class="text-2xl font-bold">My Profile</h1>
    <p class="text-slate-500 mt-1">Update your personal information and address details.</p>
</div>

<div class="grid grid-cols-1 lg:grid-cols-4 gap-8">
    <div class="lg:col-span-1">
        <nav class="space-y-1">
            <a href="{{ route("client.account.profile") }}" class="flex items-center gap-2 px-3 py-2 text-sm rounded-lg bg-indigo-50 text-indigo-700 font-medium dark:bg-indigo-900/30 dark:text-indigo-400">
                Profile Details
            </a>
            <a href="{{ route("client.account.password") }}" class="flex items-center gap-2 px-3 py-2 text-sm rounded-lg text-slate-600 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-700">
                Change Password
            </a>
            <a href="{{ route("client.account.contacts") }}" class="flex items-center gap-2 px-3 py-2 text-sm rounded-lg text-slate-600 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-700">
                Contacts
            </a>
            <a href="{{ route("client.account.security") }}" class="flex items-center gap-2 px-3 py-2 text-sm rounded-lg text-slate-600 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-700">
                Security
            </a>
        </nav>
    </div>
    <div class="lg:col-span-3">
        <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6">
            <h2 class="font-semibold mb-6">Profile Details</h2>
            <form method="POST" action="{{ route("client.account.update") }}">
                @csrf
                @method("PUT")

                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    <div>
                        <label class="block text-sm font-medium mb-1">First Name <span class="text-red-500">*</span></label>
                        <input type="text" name="first_name" value="{{ old("first_name", $client?->first_name ?? $user->first_name) }}"
                            class="w-full border border-slate-300 dark:border-slate-600 rounded-lg px-3 py-2 text-sm bg-white dark:bg-slate-700 focus:outline-none focus:ring-2 focus:ring-indigo-400">
                        @error("first_name") <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">Last Name <span class="text-red-500">*</span></label>
                        <input type="text" name="last_name" value="{{ old("last_name", $client?->last_name ?? $user->last_name) }}"
                            class="w-full border border-slate-300 dark:border-slate-600 rounded-lg px-3 py-2 text-sm bg-white dark:bg-slate-700 focus:outline-none focus:ring-2 focus:ring-indigo-400">
                        @error("last_name") <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium mb-1">Email Address <span class="text-red-500">*</span></label>
                        <input type="email" name="email" value="{{ old("email", $user->email) }}"
                            class="w-full border border-slate-300 dark:border-slate-600 rounded-lg px-3 py-2 text-sm bg-white dark:bg-slate-700 focus:outline-none focus:ring-2 focus:ring-indigo-400">
                        @error("email") <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium mb-1">Company Name</label>
                        <input type="text" name="company_name" value="{{ old("company_name", $client?->company_name) }}"
                            class="w-full border border-slate-300 dark:border-slate-600 rounded-lg px-3 py-2 text-sm bg-white dark:bg-slate-700 focus:outline-none focus:ring-2 focus:ring-indigo-400">
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium mb-1">Address Line 1</label>
                        <input type="text" name="address1" value="{{ old("address1", $client?->address1) }}"
                            class="w-full border border-slate-300 dark:border-slate-600 rounded-lg px-3 py-2 text-sm bg-white dark:bg-slate-700 focus:outline-none focus:ring-2 focus:ring-indigo-400">
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium mb-1">Address Line 2</label>
                        <input type="text" name="address2" value="{{ old("address2", $client?->address2) }}"
                            class="w-full border border-slate-300 dark:border-slate-600 rounded-lg px-3 py-2 text-sm bg-white dark:bg-slate-700 focus:outline-none focus:ring-2 focus:ring-indigo-400">
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">City</label>
                        <input type="text" name="city" value="{{ old("city", $client?->city) }}"
                            class="w-full border border-slate-300 dark:border-slate-600 rounded-lg px-3 py-2 text-sm bg-white dark:bg-slate-700 focus:outline-none focus:ring-2 focus:ring-indigo-400">
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">State / Region</label>
                        <input type="text" name="state" value="{{ old("state", $client?->state) }}"
                            class="w-full border border-slate-300 dark:border-slate-600 rounded-lg px-3 py-2 text-sm bg-white dark:bg-slate-700 focus:outline-none focus:ring-2 focus:ring-indigo-400">
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">Postcode</label>
                        <input type="text" name="postcode" value="{{ old("postcode", $client?->postcode) }}"
                            class="w-full border border-slate-300 dark:border-slate-600 rounded-lg px-3 py-2 text-sm bg-white dark:bg-slate-700 focus:outline-none focus:ring-2 focus:ring-indigo-400">
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">Country (ISO Code)</label>
                        <input type="text" name="country" maxlength="2" value="{{ old("country", $client?->country) }}"
                            class="w-full border border-slate-300 dark:border-slate-600 rounded-lg px-3 py-2 text-sm bg-white dark:bg-slate-700 focus:outline-none focus:ring-2 focus:ring-indigo-400">
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">Phone Number</label>
                        <input type="text" name="phone_number" value="{{ old("phone_number", $client?->phone_number) }}"
                            class="w-full border border-slate-300 dark:border-slate-600 rounded-lg px-3 py-2 text-sm bg-white dark:bg-slate-700 focus:outline-none focus:ring-2 focus:ring-indigo-400">
                    </div>
                </div>

                <div class="mt-6 pt-5 border-t border-slate-200 dark:border-slate-700 flex justify-end">
                    <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white font-semibold px-6 py-2 rounded-lg transition-colors text-sm">
                        Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
