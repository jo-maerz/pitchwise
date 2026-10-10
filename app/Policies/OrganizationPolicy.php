<?php

namespace App\Policies;

use App\Models\Organization;
use App\Models\User;

class OrganizationPolicy
{
    /** Admins, and the organization's own admins, decide who annotates which instruments. */
    public function manageMembers(User $user, Organization $organization): bool
    {
        return $user->canManageLibrary($organization->id);
    }
}
