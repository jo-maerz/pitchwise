<x-app-layout>
    @push('scripts')
        @vite('resources/js/player.js')
    @endpush

    <x-slot name="header">
        <div class="flex flex-wrap items-baseline justify-between gap-2">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ $piece->title }}</h2>
            <p class="text-sm text-gray-500">{{ $piece->composer }}</p>
        </div>
    </x-slot>

    <script type="application/json" id="player-config">@json($config)</script>

    <div class="py-6">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div id="warning" class="mb-4 rounded-md bg-amber-50 border border-amber-200 p-3 text-sm text-amber-900" role="alert" hidden></div>

            <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_340px]">
                {{-- Sidebar first in the DOM so the tuner sits on top on phones --}}
                <aside class="lg:order-2 space-y-4 lg:sticky lg:top-4 self-start">
                    <div class="bg-white shadow-sm sm:rounded-lg p-4">
                        <div id="gauge"></div>
                        <ul class="mt-3 grid grid-cols-3 gap-2 text-center text-xs" aria-label="This run so far">
                            <li class="rounded bg-gray-50 py-1.5"><div class="text-lg font-semibold tabular-nums" data-tally-score>–</div>in tune</li>
                            <li class="rounded bg-gray-50 py-1.5" data-verdict="sharp"><div class="text-lg font-semibold tabular-nums" data-tally="sharp">0</div><span class="pi-dot"></span>too high</li>
                            <li class="rounded bg-gray-50 py-1.5" data-verdict="flat"><div class="text-lg font-semibold tabular-nums" data-tally="flat">0</div><span class="pi-dot"></span>too low</li>
                            <li class="rounded bg-gray-50 py-1.5" data-verdict="in_tune"><div class="text-lg font-semibold tabular-nums" data-tally="in_tune">0</div><span class="pi-dot"></span>notes ok</li>
                            <li class="rounded bg-gray-50 py-1.5" data-verdict="wrong_note"><div class="text-lg font-semibold tabular-nums" data-tally="wrong_note">0</div><span class="pi-dot"></span>wrong</li>
                            <li class="rounded bg-gray-50 py-1.5" data-verdict="missed"><div class="text-lg font-semibold tabular-nums" data-tally="missed">0</div><span class="pi-dot"></span>missed</li>
                        </ul>
                        <div class="mt-4 flex gap-2">
                            <button id="btn-start" type="button" class="flex-1 inline-flex justify-center items-center px-4 py-2.5 bg-gray-800 rounded-md font-semibold text-sm text-white hover:bg-gray-700 disabled:opacity-40" disabled>Start</button>
                            <button id="btn-skip" type="button" class="inline-flex justify-center items-center px-3 py-2.5 border border-gray-300 rounded-md font-semibold text-sm text-gray-700 hover:bg-gray-50 disabled:opacity-40" title="Skip this note (counts as missed). Shortcut: right arrow" hidden disabled>Skip</button>
                            <button id="btn-stop" type="button" class="inline-flex justify-center items-center px-4 py-2.5 border border-gray-300 rounded-md font-semibold text-sm text-gray-700 hover:bg-gray-50 disabled:opacity-40" disabled>Stop</button>
                        </div>
                        <p id="status" class="mt-2 text-sm text-gray-600" aria-live="polite">Loading the score…</p>
                        <p id="progress" class="text-xs text-gray-500 tabular-nums"></p>
                    </div>

                    <form id="settings" class="bg-white shadow-sm sm:rounded-lg p-4 space-y-4">
                        <h3 class="font-semibold text-gray-800">Settings</h3>
                        <label class="block">
                            <span class="text-sm font-medium text-gray-700">Mode</span>
                            <select name="playMode" class="mt-1 w-full border-gray-300 rounded-md shadow-sm text-sm">
                                <option value="follow">Follow the tempo</option>
                                <option value="wait">Wait for me</option>
                            </select>
                            <span class="block text-xs text-gray-500" data-wait-only>The cursor stays on a note until you have played it (hold it for a moment). No tempo, no count-in.</span>
                        </label>
                        <div class="grid grid-cols-2 gap-3">
                            <label class="block" data-follow-only>
                                <span class="text-sm font-medium text-gray-700">Tempo ♩ =</span>
                                <input name="bpm" type="number" min="20" max="300" class="mt-1 w-full border-gray-300 rounded-md shadow-sm text-sm">
                            </label>
                            <label class="block">
                                <span class="text-sm font-medium text-gray-700">Layout</span>
                                <select name="layout" class="mt-1 w-full border-gray-300 rounded-md shadow-sm text-sm">
                                    <option value="pages">Pages</option>
                                    <option value="continuous">Continuous</option>
                                </select>
                            </label>
                        </div>
                        <div class="grid grid-cols-2 gap-3" data-page-range>
                            <label class="block"><span class="text-sm font-medium text-gray-700">From page</span>
                                <select name="fromPage" class="mt-1 w-full border-gray-300 rounded-md shadow-sm text-sm"><option value="1">1</option></select></label>
                            <label class="block"><span class="text-sm font-medium text-gray-700">To page</span>
                                <select name="toPage" class="mt-1 w-full border-gray-300 rounded-md shadow-sm text-sm"><option value="1">1</option></select></label>
                        </div>

                        @include('player._tolerance')

                        <details class="text-sm">
                            <summary class="cursor-pointer font-medium text-gray-700">Timing and microphone</summary>
                            <div class="mt-3 space-y-3">
                                <label class="block" data-follow-only>
                                    <span class="text-gray-700">Input delay (ms)</span>
                                    <input name="latencyMs" type="number" min="-500" max="1000" step="10" class="mt-1 w-24 border-gray-300 rounded-md shadow-sm text-sm">
                                    <span class="block text-xs text-gray-500">If notes are judged too early (the previous note bleeds in), raise this. Typical: 50–150 ms.</span>
                                </label>
                                <label class="block">
                                    <span class="text-gray-700">Ignore sounds quieter than</span>
                                    <select name="noiseGateDb" class="mt-1 border-gray-300 rounded-md shadow-sm text-sm">
                                        <option value="-40">−40 dB (quiet room)</option>
                                        <option value="-30">−30 dB (normal)</option>
                                        <option value="-20">−20 dB (noisy room)</option>
                                    </select>
                                </label>
                                <label class="flex items-center gap-2" data-follow-only><input type="checkbox" name="countIn"> One-bar count-in</label>
                                <label class="flex items-center gap-2" data-follow-only><input type="checkbox" name="metronome"> Metronome while playing</label>
                            </div>
                        </details>
                    </form>
                </aside>

                <section class="lg:order-1 space-y-6 min-w-0">
                    <section id="report" class="bg-white shadow-sm sm:rounded-lg p-6" hidden aria-labelledby="report-title">
                        <div class="flex flex-wrap items-end justify-between gap-4">
                            <div>
                                <h3 id="report-title" class="text-sm uppercase tracking-wider text-gray-500">Result</h3>
                                <div class="text-5xl font-bold tabular-nums" data-report-score></div>
                                <p class="text-sm text-gray-600" data-report-sub></p>
                            </div>
                            <a data-report-link class="text-sm font-medium text-indigo-700 hover:underline" hidden>Open the saved report →</a>
                        </div>
                        <ul class="mt-4 flex flex-wrap gap-2" data-report-counts></ul>
                        <div class="mt-5 grid gap-6 md:grid-cols-3 text-sm [&_h4]:font-semibold [&_h4]:mb-2 [&_ol]:list-decimal [&_ol]:pl-5 [&_ul]:space-y-1">
                            <div data-report-weakest></div>
                            <div data-report-notes></div>
                            <div data-report-pages></div>
                        </div>
                    </section>

                    <div class="bg-white shadow-sm sm:rounded-lg p-2">
                        <ul class="flex flex-wrap gap-3 px-2 py-1 text-xs text-gray-600" aria-label="Note colours">
                            <li data-verdict="in_tune"><span class="pi-dot"></span>in tune</li>
                            <li data-verdict="sharp"><span class="pi-dot"></span>too high</li>
                            <li data-verdict="flat"><span class="pi-dot"></span>too low</li>
                            <li data-verdict="wrong_note"><span class="pi-dot"></span>wrong note</li>
                            <li data-verdict="missed"><span class="pi-dot"></span>missed</li>
                        </ul>
                        <div id="score" class="pi-score"></div>
                    </div>
                </section>
            </div>
        </div>
    </div>

    <div id="countdown" class="pi-countdown" hidden aria-hidden="true"></div>
    <div id="toast" class="pi-toast" role="status" hidden></div>
</x-app-layout>
