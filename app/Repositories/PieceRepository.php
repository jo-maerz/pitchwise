<?php

namespace App\Repositories;

use App\Models\Piece;
use App\Models\PieceNote;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PieceRepository
{
    /** The pieces of one library folder (NULL folder: the library's top level), with the viewer's finished runs counted. */
    public function paginateIn(User $viewer, ?int $organizationId, ?int $folderId, ?string $instrument = null, int $perPage = 50): LengthAwarePaginator
    {
        return $this->in($viewer, $organizationId, $folderId, $instrument)->paginate($perPage)->withQueryString();
    }

    public function allIn(User $viewer, ?int $organizationId, ?int $folderId, ?string $instrument = null): Collection
    {
        return $this->in($viewer, $organizationId, $folderId, $instrument)->get();
    }

    private function in(User $viewer, ?int $organizationId, ?int $folderId, ?string $instrument): Builder
    {
        return Piece::query()
            ->inLibrary($organizationId)
            ->when($folderId, fn ($q) => $q->where('folder_id', $folderId), fn ($q) => $q->whereNull('folder_id'))
            ->when($instrument, fn ($q) => $q->where('instrument', $instrument))
            ->withCount(['sessions as my_runs' => fn ($q) => $q->where('user_id', $viewer->id)->whereNotNull('finished_at')])
            ->orderBy('title');
    }

    public function create(array $attributes): Piece
    {
        return Piece::create($attributes);
    }

    /**
     * @param  list<array{note_index:int, measure:int, midi_pitch:int, onset_beats:float, duration_beats:float}>  $notes
     */
    public function replaceNotes(Piece $piece, array $notes, array $pieceUpdates): void
    {
        DB::transaction(function () use ($piece, $notes, $pieceUpdates) {
            $piece->notes()->delete();
            foreach (array_chunk($notes, 500) as $chunk) {
                DB::table('piece_notes')->insert(array_map(
                    fn (array $n) => $n + ['piece_id' => $piece->id],
                    $chunk,
                ));
            }
            $piece->update($pieceUpdates + ['note_count' => count($notes), 'parse_status' => 'ready']);
        });
    }

    public function markFailed(Piece $piece): void
    {
        $piece->update(['parse_status' => 'failed']);
    }

    /** @return Collection<int, PieceNote> */
    public function notes(Piece $piece): Collection
    {
        return $piece->notes()->get();
    }
}
