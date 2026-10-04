<?php

namespace App\Jobs;

use App\Models\Piece;
use App\Services\MusicXml\MusicXmlException;
use App\Services\PieceService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class ParseMusicXml implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

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
}
