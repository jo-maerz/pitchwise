<?php

namespace App\Http\Requests;

use App\Support\PitchRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StartPracticeSessionRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'piece_id' => ['required', 'integer:strict'],
            'bpm' => ['required', 'integer:strict', 'between:20,300'],
            'tolerance_mode' => ['sometimes', Rule::in(PitchRule::MODES)],
            'tolerance_value' => ['sometimes', 'numeric:strict', 'between:'.PitchRule::TOLERANCE_MIN.','.PitchRule::TOLERANCE_MAX],
            'reference_hz' => ['sometimes', 'numeric:strict', 'between:400,480'],
            'latency_ms' => ['sometimes', 'integer:strict', 'between:-500,1000'],
        ];
    }
}
