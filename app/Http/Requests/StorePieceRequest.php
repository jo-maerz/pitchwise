<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StorePieceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:200'],
            'composer' => ['nullable', 'string', 'max:200'],
            'instrument' => ['required', 'string', 'in:violin,viola,cello,double bass,flute,voice,other'],
            'default_bpm' => ['required', 'integer', 'between:30,240'],
            'score' => ['required', 'file', 'max:'.config('practice.max_upload_kb')],
        ];
    }

    /** MIME detection for MusicXML is unreliable, so check the extension and the first bytes ourselves. */
    public function after(): array
    {
        return [function (Validator $validator) {
            $file = $this->file('score');
            if (! $file || ! $file->isValid()) {
                return;
            }
            $ext = strtolower($file->getClientOriginalExtension());
            if (! in_array($ext, ['musicxml', 'xml', 'mxl'], true)) {
                $validator->errors()->add('score', 'Upload a MusicXML file (.musicxml, .xml or .mxl).');

                return;
            }
            $head = (string) file_get_contents($file->getRealPath(), false, null, 0, 2048);
            $looksRight = $ext === 'mxl'
                ? str_starts_with($head, 'PK')
                : (str_contains($head, '<score-partwise') || str_contains($head, '<!DOCTYPE score-partwise'));
            if (! $looksRight) {
                $validator->errors()->add('score', 'This file does not look like a partwise MusicXML score.');
            }
        }];
    }
}
