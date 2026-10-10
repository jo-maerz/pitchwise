<?php

namespace App\Http\Requests;

use App\Models\Folder;
use App\Services\LibraryService;
use App\Support\LibraryLocation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/** Creating a folder (inside `location`) or renaming one (the route's folder). */
class SaveFolderRequest extends FormRequest
{
    public function authorize(): bool
    {
        $folder = $this->route('folder');
        if ($folder instanceof Folder) {
            return $this->user()->can('update', $folder);
        }
        $parent = $this->parentLocation();

        return $parent !== null && $this->user()->canManageLibrary($parent->organizationId);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100', 'not_regex:#/#'],
            'location' => [$this->route('folder') ? 'prohibited' : 'required', 'string', 'max:40'],
        ];
    }

    public function messages(): array
    {
        return ['name.not_regex' => 'A folder name cannot contain a slash.'];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            $folder = $this->route('folder');
            $parent = $folder instanceof Folder
                ? new LibraryLocation($folder->organization_id, $folder->parent_id)
                : $this->parentLocation();
            if ($parent && app(LibraryService::class)->nameTaken(trim($this->string('name')), $parent->organizationId, $parent->folderId, $folder?->id)) {
                $validator->errors()->add('name', 'There is already a folder with this name here.');
            }
        }];
    }

    /** Where a new folder goes: a library's top level or inside another folder. */
    public function parentLocation(): ?LibraryLocation
    {
        return app(LibraryService::class)->resolve((string) $this->input('location'));
    }
}
