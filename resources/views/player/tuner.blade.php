<x-app-layout>
    @push('scripts')
        @vite('resources/js/tuner.js')
    @endpush

    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Tuner') }}</h2>
    </x-slot>

    <script type="application/json" id="tuner-config">@json($config)</script>

    <div class="py-8">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 space-y-4">
            <div id="warning" class="rounded-md bg-amber-50 border border-amber-200 p-3 text-sm text-amber-900" role="alert" hidden></div>
            <div class="grid gap-6 md:grid-cols-[minmax(0,1fr)_260px]">
                <div class="bg-white shadow-sm sm:rounded-lg p-6">
                    <div id="gauge"></div>
                    <button id="btn-listen" type="button" class="mt-4 w-full px-4 py-2.5 bg-gray-800 rounded-md font-semibold text-sm text-white hover:bg-gray-700">Start listening</button>
                    <p class="mt-2 text-xs text-gray-500">The dial compares you to the nearest note. Use it to tune, or to find your input delay: play an open string or a long note and watch how quickly the needle settles.</p>
                </div>
                <form id="tuner-settings" class="bg-white shadow-sm sm:rounded-lg p-4 space-y-4">
                    <label class="block">
                        <span class="text-sm font-medium text-gray-700">Instrument</span>
                        <select name="instrument" class="mt-1 w-full border-gray-300 rounded-md shadow-sm text-sm">
                            @foreach (collect($config['instruments'])->groupBy('family') as $family => $instruments)
                                <optgroup label="{{ $family }}">
                                    @foreach ($instruments as $instrument)
                                        <option value="{{ $instrument['key'] }}">{{ $instrument['label'] }}</option>
                                    @endforeach
                                </optgroup>
                            @endforeach
                        </select>
                    </label>
                    <div>
                        <p id="strings-title" class="text-sm font-medium text-gray-700"></p>
                        <ul id="strings" class="pi-strings mt-1 text-sm" aria-labelledby="strings-title"></ul>
                        <p id="transpose-note" class="mt-1 text-xs text-gray-500" hidden></p>
                    </div>
                    @include('player._tolerance')
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
