<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PracticeSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'piece_id', 'bpm', 'tolerance_mode', 'tolerance_value', 'reference_hz',
        'latency_ms', 'started_at', 'finished_at', 'score_pct', 'aggregated_at',
    ];

    protected function casts(): array
    {
        return [
            'bpm' => 'integer',
            'tolerance_value' => 'float',
            'reference_hz' => 'float',
            'latency_ms' => 'integer',
            'score_pct' => 'float',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'aggregated_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function piece(): BelongsTo
    {
        return $this->belongsTo(Piece::class);
    }

    public function results(): HasMany
    {
        return $this->hasMany(NoteResult::class, 'session_id')->orderBy('note_index');
    }

    /** "±30 cents" or "±30 Hz", for display. */
    public function toleranceLabel(): string
    {
        $value = rtrim(rtrim(number_format($this->tolerance_value, 2, '.', ''), '0'), '.');

        return '±'.$value.' '.($this->tolerance_mode === 'hz' ? 'Hz' : 'cents');
    }
}
