<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ $piece->title }}</h2>
                <p class="text-sm text-gray-500">{{ $piece->composer ?? 'Unknown composer' }} · {{ ucfirst($piece->instrument) }}</p>
            </div>
            @can('play', $piece)
                <a href="{{ route('player.show', $piece) }}" class="inline-flex items-center px-4 py-2 bg-gray-800 rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700">▶ {{ __('Practise') }}</a>
            @endcan
        </div>
    </x-slot>

    <div class="py-8" @if ($piece->parse_status === 'pending') x-data x-init="setTimeout(() => location.reload(), 3000)" @endif>
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            @if (session('status'))
                <div class="rounded-md bg-green-50 p-3 text-sm text-green-800">{{ session('status') }}</div>
            @endif

            <div class="bg-white shadow-sm sm:rounded-lg p-6 grid sm:grid-cols-4 gap-4 text-sm">
                <div><div class="text-gray-500">Status</div><div class="font-medium">{{ ['pending' => 'Reading notes…', 'ready' => 'Ready to play', 'failed' => 'Could not read the file'][$piece->parse_status] }}</div></div>
                <div><div class="text-gray-500">Notes checked</div><div class="font-medium tabular-nums">{{ $piece->note_count }}</div></div>
                <div><div class="text-gray-500">Default tempo</div><div class="font-medium tabular-nums">♩ = {{ $piece->default_bpm }}</div></div>
                <div><div class="text-gray-500">Count-in</div><div class="font-medium tabular-nums">{{ $piece->beats_per_measure }} beats</div></div>
            </div>

            @if ($piece->parse_status === 'pending')
                <p class="text-sm text-gray-600">The queue worker is reading the score. If this stays here, start it with <code>php artisan queue:work</code>.</p>
            @endif

            <div class="bg-white shadow-sm sm:rounded-lg">
                <h3 class="px-6 pt-5 font-semibold text-gray-800">Your recent runs</h3>
                <table class="pi-table mt-2">
                    <thead><tr><th class="pl-6">Date</th><th>Tempo</th><th>Rule</th><th>In tune</th><th></th></tr></thead>
                    <tbody>
                        @forelse ($runs as $run)
                            <tr>
                                <td class="pl-6">{{ $run->finished_at->format('j M Y, H:i') }}</td>
                                <td>♩ = {{ $run->bpm }}</td>
                                <td>{{ $run->toleranceLabel() }}</td>
                                <td class="font-medium">{{ number_format($run->score_pct, 0) }}%</td>
                                <td><a class="text-indigo-700 hover:underline" href="{{ route('sessions.show', $run) }}">Report</a></td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="pl-6 py-4 text-gray-500">No runs yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="flex items-center gap-3">
                @can('update', $piece)
                    <a href="{{ route('pieces.edit', $piece) }}"
                       class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">{{ __('Edit piece') }}</a>
                @endcan

                @can('delete', $piece)
                    <form method="POST" action="{{ route('pieces.destroy', $piece) }}" onsubmit="return confirm('Delete this piece and all its runs?')">
                        @csrf @method('DELETE')
                        <x-danger-button>{{ __('Delete piece') }}</x-danger-button>
                    </form>
                @endcan
            </div>
        </div>
    </div>
</x-app-layout>
