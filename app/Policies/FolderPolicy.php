<?php

namespace App\Policies;

use App\Models\Folder;
use App\Models\User;

class FolderPolicy
{
    public function view(User $user, Folder $folder): bool
    {
        return $folder->isVisibleTo($user);
    }

    public function update(User $user, Folder $folder): bool
    {
        return $user->canManageLibrary($folder->organization_id);
    }

    public function delete(User $user, Folder $folder): bool
    {
        return $this->update($user, $folder);
    }
}
