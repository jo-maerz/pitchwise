<?php

namespace App\Policies;

use App\Models\Piece;
use App\Models\User;

class PiecePolicy
{
    /** Catalogue pieces are open to everyone signed in; uploads only to their owner. */
    public function view(User $user, Piece $piece): bool
    {
        return $piece->isCatalogue() || $piece->owner_id === $user->id;
    }

    public function play(User $user, Piece $piece): bool
    {
        return $this->view($user, $piece) && $piece->isReady();
    }

    /** The PDF page: the original sheet music with a live tuner, for pieces that have a PDF. */
    public function playPdf(User $user, Piece $piece): bool
    {
        return $this->view($user, $piece) && $piece->hasPdf();
    }

    public function update(User $user, Piece $piece): bool
    {
        return $piece->owner_id === $user->id;
    }

    public function delete(User $user, Piece $piece): bool
    {
        return $piece->owner_id === $user->id;
    }
}
