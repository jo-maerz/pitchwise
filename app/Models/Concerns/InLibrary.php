<?php

namespace App\Models\Concerns;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A folder or piece lives either in the shared library (organization_id NULL, seen by everyone)
 * or in one organization's library (seen by its members). Admins see everything.
 */
trait InLibrary
{
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function isShared(): bool
    {
        return $this->organization_id === null;
    }

    public function isVisibleTo(User $user): bool
    {
        return $user->isAdmin() || $this->isShared() || $this->organization_id === $user->organization_id;
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->isAdmin()) {
            return $query;
        }

        return $query->where(fn (Builder $q) => $q->whereNull($this->qualifyColumn('organization_id'))
            ->when($user->organization_id, fn (Builder $q, int $orgId) => $q->orWhere($this->qualifyColumn('organization_id'), $orgId)));
    }

    /** One library: the shared one for NULL, else that organization's. */
    public function scopeInLibrary(Builder $query, ?int $organizationId): Builder
    {
        return $organizationId === null
            ? $query->whereNull($this->qualifyColumn('organization_id'))
            : $query->where($this->qualifyColumn('organization_id'), $organizationId);
    }
}
