<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Piece extends Model
{
    use HasFactory;

    protected $fillable = [
        'owner_id', 'title', 'composer', 'instrument', 'musicxml_path',
        'default_bpm', 'beats_per_measure', 'note_count', 'parse_status',
    ];

    protected function casts(): array
    {
        return [
            'default_bpm' => 'integer',
            'beats_per_measure' => 'integer',
            'note_count' => 'integer',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function notes(): HasMany
    {
        return $this->hasMany(PieceNote::class)->orderBy('note_index');
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(PracticeSession::class);
    }

    public function isCatalogue(): bool
    {
        return $this->owner_id === null;
    }

    public function isReady(): bool
    {
        return $this->parse_status === 'ready';
    }

    /** Catalogue pieces plus the user's own uploads. */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $query->where(fn (Builder $q) => $q->whereNull('owner_id')->orWhere('owner_id', $user->id));
    }
}
