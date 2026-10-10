<?php

namespace App\Services;

use App\Models\Folder;
use App\Models\Organization;
use App\Models\Piece;
use App\Models\User;
use App\Support\LibraryLocation;
use Illuminate\Support\Collection;

class LibraryService
{
    public const SHARED_NAME = 'Shared library';

    /**
     * The libraries a user sees at the top of the Pieces page, shared library first.
     *
     * @return Collection<int, array{organization_id: ?int, name: string}>
     */
    public function librariesFor(User $user): Collection
    {
        $organizations = $user->isAdmin()
            ? Organization::orderBy('name')->get()
            : collect([$user->organization])->filter();

        return collect([['organization_id' => null, 'name' => self::SHARED_NAME]])
            ->concat($organizations->map(fn (Organization $o) => ['organization_id' => $o->id, 'name' => $o->name]));
    }

    /**
     * Every place the user may put a piece, labelled with its full path, for the upload and edit forms.
     *
     * @return array<string, string> location key => "Library / Folder / Subfolder"
     */
    public function locationsFor(User $user): array
    {
        $libraries = $this->librariesFor($user)->filter(fn (array $l) => $user->canManageLibrary($l['organization_id']));
        if ($libraries->isEmpty()) {
            return [];
        }
        $folders = Folder::query()
            ->where(fn ($q) => $libraries->each(fn (array $l) => $q->orWhere(fn ($q) => $q->inLibrary($l['organization_id']))))
            ->get()
            ->keyBy('id');

        $locations = [];
        foreach ($libraries as $library) {
            $locations[(new LibraryLocation($library['organization_id']))->key()] = $library['name'];
            $paths = $folders->where('organization_id', $library['organization_id'])
                ->map(fn (Folder $f) => $library['name'].' / '.$this->pathOf($f, $folders))
                ->sort(SORT_NATURAL | SORT_FLAG_CASE);
            foreach ($paths as $id => $path) {
                $locations[(new LibraryLocation($library['organization_id'], $id))->key()] = $path;
            }
        }

        return $locations;
    }

    /** @param  Collection<int, Folder>  $folders  all folders of the library, keyed by id */
    private function pathOf(Folder $folder, Collection $folders): string
    {
        $names = [$folder->name];
        while ($folder->parent_id !== null && ($folder = $folders->get($folder->parent_id))) {
            array_unshift($names, $folder->name);
        }

        return implode(' / ', $names);
    }

    /**
     * Folders of one library that hold pieces for this instrument, directly or in a subfolder,
     * so the filtered view can hide the others.
     *
     * @return array<int, true> folder id => true
     */
    public function foldersWithInstrument(?int $organizationId, string $instrument): array
    {
        $parents = Folder::inLibrary($organizationId)->pluck('parent_id', 'id');
        $keep = [];
        $direct = Piece::inLibrary($organizationId)->where('instrument', $instrument)->whereNotNull('folder_id')->distinct()->pluck('folder_id');
        foreach ($direct as $id) {
            for (; $id !== null && ! isset($keep[$id]); $id = $parents[$id] ?? null) {
                $keep[$id] = true;
            }
        }

        return $keep;
    }

    /** @return list<string> instrument keys that occur among the pieces this user can see */
    public function instrumentsVisibleTo(User $user): array
    {
        return Piece::visibleTo($user)->distinct()->pluck('instrument')->all();
    }

    public function resolve(string $key): ?LibraryLocation
    {
        if (! preg_match('/^(root|folder):(shared|\d+)$/', $key, $m)) {
            return null;
        }
        if ($m[1] === 'root') {
            if ($m[2] === 'shared') {
                return new LibraryLocation(null);
            }

            return Organization::whereKey((int) $m[2])->exists() ? new LibraryLocation((int) $m[2]) : null;
        }
        $folder = $m[2] === 'shared' ? null : Folder::find((int) $m[2]);

        return $folder ? new LibraryLocation($folder->organization_id, $folder->id) : null;
    }

    public function libraryName(?int $organizationId): string
    {
        return $organizationId === null ? self::SHARED_NAME : (string) Organization::whereKey($organizationId)->value('name');
    }

    public function createFolder(string $name, ?int $organizationId, ?Folder $parent): Folder
    {
        return Folder::create([
            'name' => $name,
            'organization_id' => $parent?->organization_id ?? $organizationId,
            'parent_id' => $parent?->id,
        ]);
    }

    public function nameTaken(string $name, ?int $organizationId, ?int $parentId, ?int $exceptId = null): bool
    {
        return Folder::query()
            ->inLibrary($organizationId)
            ->when($parentId, fn ($q) => $q->where('parent_id', $parentId), fn ($q) => $q->whereNull('parent_id'))
            ->when($exceptId, fn ($q) => $q->whereKeyNot($exceptId))
            ->where('name', $name)
            ->exists();
    }
}
