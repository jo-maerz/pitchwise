<?php

namespace Database\Seeders;

use App\Models\Folder;
use App\Models\Piece;
use App\Services\PieceService;
use Illuminate\Support\Facades\Storage;

/** Puts score files from database/seeders/scores into a library, creating folders on the way. */
trait SeedsLibrary
{
    /**
     * @param  list<array{file: string, title: string, bpm: int, composer?: string, instrument?: string, folder?: list<string>}>  $pieces
     */
    private function seedPieces(PieceService $service, array $pieces, ?int $organizationId, ?int $ownerId, string $storageDir): void
    {
        foreach ($pieces as $p) {
            $path = $storageDir.'/'.$p['file'];
            Storage::disk('local')->put($path, file_get_contents(__DIR__.'/scores/'.$p['file']));

            $piece = Piece::updateOrCreate(
                ['organization_id' => $organizationId, 'title' => $p['title']],
                [
                    'owner_id' => $ownerId,
                    'folder_id' => isset($p['folder']) ? $this->folder($p['folder'], $organizationId)->id : null,
                    'composer' => $p['composer'] ?? null,
                    'instrument' => $p['instrument'] ?? 'violin',
                    'default_bpm' => $p['bpm'],
                    'musicxml_path' => $path,
                ],
            );
            $service->extractNotes($piece); // synchronous here; uploads go through the queue
        }
    }

    /** @param  list<string>  $names  path from the top of the library */
    private function folder(array $names, ?int $organizationId): Folder
    {
        $folder = null;
        foreach ($names as $name) {
            $folder = Folder::firstOrCreate(['organization_id' => $organizationId, 'parent_id' => $folder?->id, 'name' => $name]);
        }

        return $folder;
    }
}
