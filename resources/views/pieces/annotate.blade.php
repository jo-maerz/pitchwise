<x-app-layout>
    @push('scripts')
        @vite('resources/js/annotate.js')
    @endpush

    <x-slot name="header">
        <div class="flex flex-wrap items-baseline justify-between gap-2">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ $piece->title }} <span class="text-sm font-normal text-gray-500">· {{ __('Annotations') }}</span></h2>
                <p class="text-sm text-gray-500">{{ $piece->composer }} · {{ App\Support\Instruments::label($piece->instrument) }}</p>
            </div>
            <a href="{{ route('pieces.show', $piece) }}" class="text-sm text-indigo-700 hover:underline">{{ __('Back to the piece') }}</a>
        </div>
    </x-slot>

    <script type="application/json" id="annotate-config">@json($config)</script>

    @php
        $toolClass = 'inline-flex min-w-9 items-center justify-center rounded-md border border-gray-300 bg-white px-2.5 py-1.5 text-sm text-gray-700 hover:bg-gray-50 aria-pressed:border-gray-800 aria-pressed:bg-gray-800 aria-pressed:text-white';
    @endphp

    <div class="py-6">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 space-y-4">
            @if ($hasSharedLayer)
                <p class="text-sm text-gray-600">
                    {{ __('Shared annotations are seen by everyone in :organization.', ['organization' => $piece->organization->name]) }}
                    {{ __('Your own annotations are seen only by you.') }}
                    @if ($shared?->editor)
                        {{ __('Shared annotations last changed by :name on :date.', ['name' => $shared->editor->name, 'date' => $shared->updated_at->format('j M Y, H:i')]) }}
                    @endif
                </p>
            @endif
            @foreach (['shared' => __('The shared annotations'), 'mine' => __('Your annotations')] as $layer => $label)
                @if ($config['layers'][$layer]['outdated'] ?? false)
                    <div class="rounded-md bg-amber-50 border border-amber-200 p-3 text-sm text-amber-900">{{ $label }} {{ __('were made on an earlier upload of this score. Check that they still sit on the right notes.') }}</div>
                @endif
            @endforeach
            <div id="annotate-warning" class="rounded-md bg-amber-50 border border-amber-200 p-3 text-sm text-amber-900" role="alert" hidden></div>

            <div id="annotate-toolbar" class="sticky top-0 z-20 bg-white shadow-sm sm:rounded-lg p-3 space-y-3">
                <div class="flex flex-wrap items-center gap-x-6 gap-y-2 text-sm">
                    @if ($hasSharedLayer)
                        <fieldset class="flex items-center gap-3">
                            <legend class="sr-only">{{ __('Draw on') }}</legend>
                            <span class="text-gray-500">{{ __('Draw on') }}</span>
                            <label class="flex items-center gap-1.5"><input type="radio" name="annotate-layer" value="mine" class="text-indigo-600"> {{ __('Mine') }}</label>
                            <label class="flex items-center gap-1.5 {{ $canEditShared ? '' : 'text-gray-400' }}" @unless ($canEditShared) title="{{ __('Your organization admin decides who edits the shared annotations for :instrument.', ['instrument' => App\Support\Instruments::label($piece->instrument)]) }}" @endunless>
                                <input type="radio" name="annotate-layer" value="shared" class="text-indigo-600" @disabled(! $canEditShared)> {{ __('Shared') }}
                            </label>
                        </fieldset>
                        <fieldset class="flex items-center gap-3">
                            <legend class="sr-only">{{ __('Show') }}</legend>
                            <span class="text-gray-500">{{ __('Show') }}</span>
                            <label class="flex items-center gap-1.5"><input type="checkbox" id="annotate-show-shared" checked class="rounded border-gray-300 text-indigo-600"> {{ __('Shared') }}</label>
                            <label class="flex items-center gap-1.5"><input type="checkbox" id="annotate-show-mine" checked class="rounded border-gray-300 text-indigo-600"> {{ __('Mine') }}</label>
                        </fieldset>
                    @endif
                    <div class="ml-auto flex items-center gap-3">
                        <span id="annotate-status" class="text-gray-500" aria-live="polite"></span>
                        <button id="annotate-undo" type="button" class="{{ $toolClass }} disabled:opacity-40" title="{{ __('Undo (Ctrl/⌘ Z)') }}">↶ {{ __('Undo') }}</button>
                        <button id="annotate-export" type="button" class="{{ $toolClass }}">{{ __('Export PDF') }}</button>
                        <x-primary-button id="annotate-save" type="button" class="disabled:opacity-40">{{ __('Save') }}</x-primary-button>
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <button type="button" data-tool="select" class="{{ $toolClass }}" title="{{ __('Move, resize, rotate; Delete removes the selection') }}">⬚ {{ __('Select') }}</button>
                    <button type="button" data-tool="pen" class="{{ $toolClass }}">✎ {{ __('Pen') }}</button>
                    <button type="button" data-tool="text" class="{{ $toolClass }}" title="{{ __('Click on the page to write') }}">T {{ __('Text') }}</button>
                    <button type="button" data-tool="eraser" class="{{ $toolClass }}" title="{{ __('Click a mark to remove it') }}">⌫ {{ __('Eraser') }}</button>
                    <label class="flex items-center gap-1.5 text-sm text-gray-600">{{ __('Colour') }} <input id="annotate-colour" type="color" value="#1e3a8a" class="h-8 w-10 rounded border-gray-300"></label>
                    <label class="flex items-center gap-1.5 text-sm text-gray-600">{{ __('Pen') }}
                        <select id="annotate-width" class="border-gray-300 rounded-md shadow-sm text-sm py-1">
                            <option value="2">{{ __('fine') }}</option>
                            <option value="4" selected>{{ __('medium') }}</option>
                            <option value="8">{{ __('thick') }}</option>
                            <option value="20">{{ __('highlighter') }}</option>
                        </select>
                    </label>
                </div>

                <div class="flex flex-wrap items-center gap-1.5" role="group" aria-label="{{ __('Stickers: pick one, then click on the page') }}">
                    <span class="mr-1 text-sm text-gray-500">{{ __('Stickers') }}</span>
                    @foreach (['pp' => 'pp', 'p' => 'p', 'mp' => 'mp', 'mf' => 'mf', 'f' => 'f', 'ff' => 'ff', 'sfz' => 'sfz'] as $key => $label)
                        <button type="button" data-tool="sticker" data-sticker="{{ $key }}" class="{{ $toolClass }} font-serif italic font-bold">{{ $label }}</button>
                    @endforeach
                    @foreach (['cresc' => ['cresc. <', 'Crescendo: stretch it to length'], 'dim' => ['dim. >', 'Diminuendo: stretch it to length'], 'rit' => ['rit.', 'Ritardando'], 'accent' => ['>', 'Accent'], 'fermata' => ['𝄐', 'Fermata'], 'breath' => ['’', 'Breath mark'], 'downBow' => ['⊓', 'Down-bow'], 'upBow' => ['V', 'Up-bow']] as $key => [$label, $title])
                        <button type="button" data-tool="sticker" data-sticker="{{ $key }}" class="{{ $toolClass }}" title="{{ __($title) }}">{{ $label }}</button>
                    @endforeach
                </div>

                <div class="flex flex-wrap items-center gap-1.5" role="group" aria-label="{{ __('Fingering: pick a number, then click on the page') }}">
                    <span class="mr-1 text-sm text-gray-500">{{ __('Fingering') }}</span>
                    @foreach (range(0, 5) as $finger)
                        <button type="button" data-tool="sticker" data-sticker="finger{{ $finger }}" class="{{ $toolClass }} font-bold tabular-nums" title="{{ $finger === 0 ? __('Open string') : __('Finger :n', ['n' => $finger]) }}">{{ $finger }}</button>
                    @endforeach
                </div>
            </div>

            <div id="annotate-pages" class="space-y-6"></div>
        </div>
    </div>
</x-app-layout>
