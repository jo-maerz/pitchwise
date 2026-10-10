<?php

namespace App\Http\Requests;

use App\Support\Instruments;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAnnotationInstrumentsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manageMembers', $this->route('organization'));
    }

    public function rules(): array
    {
        return [
            'instruments' => ['array'],
            'instruments.*' => ['string', Rule::in(Instruments::keys())],
        ];
    }

    /** @return list<string> */
    public function instruments(): array
    {
        return array_values(array_unique($this->validated('instruments', [])));
    }
}
