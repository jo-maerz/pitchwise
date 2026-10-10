<x-app-layout>
    @push('scripts')
        @vite('resources/js/piece-stats.js')
        @if ($piece->needsReview())
            @vite('resources/js/score-preview.js')
        @endif
    @endpush

    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ $piece->title }}</h2>
                <p class="text-sm text-gray-500">{{ $piece->composer ?? 'Unknown composer' }} · {{ ucfirst($piece->instrument) }}</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                @can('play', $piece)
                    <a href="{{ route('player.show', $piece) }}" class="inline-flex items-center px-4 py-2 bg-gray-800 rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700">▶ {{ __('Practise') }}</a>
                @endcan
                @can('playPdf', $piece)
                    <a href="{{ route('player.pdf', $piece) }}" class="inline-flex items-center px-4 py-2 {{ $piece->isReady() ? 'bg-white border border-gray-300 text-gray-700 hover:bg-gray-50' : 'bg-gray-800 text-white hover:bg-gray-700' }} rounded-md font-semibold text-xs uppercase tracking-widest"
                       title="Shows your PDF next to the live tuner. The notes are not followed or checked.">▶ {{ $piece->isReady() ? __('Practise from the PDF') : __('Practise with the PDF') }} · {{ __('tuner only') }}</a>
                @endcan
            </div>
        </div>
    </x-slot>

    <div class="py-8" @if ($piece->isProcessing()) x-data x-init="setTimeout(() => location.reload(), 3000)" @endif>
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            @if (session('status'))
                <div class="rounded-md bg-green-50 p-3 text-sm text-green-800">{{ session('status') }}</div>
            @endif

            <div class="bg-white shadow-sm sm:rounded-lg p-6 grid sm:grid-cols-4 gap-4 text-sm">
                <div><div class="text-gray-500">Status</div><div class="font-medium">{{ ['pending' => 'Reading notes…', 'converting' => 'Recognising the PDF…', 'needs_review' => 'Needs your check', 'pdf_only' => 'PDF + tuner only', 'ready' => 'Ready to play', 'failed' => 'Could not read the file'][$piece->parse_status] }}</div></div>
                <div><div class="text-gray-500">Notes checked</div><div class="font-medium tabular-nums">{{ $piece->note_count }}</div></div>
                <div><div class="text-gray-500">Default tempo</div><div class="font-medium tabular-nums">♩ = {{ $piece->default_bpm }}</div></div>
                <div><div class="text-gray-500">Count-in</div><div class="font-medium tabular-nums">{{ $piece->beats_per_measure }} beats</div></div>
            </div>

            @if ($piece->parse_status === 'pending')
                <p class="text-sm text-gray-600">The queue worker is reading the score. If this stays here, start it with <code>php artisan queue:work</code>.</p>
            @elseif ($piece->parse_status === 'converting')
                <p class="text-sm text-gray-600">Audiveris is recognising the notes in your PDF. This can take a few minutes; the page refreshes by itself. If it stays here, check that the <code>omr</code> container is running.</p>
            @endif

            @if ($piece->parse_status === 'failed' && $piece->hasPdf())
                <div class="bg-amber-50 border border-amber-200 sm:rounded-lg p-6 space-y-3">
                    <h3 class="font-semibold text-amber-900">The notes could not be read from this file</h3>
                    <p class="text-sm text-amber-900">You can still practise from your PDF with the live tuner, or upload a MusicXML file for note-by-note checking.</p>
                    @can('update', $piece)
                        <div class="flex flex-wrap items-center gap-3">
                            <form method="POST" action="{{ route('pieces.use-pdf', $piece) }}">@csrf<x-primary-button>{{ __('Use the PDF with the tuner') }}</x-primary-button></form>
                            <a href="{{ route('pieces.edit', $piece) }}" class="text-sm text-indigo-700 hover:underline">Upload MusicXML instead</a>
                        </div>
                    @endcan
                </div>
            @endif

            @if ($piece->isPdfOnly())
                @include('pieces._pdf-limits')
                @can('update', $piece)
                    <p class="text-sm text-gray-600">Want every note checked? <a href="{{ route('pieces.edit', $piece) }}" class="text-indigo-700 hover:underline">Upload a MusicXML file</a> for this piece.</p>
                @endcan
            @endif

            @if ($piece->needsReview())
                <div class="bg-amber-50 border border-amber-200 sm:rounded-lg p-6 space-y-4">
                    <div>
                        <h3 class="font-semibold text-amber-900">Check the recognised score</h3>
                        <p class="text-sm text-amber-900">Notes were read from your PDF by software, which makes mistakes (wrong rhythms, missed accidentals, a clef read wrongly). Compare it with your PDF: practising against wrong notes gives wrong outcomes.</p>
                        @if ($piece->review_notes)
                            <ul class="mt-2 list-disc pl-5 text-sm text-amber-900">
                                @foreach (explode("\n", $piece->review_notes) as $line)<li>{{ $line }}</li>@endforeach
                            </ul>
                        @endif
                    </div>
                    <div id="score-preview" data-url="{{ route('pieces.file', $piece) }}" class="bg-white rounded-md border border-amber-200 p-2 overflow-x-auto"></div>
                    <p id="score-preview-error" class="hidden text-sm text-red-700"></p>
                    @can('update', $piece)
                        <div class="flex flex-wrap items-center gap-3">
                            <form method="POST" action="{{ route('pieces.confirm', $piece) }}">
                                @csrf
                                <x-primary-button>{{ __('It matches: confirm') }}</x-primary-button>
                            </form>
                            <a href="{{ route('pieces.edit', $piece) }}" class="text-sm text-indigo-700 hover:underline">It has mistakes: upload corrected MusicXML</a>
                            <form method="POST" action="{{ route('pieces.use-pdf', $piece) }}" class="sm:ml-auto">
                                @csrf
                                <button class="text-sm text-gray-700 underline hover:text-gray-900" title="Throw the recognised score away and practise from your PDF with the live tuner">Use the original PDF with the tuner only</button>
                            </form>
                        </div>
                        <p class="text-xs text-amber-900">With the PDF alone the app cannot know which note you meant, so it compares you with the nearest note. Results may be restricted and inaccurate.</p>
                    @endcan
                </div>
            @endif

            @if ($pitches->isNotEmpty())
                <div class="bg-white shadow-sm sm:rounded-lg p-6">
                    <script type="application/json" id="piece-stats-data">@json($pitches)</script>
                    <h3 class="font-semibold text-gray-800">Your intonation by note, on this piece</h3>
                    <p class="text-sm text-gray-600">The average offset of each pitch over all your finished runs of this piece. Above zero is sharp, below is flat.</p>
                    <div class="mt-4 h-64"><canvas id="chart-piece-pitches" role="img" aria-label="Bar chart of the average cents offset per note on this piece"></canvas></div>
                </div>
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
