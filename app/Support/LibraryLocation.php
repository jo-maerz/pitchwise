<?php

namespace App\Support;

/**
 * Where a piece sits: a library (NULL organization = the shared library) and optionally a folder in it.
 * Forms send it as one key: "root:shared", "root:<organization id>" or "folder:<folder id>".
 */
final readonly class LibraryLocation
{
    public function __construct(
        public ?int $organizationId,
        public ?int $folderId = null,
    ) {}

    public function key(): string
    {
        return $this->folderId !== null
            ? 'folder:'.$this->folderId
            : 'root:'.($this->organizationId ?? 'shared');
    }
}
