<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Admin') }}</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 space-y-8">
            @if (session('status'))
                <div class="rounded-md bg-green-50 p-3 text-sm text-green-800">{{ session('status') }}</div>
            @endif
            @if ($errors->any())
                <div class="rounded-md bg-red-50 p-3 text-sm text-red-800">{{ $errors->first() }}</div>
            @endif

            <section class="bg-white shadow-sm sm:rounded-lg">
                <div class="px-6 pt-5 flex flex-wrap items-center justify-between gap-3">
                    <h3 class="font-semibold text-gray-800">{{ __('Organizations') }}</h3>
                    <form method="POST" action="{{ route('admin.organizations.store') }}" class="flex items-center gap-2">
                        @csrf
                        <x-text-input name="name" required maxlength="120" placeholder="{{ __('New organization') }}" class="text-sm py-1.5" aria-label="{{ __('New organization name') }}" />
                        <x-primary-button>{{ __('Add') }}</x-primary-button>
                    </form>
                </div>
                <table class="pi-table mt-3">
                    <thead><tr><th class="pl-6">Name</th><th>Members</th><th>Folders</th><th>Pieces</th><th></th></tr></thead>
                    <tbody>
                        @forelse ($organizations as $organization)
                            <tr>
                                <td class="pl-6">
                                    <form method="POST" action="{{ route('admin.organizations.update', $organization) }}" class="flex items-center gap-2">
                                        @csrf @method('PUT')
                                        <x-text-input name="name" :value="$organization->name" required maxlength="120" class="text-sm py-1" aria-label="{{ __('Organization name') }}" />
                                        <button class="text-sm text-indigo-700 hover:underline">{{ __('Rename') }}</button>
                                    </form>
                                </td>
                                <td>{{ $organization->users_count }}</td>
                                <td>{{ $organization->folders_count }}</td>
                                <td>{{ $organization->pieces_count }}</td>
                                <td class="pr-6 text-right">
                                    <form method="POST" action="{{ route('admin.organizations.destroy', $organization) }}"
                                          onsubmit="return confirm(@js('Delete '.$organization->name.' with its '.$organization->pieces_count.' pieces and '.$organization->folders_count.' folders? Its members keep their accounts.'))">
                                        @csrf @method('DELETE')
                                        <button class="text-sm text-red-700 hover:underline">{{ __('Delete') }}</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="pl-6 py-4 text-gray-500">No organizations yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </section>

            <section class="bg-white shadow-sm sm:rounded-lg">
                <div class="px-6 pt-5 flex flex-wrap items-center justify-between gap-3">
                    <h3 class="font-semibold text-gray-800">{{ __('Users') }}</h3>
                    <form method="GET" action="{{ route('admin.index') }}" class="flex items-center gap-2">
                        <x-text-input name="q" :value="$search" placeholder="{{ __('Name or email') }}" class="text-sm py-1.5" aria-label="{{ __('Search users') }}" />
                        <x-secondary-button type="submit">{{ __('Search') }}</x-secondary-button>
                    </form>
                </div>
                <p class="px-6 mt-1 text-sm text-gray-500">
                    <strong>Admin</strong>: everything, in every library. <strong>Organization admin</strong>: folders and pieces of their organization.
                    <strong>User</strong>: practises the shared library and their organization's pieces.
                </p>
                <div class="overflow-x-auto">
                    <table class="pi-table mt-3">
                        <thead><tr><th class="pl-6">Name</th><th>Email</th><th>Role</th><th>Organization</th><th></th></tr></thead>
                        <tbody>
                            @foreach ($users as $member)
                                <tr>
                                    <td class="pl-6">{{ $member->name }}</td>
                                    <td>{{ $member->email }}</td>
                                    <td>
                                        <select form="user-{{ $member->id }}" name="role" class="border-gray-300 rounded-md shadow-sm text-sm py-1" aria-label="{{ __('Role of :name', ['name' => $member->name]) }}">
                                            @foreach ($roles as $role)
                                                <option value="{{ $role->value }}" @selected($member->role === $role)>{{ $role->label() }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td>
                                        <select form="user-{{ $member->id }}" name="organization_id" class="border-gray-300 rounded-md shadow-sm text-sm py-1" aria-label="{{ __('Organization of :name', ['name' => $member->name]) }}">
                                            <option value="">{{ __('None') }}</option>
                                            @foreach ($organizations as $organization)
                                                <option value="{{ $organization->id }}" @selected($member->organization_id === $organization->id)>{{ $organization->name }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td class="pr-6 text-right">
                                        <form id="user-{{ $member->id }}" method="POST" action="{{ route('admin.users.update', $member) }}">
                                            @csrf @method('PUT')
                                            <button class="text-sm text-indigo-700 hover:underline">{{ __('Save') }}</button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="px-6 py-4">{{ $users->links() }}</div>
            </section>
        </div>
    </div>
</x-app-layout>
