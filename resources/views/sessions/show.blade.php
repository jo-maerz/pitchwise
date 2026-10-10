@php
    use App\Support\Pitch;
    $notesByBar = collect($report['notes'])->groupBy('measure');
    $labels = ['in_tune' => 'In tune', 'sharp' => 'Too high', 'flat' => 'Too low', 'wrong_note' => 'Wrong note', 'missed' => 'Missed'];
@endphp
<x-app-layout>
    @push('scripts')
        @vite('resources/js/session-report.js')
    @endpush

    <script type="application/json" id="report-data">@json($reportData)</script>

    <x-slot name="header">
        <div class="flex flex-wrap items-baseline justify-between gap-2">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                <a href="{{ route('pieces.show', $session->piece) }}" class="hover:underline">{{ $session->piece->title }}</a>
                <span class="text-gray-400 font-normal">· run report</span>
            </h2>
            <p class="text-sm text-gray-500">
                {{ $session->finished_at?->format('j M Y, H:i') ?? 'Not finished' }} · ♩ = {{ $session->bpm }}
                · rule {{ $session->toleranceLabel() }} · A = {{ rtrim(rtrim(number_format($session->reference_hz, 1), '0'), '.') }} Hz
            </p>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            <div class="bg-white shadow-sm sm:rounded-lg p-6 flex flex-wrap items-center gap-8">
                <div>
                    <div class="text-sm uppercase tracking-wider text-gray-500">In tune</div>
                    <div class="text-6xl font-bold tabular-nums">{{ $report['score'] === null ? '–' : number_format($report['score'], 0).'%' }}</div>
                    <div class="text-sm text-gray-600">
                        {{ $report['counts']['in_tune'] }} of {{ $report['total'] }} notes
                        @if ($report['total'] < $report['checked_of']) (run covered {{ $report['total'] }} of {{ $report['checked_of'] }}) @endif
                        @if ($report['avg_cents'] !== null) · average offset {{ Pitch::cents($report['avg_cents']) }} @endif
                    </div>
                </div>
                <ul class="flex flex-wrap gap-2">
                    @foreach ($report['counts'] as $outcome => $n)
                        <li class="pi-chip" data-outcome="{{ $outcome }}"><span class="pi-dot"></span>{{ $labels[$outcome] }} <strong>{{ $n }}</strong></li>
                    @endforeach
                </ul>
                @can('play', $session->piece)
                    <a href="{{ route('player.show', $session->piece) }}" class="ml-auto inline-flex items-center px-4 py-2 bg-gray-800 rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700">▶ Play again</a>
                @endcan
            </div>

            <div class="bg-white shadow-sm sm:rounded-lg p-4">
                <div class="flex flex-wrap items-center justify-between gap-3 px-2">
                    <h3 class="font-semibold text-gray-800">Your run on the score</h3>
                    <label class="inline-flex items-center gap-2 text-sm text-gray-600">
                        <input type="checkbox" id="toggle-offsets" checked> Show the offset under each note (cents)
                    </label>
                </div>
                <ul class="flex flex-wrap gap-3 px-2 py-2 text-xs text-gray-600" aria-label="Note colours">
                    @foreach ($labels as $outcome => $label)
                        <li data-outcome="{{ $outcome }}"><span class="pi-dot"></span>{{ strtolower($label) }}</li>
                    @endforeach
                    <li class="text-gray-400">Numbers: + sharp, − flat. Click a note for details.</li>
                </ul>
                <p id="score-message" class="px-2 pb-2 text-sm text-amber-800" hidden></p>
                <div id="note-detail" class="mx-2 mb-3 rounded-md border border-gray-200 bg-gray-50 p-3 text-sm" hidden>
                    <div class="flex flex-wrap items-baseline justify-between gap-2">
                        <strong data-detail-title></strong>
                        <span class="inline-flex items-center"><span class="pi-dot"></span><span data-detail-outcome></span></span>
                    </div>
                    <div class="text-gray-600" data-detail-text></div>
                    <div class="mt-3 pi-ruler" aria-hidden="true"><span class="pi-ruler-marker" data-detail-marker></span></div>
                    <div class="mt-1 flex justify-between text-xs text-gray-500" aria-hidden="true"><span>−50 flat</span><span>0</span><span>sharp +50</span></div>
                </div>
                <div id="score" class="pi-score"></div>
            </div>

            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                <h3 class="font-semibold text-gray-800">Intonation by note, on this piece</h3>
                <p class="text-sm text-gray-600">Bars: the average offset of each pitch in this run. Diamonds: your average over all finished runs of this piece. Above zero is sharp, below is flat.</p>
                <div class="mt-4 h-64"><canvas id="chart-run-pitches" role="img" aria-label="Bar chart of the average cents offset per note in this run, with your all-runs average for comparison"></canvas></div>
            </div>

            <div class="grid gap-6 md:grid-cols-2">
                <div class="bg-white shadow-sm sm:rounded-lg p-6">
                    <h3 class="font-semibold text-gray-800">Bars to practise</h3>
                    @if ($report['weakest'])
                        <ol class="mt-3 list-decimal pl-5 space-y-1 text-sm">
                            @foreach ($report['weakest'] as $m)
                                <li>Bar {{ $m['measure'] }}: {{ $m['in_tune'] }} of {{ $m['notes'] }} in tune ({{ number_format($m['rate'], 0) }}%)</li>
                            @endforeach
                        </ol>
                    @else
                        <p class="mt-3 text-sm text-gray-600">None: every bar was in tune.</p>
                    @endif
                </div>
                <div class="bg-white shadow-sm sm:rounded-lg p-6">
                    <h3 class="font-semibold text-gray-800">Bar by bar</h3>
                    <div class="mt-3 flex flex-wrap gap-1" role="list">
                        @foreach ($report['measures'] as $m)
                            <div role="listitem" tabindex="0" data-bar="{{ $m['measure'] }}" class="group relative w-11 rounded text-center text-xs py-1 tabular-nums focus:outline-none focus:ring-2 focus:ring-gray-400 {{ $m['rate'] >= 80 ? 'bg-green-50 text-green-900' : ($m['rate'] >= 50 ? 'bg-amber-50 text-amber-900' : 'bg-red-50 text-red-900') }}">
                                <div class="text-gray-500">{{ $m['measure'] }}</div>{{ number_format($m['rate'], 0) }}%
                                <div role="tooltip" class="hidden group-hover:block group-focus:block absolute z-20 left-1/2 top-full mt-1 -translate-x-1/2 w-48 rounded-md bg-white text-left text-gray-800 shadow-lg ring-1 ring-gray-200 p-2">
                                    <div class="font-semibold">Bar {{ $m['measure'] }}: {{ $m['in_tune'] }} of {{ $m['notes'] }} in tune</div>
                                    <div class="text-gray-500">Click to show in the score</div>
                                    <ul class="mt-1 space-y-0.5">
                                        @foreach ($notesByBar->get($m['measure'], []) as $n)
                                            <li class="flex items-center" data-outcome="{{ $n['outcome'] }}">
                                                <span class="pi-dot"></span>
                                                <span class="font-medium">{{ $n['expected'] }}</span>
                                                <span class="text-gray-600">{{ $labels[$n['outcome']] }}@if ($n['cents'] !== null && $n['outcome'] !== 'missed') ({{ Pitch::cents($n['cents']) }})@endif</span>
                                            </li>
                                        @endforeach
                                    </ul>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

            <details class="bg-white shadow-sm sm:rounded-lg">
                <summary class="px-6 py-4 cursor-pointer font-semibold text-gray-800">Every note, as a table</summary>
                <div class="overflow-x-auto">
                    <table class="pi-table mt-2">
                        <thead><tr><th class="pl-6">#</th><th>Bar</th><th>Written</th><th>Heard</th><th>Offset</th><th>Result</th></tr></thead>
                        <tbody>
                            @foreach ($report['notes'] as $n)
                                <tr data-index="{{ $n['index'] }}">
                                    <td class="pl-6 text-gray-500">{{ $n['index'] + 1 }}</td>
                                    <td>{{ $n['measure'] }}</td>
                                    <td>{{ $n['expected'] }}</td>
                                    <td>{{ $n['hz'] ? $n['detected'].' · '.number_format($n['hz'], 1).' Hz' : '–' }}</td>
                                    <td>{{ Pitch::cents($n['cents']) }}</td>
                                    <td data-outcome="{{ $n['outcome'] }}"><span class="pi-dot"></span>{{ $labels[$n['outcome']] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </details>
        </div>
    </div>
</x-app-layout>
