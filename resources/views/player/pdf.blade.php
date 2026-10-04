<x-app-layout>
    @push('scripts')
        @vite('resources/js/pdf-player.js')
    @endpush

    <x-slot name="header">
        <div class="flex flex-wrap items-baseline justify-between gap-2">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ $piece->title }} <span class="text-sm font-normal text-gray-500">· PDF + tuner</span></h2>
                <p class="text-sm text-gray-500">{{ $piece->composer }}</p>
            </div>
            <a href="{{ route('pieces.show', $piece) }}" class="text-sm text-indigo-700 hover:underline">Back to the piece</a>
        </div>
    </x-slot>

    <script type="application/json" id="pdf-player-config">@json($config)</script>

    <div class="py-6">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-4">
            @include('pieces._pdf-limits')
            <div id="warning" class="rounded-md bg-amber-50 border border-amber-200 p-3 text-sm text-amber-900" role="alert" hidden></div>

            <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_340px]">
                {{-- Sidebar first in the DOM so the tuner sits on top on phones --}}
                <aside class="lg:order-2 space-y-4 lg:sticky lg:top-4 self-start">
                    <div class="bg-white shadow-sm sm:rounded-lg p-4">
                        <div id="gauge"></div>
                        <button id="btn-listen" type="button" class="mt-4 w-full px-4 py-2.5 bg-gray-800 rounded-md font-semibold text-sm text-white hover:bg-gray-700">Start listening</button>
                        <p class="mt-2 text-xs text-gray-500">The dial compares you with the <strong>nearest</strong> note, which may not be the note on the page.</p>
                    </div>

                    <div class="bg-white shadow-sm sm:rounded-lg p-4">
                        <div class="flex items-baseline justify-between">
                            <h3 class="font-semibold text-gray-800">Notes you played</h3>
                            <button id="btn-clear" type="button" class="text-xs text-gray-600 underline hover:text-gray-900" hidden>Clear</button>
                        </div>
                        <p id="log-summary" class="text-xs text-gray-500 tabular-nums" aria-live="polite">Nothing yet.</p>
                        <ol id="note-log" class="mt-2 max-h-80 overflow-y-auto text-sm" aria-label="Notes you played, newest first"></ol>
                    </div>

                    <form id="tuner-settings" class="bg-white shadow-sm sm:rounded-lg p-4 space-y-4">
                        <h3 class="font-semibold text-gray-800">Settings</h3>
                        @include('player._tolerance')
                    </form>
                </aside>

                <div class="lg:order-1 min-w-0">
                    <div id="pdf-status" class="text-sm text-gray-600" aria-live="polite">Loading the PDF…</div>
                    <div id="pdf-pages" class="space-y-4"></div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
