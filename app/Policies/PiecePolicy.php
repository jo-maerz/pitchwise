<?php

namespace App\Policies;

use App\Models\Piece;
use App\Models\User;

class PiecePolicy
{
    /** Shared-library pieces are open to everyone signed in; an organization's pieces to its members. */
    public function view(User $user, Piece $piece): bool
    {
        return $piece->isVisibleTo($user);
    }

    public function play(User $user, Piece $piece): bool
    {
        return $this->view($user, $piece) && $piece->isReady();
    }

    public function playPdf(User $user, Piece $piece): bool
    {
        return $this->view($user, $piece) && $piece->hasPdf();
    }

    /** Annotations live in an organization's library only, and only its members see and make them. */
    public function annotate(User $user, Piece $piece): bool
    {
        return $user->belongsToOrganization($piece->organization_id);
    }

    /** The shared layer: the organization's admins, and members given the piece's instrument. */
    public function annotateShared(User $user, Piece $piece): bool
    {
        return $this->annotate($user, $piece)
            && ($user->canManageLibrary($piece->organization_id) || $user->canAnnotateInstrument($piece->instrument));
    }

    public function create(User $user): bool
    {
        return $user->canManageAnyLibrary();
    }

    public function update(User $user, Piece $piece): bool
    {
        return $user->canManageLibrary($piece->organization_id);
    }

    public function delete(User $user, Piece $piece): bool
    {
        return $this->update($user, $piece);
    }
}
