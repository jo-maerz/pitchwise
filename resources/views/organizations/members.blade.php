<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ $organization->name }} · {{ __('Members') }}</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            @if (session('status'))
                <div class="rounded-md bg-green-50 p-3 text-sm text-green-800">{{ session('status') }}</div>
            @endif
            @if ($errors->any())
                <div class="rounded-md bg-red-50 p-3 text-sm text-red-800">{{ $errors->first() }}</div>
            @endif

            <section class="bg-white shadow-sm sm:rounded-lg">
                <div class="px-6 pt-5">
                    <h3 class="font-semibold text-gray-800">{{ __('Who annotates for everyone') }}</h3>
                    <p class="mt-1 text-sm text-gray-500">
                        Every member sees the shared annotations on the organization's pieces and keeps private notes of their own.
                        Members can edit the shared annotations on pieces for the instruments ticked here. Organization admins can edit them on every piece.
                    </p>
                </div>
                <div class="overflow-x-auto">
                    <table class="pi-table mt-3">
                        <thead><tr><th class="pl-6">Name</th><th>Role</th><th>Annotates for everyone</th></tr></thead>
                        <tbody>
                            @forelse ($members as $member)
                                <tr class="align-top">
                                    <td class="pl-6">
                                        <div>{{ $member->name }}</div>
                                        <div class="text-xs text-gray-500">{{ $member->email }}</div>
                                    </td>
                                    <td>{{ $member->role->label() }}</td>
                                    <td class="pr-6">
                                        <details>
                                            <summary class="cursor-pointer text-sm">
                                                @if ($member->canManageLibrary($organization->id))
                                                    {{ __('All instruments (organization admin)') }}
                                                @else
                                                    {{ collect($member->annotation_instruments ?? [])->map(fn ($key) => App\Support\Instruments::label($key))->join(', ') ?: __('None') }}
                                                @endif
                                            </summary>
                                            <form method="POST" action="{{ route('organizations.members.update', [$organization, $member]) }}" class="mt-3 space-y-3">
                                                @csrf @method('PUT')
                                                <div class="grid gap-4 sm:grid-cols-4">
                                                    @foreach ($instruments as $family => $options)
                                                        <fieldset>
                                                            <legend class="text-xs font-semibold uppercase tracking-wide text-gray-500">{{ $family }}</legend>
                                                            @foreach ($options as $key => $label)
                                                                <label class="mt-1 flex items-center gap-2 text-sm">
                                                                    <input type="checkbox" name="instruments[]" value="{{ $key }}" class="rounded border-gray-300 text-indigo-600"
                                                                           @checked($member->canAnnotateInstrument($key))>
                                                                    {{ $label }}
                                                                </label>
                                                            @endforeach
                                                        </fieldset>
                                                    @endforeach
                                                </div>
                                                <x-primary-button>{{ __('Save') }}</x-primary-button>
                                            </form>
                                        </details>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="pl-6 py-4 text-gray-500">No members yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </div>
</x-app-layout>
