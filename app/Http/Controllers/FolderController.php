<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveFolderRequest;
use App\Models\Folder;
use App\Services\LibraryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class FolderController extends Controller
{
    public function __construct(private readonly LibraryService $library) {}

    public function store(SaveFolderRequest $request): RedirectResponse
    {
        $parent = $request->parentLocation();
        $folder = $this->library->createFolder(trim($request->string('name')), $parent->organizationId, $parent->folderId ? Folder::find($parent->folderId) : null);

        return redirect()->route('folders.show', $folder)->with('status', 'Folder created.');
    }

    public function update(SaveFolderRequest $request, Folder $folder): RedirectResponse
    {
        $folder->update(['name' => trim($request->string('name'))]);

        // Renamed from its own page or from a row in the list: stay where the user was.
        return back()->with('status', 'Folder renamed.');
    }

    /** Only empty folders can go, so no piece (and none of its practice history) disappears by accident. */
    public function destroy(Folder $folder): RedirectResponse
    {
        Gate::authorize('delete', $folder);
        if (! $folder->isEmpty()) {
            return back()->withErrors(['folder' => 'Move or delete what is inside the folder first.']);
        }
        $parentId = $folder->parent_id;
        $folder->delete();

        return redirect($parentId ? route('folders.show', $parentId) : route('pieces.index'))->with('status', 'Folder deleted.');
    }
}
