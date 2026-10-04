@php
    use App\Support\Pitch;
    $labels = ['in_tune' => 'In tune', 'sharp' => 'Too high', 'flat' => 'Too low', 'wrong_note' => 'Wrong note', 'missed' => 'Missed'];
@endphp
<x-app-layout>
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
                    @foreach ($report['counts'] as $verdict => $n)
                        <li class="pi-chip" data-verdict="{{ $verdict }}"><span class="pi-dot"></span>{{ $labels[$verdict] }} <strong>{{ $n }}</strong></li>
                    @endforeach
                </ul>
                @can('play', $session->piece)
                    <a href="{{ route('player.show', $session->piece) }}" class="ml-auto inline-flex items-center px-4 py-2 bg-gray-800 rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700">▶ Play again</a>
                @endcan
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
                            <div role="listitem" class="w-11 rounded text-center text-xs py-1 tabular-nums {{ $m['rate'] >= 80 ? 'bg-green-50 text-green-900' : ($m['rate'] >= 50 ? 'bg-amber-50 text-amber-900' : 'bg-red-50 text-red-900') }}"
                                 title="Bar {{ $m['measure'] }}: {{ $m['in_tune'] }} of {{ $m['notes'] }} in tune">
                                <div class="text-gray-500">{{ $m['measure'] }}</div>{{ number_format($m['rate'], 0) }}%
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

            <div class="bg-white shadow-sm sm:rounded-lg">
                <h3 class="px-6 pt-5 font-semibold text-gray-800">Every note</h3>
                <div class="overflow-x-auto">
                    <table class="pi-table mt-2">
                        <thead><tr><th class="pl-6">#</th><th>Bar</th><th>Written</th><th>Heard</th><th>Offset</th><th>Result</th></tr></thead>
                        <tbody>
                            @foreach ($report['notes'] as $n)
                                <tr>
                                    <td class="pl-6 text-gray-500">{{ $n['index'] + 1 }}</td>
                                    <td>{{ $n['measure'] }}</td>
                                    <td>{{ $n['expected'] }}</td>
                                    <td>{{ $n['hz'] ? $n['detected'].' · '.number_format($n['hz'], 1).' Hz' : '–' }}</td>
                                    <td>{{ Pitch::cents($n['cents']) }}</td>
                                    <td data-verdict="{{ $n['verdict'] }}"><span class="pi-dot"></span>{{ $labels[$n['verdict']] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
