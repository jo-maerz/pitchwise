<div class="p-4 sm:px-6 flex flex-wrap items-center gap-x-6 gap-y-2">
    <div class="flex-1 min-w-[12rem]">
        <a href="{{ route('pieces.show', $piece) }}" class="font-medium text-gray-900 hover:underline">{{ $piece->title }}</a>
        <div class="text-sm text-gray-500">{{ $piece->composer ?? 'Unknown composer' }} · {{ App\Support\Instruments::label($piece->instrument) }}</div>
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
