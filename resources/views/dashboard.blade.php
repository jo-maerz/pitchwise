@php use App\Support\Pitch; @endphp
<x-app-layout>
    @push('scripts')
        @vite('resources/js/dashboard.js')
    @endpush

    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Dashboard') }}</h2>
    </x-slot>

    <script type="application/json" id="dashboard-data">@json($chartData)</script>

    <div class="py-8">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            @if ($recent->isEmpty())
                <div class="bg-white shadow-sm sm:rounded-lg p-6">
                    <p class="text-gray-700">No runs yet. <a class="text-indigo-700 hover:underline" href="{{ route('pieces.index') }}">Pick a piece</a> and press Start; your results land here.</p>
                </div>
            @else
                <div class="grid gap-6 lg:grid-cols-2">
                    <section class="bg-white shadow-sm sm:rounded-lg p-6">
                        <h3 class="font-semibold text-gray-800">In tune per run</h3>
                        <p class="text-sm text-gray-500">Share of notes within the run's tolerance. Click a point for its report.</p>
                        <div class="mt-4 h-64"><canvas id="chart-history" role="img" aria-label="Line chart of in-tune percentage per run"></canvas></div>
                    </section>

                    <section class="bg-white shadow-sm sm:rounded-lg p-6">
                        <h3 class="font-semibold text-gray-800">Your intonation by note</h3>
                        <p class="text-sm text-gray-500">Average offset when the right note was played. Above zero: sharp. Below: flat.</p>
                        @if ($pitches->isEmpty())
                            <p class="mt-4 text-sm text-gray-600">Statistics are updated every five minutes (<code>php artisan schedule:work</code>, or run <code>php artisan practice:aggregate-stats</code>).</p>
                        @else
                            <div class="mt-4 h-64"><canvas id="chart-pitches" role="img" aria-label="Bar chart of average cents offset per note"></canvas></div>
                            @php $worst = $pitches->filter(fn ($p) => $p->avg_cents !== null && $p->attempts >= 5)->sortByDesc(fn ($p) => abs($p->avg_cents))->first(); @endphp
                            @if ($worst && abs($worst->avg_cents) >= 10)
                                <p class="mt-3 text-sm text-gray-700">Your {{ Pitch::name($worst->midi_pitch) }} is on average <strong>{{ Pitch::cents($worst->avg_cents) }}</strong> ({{ $worst->avg_cents > 0 ? 'sharp' : 'flat' }}).</p>
                            @endif
                            <details class="mt-2 text-sm">
                                <summary class="cursor-pointer text-gray-600">Show as table</summary>
                                <table class="pi-table mt-2">
                                    <thead><tr><th>Note</th><th>Attempts</th><th>In tune</th><th>Average offset</th></tr></thead>
                                    <tbody>
                                        @foreach ($pitches as $p)
                                            <tr><td>{{ Pitch::name($p->midi_pitch) }}</td><td>{{ $p->attempts }}</td><td>{{ round(100 * $p->in_tune / max(1, $p->attempts)) }}%</td><td>{{ Pitch::cents($p->avg_cents) }}</td></tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </details>
                        @endif
                    </section>
                </div>

                <div class="grid gap-6 lg:grid-cols-2">
                    <section class="bg-white shadow-sm sm:rounded-lg p-6">
                        <h3 class="font-semibold text-gray-800">Trouble bars</h3>
                        <p class="text-sm text-gray-500">Lowest in-tune rate over your last 20 runs.</p>
                        @if ($trouble->isEmpty())
                            <p class="mt-3 text-sm text-gray-600">Nothing stands out yet.</p>
                        @else
                            <table class="pi-table mt-3">
                                <thead><tr><th>Piece</th><th>Bar</th><th>Notes</th><th>In tune</th></tr></thead>
                                <tbody>
                                    @foreach ($trouble as $t)
                                        <tr><td>{{ $t->title }}</td><td>{{ $t->measure }}</td><td>{{ $t->notes }}</td><td>{{ number_format($t->rate, 0) }}%</td></tr>
                                    @endforeach
                                </tbody>
                            </table>
                        @endif
                    </section>

                    <section class="bg-white shadow-sm sm:rounded-lg p-6">
                        <h3 class="font-semibold text-gray-800">Recent runs</h3>
                        <table class="pi-table mt-3">
                            <thead><tr><th>Date</th><th>Piece</th><th>Rule</th><th>In tune</th></tr></thead>
                            <tbody>
                                @foreach ($recent as $run)
                                    <tr>
                                        <td><a class="text-indigo-700 hover:underline" href="{{ route('sessions.show', $run) }}">{{ $run->finished_at->format('j M, H:i') }}</a></td>
                                        <td>{{ $run->piece->title }}</td>
                                        <td>{{ $run->toleranceLabel() }}</td>
                                        <td class="font-medium">{{ number_format($run->score_pct, 0) }}%</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </section>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
