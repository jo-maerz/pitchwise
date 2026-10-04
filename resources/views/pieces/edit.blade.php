<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Edit piece') }}</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8">
            <form method="POST" action="{{ route('pieces.update', $piece) }}" enctype="multipart/form-data" class="bg-white shadow-sm sm:rounded-lg p-6 space-y-5">
                @csrf @method('PUT')

                <div>
                    <x-input-label for="score" :value="__('Replace the score (optional)')" />
                    <input id="score" name="score" type="file" accept=".musicxml,.xml,.mxl"
                           class="mt-1 block w-full text-sm text-gray-700 file:mr-3 file:rounded-md file:border-0 file:bg-gray-100 file:px-3 file:py-2" />
                    <p class="mt-1 text-sm text-gray-500">Leave empty to keep the current score. A new file replaces it and is read again.</p>
                    <x-input-error :messages="$errors->get('score')" class="mt-2" />
                </div>

                <div>
                    <x-input-label for="title" :value="__('Title')" />
                    <x-text-input id="title" name="title" class="mt-1 block w-full" :value="old('title', $piece->title)" required maxlength="200" />
                    <x-input-error :messages="$errors->get('title')" class="mt-2" />
                </div>

                <div>
                    <x-input-label for="composer" :value="__('Composer (optional)')" />
                    <x-text-input id="composer" name="composer" class="mt-1 block w-full" :value="old('composer', $piece->composer)" maxlength="200" />
                    <x-input-error :messages="$errors->get('composer')" class="mt-2" />
                </div>

                <div class="grid sm:grid-cols-2 gap-4">
                    <div>
                        <x-input-label for="instrument" :value="__('Instrument')" />
                        <select id="instrument" name="instrument" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
                            @foreach (['violin', 'viola', 'cello', 'double bass', 'flute', 'voice', 'other'] as $instrument)
                                <option value="{{ $instrument }}" @selected(old('instrument', $piece->instrument) === $instrument)>{{ ucfirst($instrument) }}</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('instrument')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="default_bpm" :value="__('Practice tempo (quarter notes per minute)')" />
                        <x-text-input id="default_bpm" name="default_bpm" type="number" min="30" max="240" class="mt-1 block w-full" :value="old('default_bpm', $piece->default_bpm)" required />
                        <x-input-error :messages="$errors->get('default_bpm')" class="mt-2" />
                    </div>
                </div>

                <div class="rounded-md bg-gray-50 p-3 text-sm text-gray-600">
                    Only the first part's top melody line is checked: one note at a time, chords and grace notes skipped,
                    repeats played once. Pick simple pieces for the best results.
                </div>

                <div class="flex justify-end gap-3">
                    <a href="{{ route('pieces.show', $piece) }}" class="px-4 py-2 text-sm text-gray-600">Cancel</a>
                    <x-primary-button>{{ __('Save') }}</x-primary-button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
