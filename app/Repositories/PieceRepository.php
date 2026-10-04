<?php

namespace App\Repositories;

use App\Models\Piece;
use App\Models\PieceNote;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PieceRepository
{
    public function paginateVisibleTo(User $user, int $perPage = 20): LengthAwarePaginator
    {
        return Piece::query()
            ->visibleTo($user)
            ->withCount(['sessions as my_runs' => fn ($q) => $q->where('user_id', $user->id)->whereNotNull('finished_at')])
            ->orderByRaw('owner_id IS NULL') // own uploads first, then the catalogue
            ->orderBy('title')
            ->paginate($perPage);
    }

    public function create(array $attributes): Piece
    {
        return Piece::create($attributes);
    }

    /**
     * Replace the expected notes of a piece in one transaction, inserting in chunks.
     *
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
