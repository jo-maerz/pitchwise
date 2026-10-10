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
        'source_pdf_path', 'review_notes',
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

    public function hasPdf(): bool
    {
        return $this->source_pdf_path !== null;
    }

    /** Practising from the PDF alone: no notes to follow, just the live tuner next to the page. */
    public function isPdfOnly(): bool
    {
        return $this->parse_status === 'pdf_only';
    }

    public function needsReview(): bool
    {
        return $this->parse_status === 'needs_review';
    }

    /** Still being worked on by the queue: the page refreshes itself while this is true. */
    public function isProcessing(): bool
    {
        return in_array($this->parse_status, ['pending', 'converting'], true);
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $query->where(fn (Builder $q) => $q->whereNull('owner_id')->orWhere('owner_id', $user->id));
    }
}
