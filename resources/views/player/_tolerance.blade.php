<fieldset>
    <legend class="text-sm font-medium text-gray-700">Counts as in tune within</legend>
    <div class="mt-1 flex items-center gap-2">
        <input name="toleranceValue" type="number" min="1" max="100" step="1" class="w-20 border-gray-300 rounded-md shadow-sm text-sm" aria-label="Tolerance">
        <span class="text-sm text-gray-600">±</span>
        <span class="text-sm text-gray-600" data-tolerance-unit>cents</span>
    </div>
    <div class="mt-2 flex gap-4 text-sm">
        <label class="inline-flex items-center gap-1.5"><input type="radio" name="toleranceMode" value="cents" checked> cents</label>
        <label class="inline-flex items-center gap-1.5"><input type="radio" name="toleranceMode" value="hz"> Hz</label>
    </div>
    <p id="tolerance-hint" class="mt-1 text-xs text-gray-500"></p>
</fieldset>
<label class="block">
    <span class="text-sm font-medium text-gray-700">Concert A (Hz)</span>
    <input name="referenceHz" type="number" min="400" max="480" step="0.5" class="mt-1 w-24 border-gray-300 rounded-md shadow-sm text-sm">
</label>
