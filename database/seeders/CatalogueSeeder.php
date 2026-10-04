<?php

namespace Database\Seeders;

use App\Models\Piece;
use App\Services\PieceService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

/** Three short public-domain / self-written violin pieces everyone can play. */
class CatalogueSeeder extends Seeder
{
    private const PIECES = [
        ['file' => 'open-strings-and-a-major-arpeggio.musicxml', 'title' => 'Open Strings and A Major Arpeggio', 'composer' => 'Exercise', 'bpm' => 60],
        ['file' => 'g-major-scale-two-octaves.musicxml', 'title' => 'G Major Scale, Two Octaves', 'composer' => 'Exercise', 'bpm' => 72],
        ['file' => 'twinkle-twinkle-little-star.musicxml', 'title' => 'Twinkle, Twinkle, Little Star', 'composer' => 'Traditional', 'bpm' => 90],
    ];

    public function run(PieceService $service): void
    {
        foreach (self::PIECES as $p) {
            $path = 'catalogue/'.$p['file'];
            Storage::disk('local')->put($path, file_get_contents(__DIR__.'/scores/'.$p['file']));

            $piece = Piece::updateOrCreate(
                ['owner_id' => null, 'title' => $p['title']],
                ['composer' => $p['composer'], 'instrument' => 'violin', 'default_bpm' => $p['bpm'], 'musicxml_path' => $path],
            );
            $service->extractNotes($piece); // synchronous here; uploads go through the queue
        }
    }
}
