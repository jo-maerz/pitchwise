<?php

namespace App\Http\Requests;

class UpdatePieceRequest extends StorePieceRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('piece')) ?? false;
    }

    /** Same rules as an upload, except the file is only needed when replacing the score. */
    public function rules(): array
    {
        return ['score' => ['nullable', 'file', 'max:'.config('practice.max_pdf_kb')]] + parent::rules();
    }
}
