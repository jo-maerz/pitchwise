<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PieceNote extends Model
{
    public $timestamps = false;

    protected $fillable = ['piece_id', 'note_index', 'measure', 'midi_pitch', 'onset_beats', 'duration_beats'];

    protected function casts(): array
    {
        return [
            'note_index' => 'integer',
            'measure' => 'integer',
            'midi_pitch' => 'integer',
            'onset_beats' => 'float',
            'duration_beats' => 'float',
        ];
    }

    public function piece(): BelongsTo
    {
        return $this->belongsTo(Piece::class);
    }
}
