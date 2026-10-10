<div>
    <x-input-label for="location" :value="__('Library and folder')" />
    <select id="location" name="location" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm" required>
        @foreach ($locations as $key => $label)
            <option value="{{ $key }}" @selected(old('location', $selected) === $key)>{{ $label }}</option>
        @endforeach
    </select>
    <p class="mt-1 text-sm text-gray-500">Everyone sees the shared library; an organization's library is seen by its members only.</p>
    <x-input-error :messages="$errors->get('location')" class="mt-2" />
</div>
