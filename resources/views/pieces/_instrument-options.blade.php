@foreach (App\Support\Instruments::grouped() as $family => $instruments)
    <optgroup label="{{ $family }}">
        @foreach ($instruments as $key => $label)
            <option value="{{ $key }}" @selected($selected === $key)>{{ $label }}</option>
        @endforeach
    </optgroup>
@endforeach
