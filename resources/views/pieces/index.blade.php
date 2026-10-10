@php
    $user = auth()->user();
    $manages = $folder ? $user->can('update', $folder) : false;
    $uploadLocation = $folder && $manages ? 'folder:'.$folder->id : null;
    $filter = array_filter(['instrument' => $instrument]);
@endphp
<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                @if ($folder)
                    <nav class="text-sm text-gray-500" aria-label="Breadcrumb">
                        <a href="{{ route('pieces.index', $filter) }}" class="hover:underline">{{ __('Pieces') }}</a>
                        <span aria-hidden="true">/</span> {{ $sections->first()['name'] }}
                        @foreach ($folder->ancestry()->slice(0, -1) as $ancestor)
                            <span aria-hidden="true">/</span> <a href="{{ route('folders.show', [$ancestor, ...$filter]) }}" class="hover:underline">{{ $ancestor->name }}</a>
                        @endforeach
                    </nav>
                @endif
                @if ($folder)
                    <x-folder-name :folder="$folder" :editable="$manages">
                        <h2 class="font-semibold text-xl text-gray-800 leading-tight truncate">{{ $folder->name }}</h2>
                    </x-folder-name>
                @else
                    <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Pieces') }}</h2>
                @endif
            </div>
            @can('create', App\Models\Piece::class)
                <a href="{{ route('pieces.create', array_filter(['location' => $uploadLocation])) }}" class="inline-flex items-center px-4 py-2 bg-gray-800 rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700">
                    {{ __('Upload a score') }}
                </a>
            @endcan
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            @if (session('status'))
                <div class="rounded-md bg-green-50 p-3 text-sm text-green-800">{{ session('status') }}</div>
            @endif
            @if ($errors->any())
                <div class="rounded-md bg-red-50 p-3 text-sm text-red-800">{{ $errors->first() }}</div>
            @endif

            @if ($instrumentOptions !== [])
                <form method="GET" class="flex flex-wrap items-center gap-2">
                    <label for="instrument-filter" class="text-sm font-medium text-gray-700">{{ __('Instrument') }}</label>
                    <select id="instrument-filter" name="instrument" onchange="this.form.submit()" class="border-gray-300 rounded-md shadow-sm text-sm py-1.5">
                        <option value="">{{ __('All instruments') }}</option>
                        @foreach ($instrumentOptions as $family => $options)
                            <optgroup label="{{ $family }}">
                                @foreach ($options as $key => $label)
                                    <option value="{{ $key }}" @selected($instrument === $key)>{{ $label }}</option>
                                @endforeach
                            </optgroup>
                        @endforeach
                    </select>
                    <noscript><x-secondary-button type="submit">{{ __('Filter') }}</x-secondary-button></noscript>
                    @if ($instrument)
                        <a href="{{ url()->current() }}" class="text-sm text-indigo-700 hover:underline">{{ __('Show all') }}</a>
                    @endif
                </form>
            @endif

            @if ($folder && $manages)
                <form method="POST" action="{{ route('folders.destroy', $folder) }}" onsubmit="return confirm('Delete this folder?')">
                    @csrf @method('DELETE')
                    <button class="text-sm text-red-700 hover:underline disabled:text-gray-400 disabled:no-underline disabled:cursor-not-allowed" @disabled(! $folder->isEmpty()) title="{{ $folder->isEmpty() ? '' : __('Only empty folders can be deleted') }}">{{ __('Delete this folder') }}</button>
                </form>
            @endif

            @foreach ($sections as $section)
                @php($canManage = $user->canManageLibrary($section['organization_id']))
                <section class="space-y-2">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        @unless ($folder)
                            <h3 class="font-semibold text-gray-800">{{ $section['name'] }}</h3>
                        @endunless
                        @if ($canManage)
                            <form method="POST" action="{{ route('folders.store') }}" class="flex items-center gap-2 ms-auto">
                                @csrf
                                <input type="hidden" name="location" value="{{ $section['location']->key() }}">
                                <x-text-input name="name" required maxlength="100" placeholder="{{ __('New folder') }}" class="text-sm py-1.5" aria-label="{{ __('New folder name') }}" />
                                <x-secondary-button type="submit">{{ __('Add folder') }}</x-secondary-button>
                            </form>
                        @endif
                    </div>

                    <div class="bg-white shadow-sm sm:rounded-lg divide-y divide-gray-100">
                        @foreach ($section['folders'] as $child)
                            <div class="relative p-4 sm:px-6 flex items-center gap-4 hover:bg-gray-50">
                                <svg class="h-6 w-6 text-amber-500 shrink-0" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M2 5a2 2 0 012-2h4l2 2h6a2 2 0 012 2v7a2 2 0 01-2 2H4a2 2 0 01-2-2V5z"/></svg>
                                <div class="flex-1 min-w-0">
                                    <x-folder-name :folder="$child" :editable="$canManage">
                                        {{-- The link's ::after covers the whole row, so the row is clickable around the rename button. --}}
                                        <a href="{{ route('folders.show', [$child, ...$filter]) }}" class="block truncate font-medium text-gray-900 after:absolute after:inset-0">{{ $child->name }}</a>
                                    </x-folder-name>
                                    <span class="block text-sm text-gray-500">
                                        {{ collect([
                                            $child->children_count ? $child->children_count.' '.Str::plural('folder', $child->children_count) : null,
                                            $child->pieces_count ? $child->pieces_count.' '.Str::plural('piece', $child->pieces_count) : null,
                                        ])->filter()->join(' · ') ?: __('Empty') }}
                                    </span>
                                </div>
                                <svg class="h-5 w-5 text-gray-400 shrink-0" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M7.3 14.7a1 1 0 010-1.4L10.6 10 7.3 6.7a1 1 0 011.4-1.4l4 4a1 1 0 010 1.4l-4 4a1 1 0 01-1.4 0z" clip-rule="evenodd"/></svg>
                            </div>
                        @endforeach
                        @foreach ($section['pieces'] as $piece)
                            @include('pieces._row')
                        @endforeach
                        @if ($section['folders']->isEmpty() && $section['pieces']->isEmpty())
                            <p class="p-6 text-gray-600">{{ $instrument ? __('Nothing for this instrument here.') : ($folder ? __('This folder is empty.') : __('Nothing here yet.')) }}</p>
                        @endif
                    </div>

                    @if ($section['pieces'] instanceof Illuminate\Contracts\Pagination\Paginator)
                        {{ $section['pieces']->links() }}
                    @endif
                </section>
            @endforeach
        </div>
    </div>
</x-app-layout>
