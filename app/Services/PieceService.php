<?php

namespace App\Services;

use App\Jobs\ParseMusicXml;
use App\Models\Piece;
use App\Models\User;
use App\Repositories\PieceRepository;
use App\Services\MusicXml\MusicXmlException;
use App\Services\MusicXml\MusicXmlParser;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PieceService
{
    public function __construct(
        private readonly PieceRepository $pieces,
        private readonly MusicXmlParser $parser,
    ) {}

    /** Store an uploaded score and queue the job that extracts its notes. */
    public function upload(User $owner, UploadedFile $file, array $details): Piece
    {
        $extension = strtolower($file->getClientOriginalExtension()) === 'mxl' ? 'mxl' : 'musicxml';
        $path = $file->storeAs('pieces/'.$owner->id, Str::uuid().'.'.$extension, 'local');

        $piece = $this->pieces->create([
            'owner_id' => $owner->id,
            'title' => $details['title'],
            'composer' => $details['composer'] ?? null,
            'instrument' => $details['instrument'] ?? 'violin',
            'default_bpm' => $details['default_bpm'] ?? 80,
            'musicxml_path' => $path,
            'parse_status' => 'pending',
        ]);

        ParseMusicXml::dispatch($piece);

        return $piece;
    }

    /** Edit a piece's details; a new file replaces the stored score and is parsed again. */
    public function update(Piece $piece, array $details, ?UploadedFile $file = null): Piece
    {
        $attributes = [
            'title' => $details['title'],
            'composer' => $details['composer'] ?? null,
            'instrument' => $details['instrument'],
            'default_bpm' => $details['default_bpm'],
        ];

        if ($file === null) {
            $piece->update($attributes);

            return $piece;
        }

        $oldPath = $piece->musicxml_path;
        $extension = strtolower($file->getClientOriginalExtension()) === 'mxl' ? 'mxl' : 'musicxml';
        $attributes['musicxml_path'] = $file->storeAs('pieces/'.$piece->owner_id, Str::uuid().'.'.$extension, 'local');
        $attributes['parse_status'] = 'pending';
        $piece->update($attributes);
        Storage::disk('local')->delete($oldPath);

        ParseMusicXml::dispatch($piece);

        return $piece;
    }

    /** Parse the stored file and save its notes. Called by the queued job and by the catalogue seeder. */
    public function extractNotes(Piece $piece): void
    {
        try {
            $score = $this->parser->parseFile(Storage::disk('local')->path($piece->musicxml_path));
        } catch (MusicXmlException $e) {
            $this->pieces->markFailed($piece);
            throw $e;
        }

        $updates = ['beats_per_measure' => $score->beatsPerMeasure];
        if ($piece->composer === null && $score->composer !== null) {
            $updates['composer'] = Str::limit($score->composer, 250);
        }
        $this->pieces->replaceNotes($piece, $score->notes, $updates);
    }

    public function delete(Piece $piece): void
    {
        Storage::disk('local')->delete($piece->musicxml_path);
        $piece->delete();
    }
}
