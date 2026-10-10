<?php

namespace App\Services;

use App\Enums\Role;
use App\Models\Organization;
use Illuminate\Support\Facades\DB;

class OrganizationService
{
    public function __construct(private readonly PieceService $pieces) {}

    /**
     * Deletes the organization with its folders and pieces (and their files and runs).
     * Its members stay, without an organization; its organization admins become plain users.
     */
    public function delete(Organization $organization): void
    {
        DB::transaction(function () use ($organization) {
            $organization->users()->where('role', Role::OrgAdmin)->update(['role' => Role::User]);
            $organization->users()->update(['organization_id' => null, 'annotation_instruments' => null]);
            $organization->pieces()->each(fn ($piece) => $this->pieces->delete($piece));
            $organization->delete();
        });
    }
}
