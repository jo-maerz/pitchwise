<?php

namespace App\Http\Requests;

use App\Models\PracticeSession;
use App\Services\PracticeRunService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreNoteResultsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('view', $this->route('session'));
    }

    public function rules(): array
    {
        return [
            'results' => ['present', 'list', 'max:'.PracticeRunService::MAX_RESULTS_PER_BATCH, 'required_unless:finished,true'],
            'results.*' => ['array'],
            'results.*.note_index' => ['required', 'integer:strict'],
            'results.*.expected_midi' => ['required', 'integer:strict'],
            'results.*.detected_hz' => ['nullable', 'numeric:strict', 'between:20,5000'],
            'results.*.clarity' => ['nullable', 'numeric:strict', 'between:0,1'],
            'finished' => ['sometimes', 'boolean:strict'],
        ];
    }

    /** Every note must exist in the score with the pitch the client claims, once per batch. A bad row rejects the batch. */
    public function after(): array
    {
        return [fn (Validator $validator) => $this->matchScore($validator)];
    }

    private function matchScore(Validator $validator): void
    {
        $results = $this->input('results');
        if (! is_array($results)) {
            return;
        }
        /** @var PracticeSession $session */
        $session = $this->route('session');
        $expected = $session->piece->notes()->pluck('midi_pitch', 'note_index');

        $seen = [];
        foreach ($results as $i => $note) {
            $index = is_array($note) ? ($note['note_index'] ?? null) : null;
            if (! is_int($index) || ! $expected->has($index)) {
                $validator->errors()->add("results.$i.note_index", 'Unknown note index for this piece.');

                continue;
            }
            if (isset($seen[$index])) {
                $validator->errors()->add("results.$i.note_index", 'Duplicate note index in this batch.');

                continue;
            }
            $seen[$index] = true;
            if (($note['expected_midi'] ?? null) !== $expected[$index]) {
                $validator->errors()->add("results.$i.expected_midi", "Expected pitch does not match the score (note {$index} is MIDI {$expected[$index]}).");
            }
        }
    }

    public function messages(): array
    {
        return ['results.required_unless' => 'Send at least one result, or finished: true.'];
    }
}
