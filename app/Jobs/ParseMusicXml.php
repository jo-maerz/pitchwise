<?php

namespace App\Jobs;

use App\Models\Piece;
use App\Repositories\PieceRepository;
use App\Services\MusicXml\MusicXmlException;
use App\Services\PieceService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class ParseMusicXml implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public bool $deleteWhenMissingModels = true;

    public function __construct(public Piece $piece) {}

    public function handle(PieceService $pieces): void
    {
        try {
            $pieces->extractNotes($this->piece);
        } catch (MusicXmlException $e) {
            // A bad file will not get better on retry; the piece is already marked failed.
            Log::info('MusicXML parse failed', ['piece' => $this->piece->id, 'reason' => $e->getMessage()]);
        }
    }

    /** Any other error, once the retries are used up: without this the piece would read "Reading notes…" for ever. */
    public function failed(?\Throwable $e): void
    {
        $piece = $this->piece->fresh();
        if ($piece?->parse_status === 'pending') {
            app(PieceRepository::class)->markFailed($piece, 'Something went wrong while reading the notes. Try uploading the file again.');
        }
        Log::error('MusicXML parse job failed', ['piece' => $this->piece->id, 'error' => $e?->getMessage()]);
    }
}
