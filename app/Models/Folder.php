<?php

namespace App\Models;

use App\Models\Concerns\InLibrary;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class Folder extends Model
{
    use HasFactory, InLibrary;

    protected $fillable = ['organization_id', 'parent_id', 'name'];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Folder::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Folder::class, 'parent_id')->orderBy('name');
    }

    public function pieces(): HasMany
    {
        return $this->hasMany(Piece::class);
    }

    /** @return Collection<int, Folder> from the top-level folder down to this one */
    public function ancestry(): Collection
    {
        $chain = collect([$this]);
        for ($folder = $this; $folder->parent_id !== null; $folder = $folder->parent) {
            $chain->prepend($folder->parent);
        }

        return $chain;
    }

    public function isEmpty(): bool
    {
        return ! $this->children()->exists() && ! $this->pieces()->exists();
    }
}
