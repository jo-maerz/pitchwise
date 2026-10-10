<?php

namespace App\Http\Requests;

use App\Models\Piece;
use App\Services\LibraryService;
use App\Support\Instruments;
use App\Support\LibraryLocation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StorePieceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Piece::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'location' => ['required', 'string', 'max:40'],
            'title' => ['required', 'string', 'max:200'],
            'composer' => ['nullable', 'string', 'max:200'],
            'instrument' => ['required', 'string', Rule::in(Instruments::keys())],
            'default_bpm' => ['required', 'integer', 'between:30,240'],
            'score' => ['required', 'file', 'max:'.config('practice.max_pdf_kb')],
            // The original PDF next to a MusicXML score, to read along and to practise with the tuner alone.
            'pdf' => ['nullable', 'file', 'max:'.config('practice.max_pdf_kb')],
        ];
    }

    /** MIME detection for MusicXML is unreliable, so check the extension and the first bytes ourselves. */
    public function after(): array
    {
        return [
            fn (Validator $validator) => $this->checkLocation($validator),
            fn (Validator $validator) => $this->checkScore($validator),
            fn (Validator $validator) => $this->checkPdf($validator),
        ];
    }

    /** The chosen library or folder, or null when the form left it out (editing keeps the current one). */
    public function location(): ?LibraryLocation
    {
        return $this->filled('location') ? app(LibraryService::class)->resolve($this->string('location')) : null;
    }

    private function checkLocation(Validator $validator): void
    {
        if (! $this->filled('location')) {
            return;
        }
        $location = $this->location();
        if ($location === null || ! $this->user()->canManageLibrary($location->organizationId)) {
            $validator->errors()->add('location', 'Choose a library or folder you manage.');
        }
    }

    private function checkPdf(Validator $validator): void
    {
        $pdf = $this->file('pdf');
        if (! $pdf || ! $pdf->isValid()) {
            return;
        }
        $head = (string) file_get_contents($pdf->getRealPath(), false, null, 0, 16);
        if (strtolower($pdf->getClientOriginalExtension()) !== 'pdf' || ! str_starts_with($head, '%PDF-')) {
            $validator->errors()->add('pdf', 'This file is not a valid PDF.');
        } elseif (strtolower((string) $this->file('score')?->getClientOriginalExtension()) === 'pdf') {
            $validator->errors()->add('pdf', 'You already chose a PDF as the score. Attach a second PDF only next to a MusicXML file.');
        }
    }

    private function checkScore(Validator $validator): void
    {
        $file = $this->file('score');
        if (! $file || ! $file->isValid()) {
            return;
        }
        $ext = strtolower($file->getClientOriginalExtension());
        if (! in_array($ext, ['musicxml', 'xml', 'mxl', 'pdf'], true)) {
            $validator->errors()->add('score', 'Upload a MusicXML file (.musicxml, .xml, .mxl) or a PDF.');

            return;
        }
        // MusicXML stays small; only PDFs may use the larger limit.
        if ($ext !== 'pdf' && $file->getSize() > config('practice.max_upload_kb') * 1024) {
            $validator->errors()->add('score', 'MusicXML files can be up to '.(config('practice.max_upload_kb') / 1024).' MB.');

            return;
        }
        $head = (string) file_get_contents($file->getRealPath(), false, null, 0, 2048);
        $looksRight = match ($ext) {
            'pdf' => str_starts_with($head, '%PDF-'),
            'mxl' => str_starts_with($head, 'PK'),
            default => str_contains($head, '<score-partwise') || str_contains($head, '<!DOCTYPE score-partwise'),
        };
        if (! $looksRight) {
            $validator->errors()->add('score', $ext === 'pdf' ? 'This file is not a valid PDF.' : 'This file does not look like a partwise MusicXML score.');
        }
    }
}
