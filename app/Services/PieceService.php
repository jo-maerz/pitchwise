<?php

namespace App\Services;

use App\Jobs\ConvertPdfScore;
use App\Jobs\ParseMusicXml;
use App\Models\Piece;
use App\Models\User;
use App\Repositories\PieceRepository;
use App\Services\MusicXml\MusicXmlException;
use App\Services\MusicXml\MusicXmlParser;
use App\Services\Omr\ScoreSanity;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PieceService
{
    public function __construct(
        private readonly PieceRepository $pieces,
        private readonly MusicXmlParser $parser,
        private readonly ScoreSanity $sanity,
    ) {}

    /**
     * Store an uploaded score and queue the job that reads it.
     *
     * $file is MusicXML (full practice) or a PDF (recognised into MusicXML, or used as it is). $pdf is an
     * optional PDF kept alongside MusicXML so the piece can also be practised from the original page.
     */
    public function upload(User $owner, UploadedFile $file, array $details, ?UploadedFile $pdf = null): Piece
    {
        $paths = $this->isPdf($file)
            ? ['source_pdf_path' => $this->storePdf($owner->id, $file)]
            : ['musicxml_path' => $this->storeXml($owner->id, $file), 'source_pdf_path' => $pdf ? $this->storePdf($owner->id, $pdf) : null];

        $piece = $this->pieces->create([
            'owner_id' => $owner->id,
            'title' => $details['title'],
            'composer' => $details['composer'] ?? null,
            'instrument' => $details['instrument'] ?? 'violin',
            'default_bpm' => $details['default_bpm'] ?? 80,
            'parse_status' => 'pending',
        ] + $paths);

        // MusicXML goes straight to the parser, even when a PDF came with it. Only a lone PDF needs recognising.
        $piece->musicxml_path !== null ? ParseMusicXml::dispatch($piece) : ConvertPdfScore::dispatch($piece);

        return $piece;
    }

    private function isPdf(UploadedFile $file): bool
    {
        return strtolower($file->getClientOriginalExtension()) === 'pdf';
    }

    private function storePdf(int $ownerId, UploadedFile $file): string
    {
        return $file->storeAs('pieces/'.$ownerId, Str::uuid().'.pdf', 'local');
    }

    private function storeXml(int $ownerId, UploadedFile $file): string
    {
        $extension = strtolower($file->getClientOriginalExtension()) === 'mxl' ? 'mxl' : 'musicxml';

        return $file->storeAs('pieces/'.$ownerId, Str::uuid().'.'.$extension, 'local');
    }

    /**
     * Edit a piece's details, and optionally replace its files. MusicXML replaces the score and is read again;
     * a PDF replaces the stored PDF (and is recognised again only if the piece has no MusicXML of its own).
     */
    public function update(Piece $piece, array $details, ?UploadedFile $file = null, ?UploadedFile $pdf = null): Piece
    {
        $piece->update([
            'title' => $details['title'],
            'composer' => $details['composer'] ?? null,
            'instrument' => $details['instrument'],
            'default_bpm' => $details['default_bpm'],
        ]);

        $newXml = $file && ! $this->isPdf($file) ? $file : null;
        $newPdf = $file && $this->isPdf($file) ? $file : $pdf;
        if ($newXml === null && $newPdf === null) {
            return $piece;
        }

        $disk = Storage::disk('local');
        $changes = [];

        if ($newPdf !== null) {
            $disk->delete(array_filter([$piece->source_pdf_path]));
            $changes['source_pdf_path'] = $this->storePdf($piece->owner_id, $newPdf);
        }
        if ($newXml !== null) {
            $disk->delete(array_filter([$piece->musicxml_path]));
            $changes += ['musicxml_path' => $this->storeXml($piece->owner_id, $newXml), 'parse_status' => 'pending', 'review_notes' => null];
        } elseif ($piece->musicxml_path === null || $piece->needsReview()) {
            // The recognised score (if any) belongs to the old PDF: drop it and recognise the new one.
            $disk->delete(array_filter([$piece->musicxml_path]));
            $changes += ['musicxml_path' => null, 'note_count' => 0, 'parse_status' => 'pending', 'review_notes' => null];
            $piece->notes()->delete();
        }

        $piece->update($changes);

        if ($newXml !== null) {
            ParseMusicXml::dispatch($piece);
        } elseif (($changes['parse_status'] ?? null) === 'pending') {
            ConvertPdfScore::dispatch($piece);
        }

        return $piece;
    }

    /** Parse the stored file and save its notes. Called by the queued job and by the catalogue seeder. */
    public function extractNotes(Piece $piece, array $extraWarnings = [], bool $recognised = false): void
    {
        $path = Storage::disk('local')->path($piece->musicxml_path);
        try {
            $score = $this->parser->parseFile($path);
        } catch (MusicXmlException $e) {
            $this->pieces->markFailed($piece);
            throw $e;
        }

        $updates = ['beats_per_measure' => $score->beatsPerMeasure];
        if ($piece->composer === null && $score->composer !== null) {
            $updates['composer'] = Str::limit($score->composer, 250);
        }
        // A recognised score is a guess: the owner confirms it before it can be practised.
        if ($recognised) {
            $warnings = [...$extraWarnings, ...$this->sanity->check($this->parser->readXml($path), count($score->notes))];
            $updates['parse_status'] = 'needs_review';
            $updates['review_notes'] = $warnings === [] ? null : implode("\n", $warnings);
        }
        $this->pieces->replaceNotes($piece, $score->notes, $updates);
    }

    /** Store the MusicXML the recogniser produced, then read its notes like any other upload. */
    public function attachRecognisedScore(Piece $piece, string $mxlPath, array $warnings = []): void
    {
        $target = 'pieces/'.$piece->owner_id.'/'.Str::uuid().'.mxl';
        Storage::disk('local')->put($target, file_get_contents($mxlPath));
        $piece->update(['musicxml_path' => $target]);
        $this->extractNotes($piece, $warnings, recognised: true);
    }

    /** The owner chose to practise from the PDF alone: drop the recognised score and its notes. */
    public function usePdfOnly(Piece $piece): void
    {
        Storage::disk('local')->delete(array_filter([$piece->musicxml_path]));
        $piece->notes()->delete();
        $piece->update(['musicxml_path' => null, 'note_count' => 0, 'parse_status' => 'pdf_only', 'review_notes' => null]);
    }

    public function confirmRecognition(Piece $piece): void
    {
        $piece->update(['parse_status' => 'ready', 'review_notes' => null]);
    }

    public function delete(Piece $piece): void
    {
        Storage::disk('local')->delete(array_filter([$piece->musicxml_path, $piece->source_pdf_path]));
        $piece->delete();
    }
}
