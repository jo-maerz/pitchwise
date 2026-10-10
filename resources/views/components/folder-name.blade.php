@props(['folder', 'editable' => false])

@if (! $editable)
    {{ $slot }}
@else
    <div x-data="{ editing: false }" {{ $attributes->merge(['class' => 'min-w-0']) }}>
        <div x-show="! editing" class="flex items-center gap-1.5 min-w-0">
            {{ $slot }}
            <button type="button" @click="editing = true; $nextTick(() => $refs.input.select())"
                    class="relative z-10 shrink-0 p-1 rounded text-gray-400 hover:text-gray-700 hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    title="{{ __('Rename folder') }}" aria-label="{{ __('Rename :name', ['name' => $folder->name]) }}">
                <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M13.6 3.6a2 2 0 012.8 2.8l-.8.8-2.8-2.8.8-.8zM11.4 5.8L3 14.2V17h2.8l8.4-8.4-2.8-2.8z"/></svg>
            </button>
        </div>
        <form x-show="editing" x-cloak method="POST" action="{{ route('folders.update', $folder) }}"
              @keydown.escape.prevent="editing = false" class="relative z-10 flex flex-wrap items-center gap-2">
            @csrf @method('PUT')
            <input x-ref="input" name="name" value="{{ $folder->name }}" required maxlength="100"
                   class="border-gray-300 rounded-md shadow-sm text-sm py-1 focus:border-indigo-500 focus:ring-indigo-500" aria-label="{{ __('Folder name') }}">
            <x-primary-button>{{ __('Save') }}</x-primary-button>
            <button type="button" @click="editing = false" class="text-sm text-gray-600 hover:underline">{{ __('Cancel') }}</button>
        </form>
    </div>
@endif
