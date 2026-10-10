<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One layer of marks on a piece: the organization's shared layer (user_id NULL) or one member's own. */
class PieceAnnotation extends Model
{
    protected $fillable = ['piece_id', 'user_id', 'updated_by', 'source_path', 'pages'];

    protected function casts(): array
    {
        return ['pages' => 'array'];
    }

    public function piece(): BelongsTo
    {
        return $this->belongsTo(Piece::class);
    }

    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function scopeShared(Builder $query): Builder
    {
        return $query->whereNull('user_id');
    }
}
