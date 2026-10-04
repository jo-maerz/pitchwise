<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Pieces') }}</h2>
            <a href="{{ route('pieces.create') }}" class="inline-flex items-center px-4 py-2 bg-gray-800 rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700">
                {{ __('Upload MusicXML') }}
            </a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 space-y-4">
            @if (session('status'))
                <div class="rounded-md bg-green-50 p-3 text-sm text-green-800">{{ session('status') }}</div>
            @endif

            <div class="bg-white shadow-sm sm:rounded-lg divide-y divide-gray-100">
                @forelse ($pieces as $piece)
                    <div class="p-4 sm:px-6 flex flex-wrap items-center gap-x-6 gap-y-2">
                        <div class="flex-1 min-w-[12rem]">
                            <a href="{{ route('pieces.show', $piece) }}" class="font-medium text-gray-900 hover:underline">{{ $piece->title }}</a>
                            <div class="text-sm text-gray-500">
                                {{ $piece->composer ?? 'Unknown composer' }} · {{ ucfirst($piece->instrument) }}
                                · {{ $piece->isCatalogue() ? 'Catalogue' : 'Your upload' }}
                            </div>
                        </div>
                        <div class="text-sm text-gray-500 tabular-nums">
                            @if ($piece->parse_status === 'ready')
                                {{ $piece->note_count }} notes · {{ $piece->my_runs }} {{ Str::plural('run', $piece->my_runs) }}
                            @elseif ($piece->parse_status === 'pending')
                                Reading notes…
                            @elseif ($piece->parse_status === 'converting')
                                Recognising PDF…
                            @elseif ($piece->parse_status === 'pdf_only')
                                PDF + tuner only
                            @elseif ($piece->parse_status === 'needs_review')
                                <span class="text-amber-700">Check the recognised score</span>
                            @else
                                <span class="text-red-700">Could not read this file</span>
                            @endif
                        </div>
                        @if ($piece->isPdfOnly())
                            <a href="{{ route('player.pdf', $piece) }}" class="inline-flex items-center px-3 py-1.5 border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50">▶ {{ __('Practise') }}</a>
                        @endif
                        @can('play', $piece)
                            <a href="{{ route('player.show', $piece) }}" class="inline-flex items-center px-3 py-1.5 border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50">
                                ▶ {{ __('Practise') }}
                            </a>
                        @endcan
                    </div>
                @empty
                    <p class="p-6 text-gray-600">No pieces yet. Upload a MusicXML file, or run <code>php artisan db:seed</code> for the demo catalogue.</p>
                @endforelse
            </div>

            {{ $pieces->links() }}
        </div>
    </div>
</x-app-layout>
