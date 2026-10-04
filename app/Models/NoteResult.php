<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NoteResult extends Model
{
    use HasFactory;

    public $timestamps = false;

    public const VERDICTS = ['in_tune', 'sharp', 'flat', 'wrong_note', 'missed'];

    protected $fillable = [
        'session_id', 'note_index', 'expected_midi', 'detected_midi',
        'detected_hz', 'cents_offset', 'verdict', 'clarity',
    ];

    protected function casts(): array
    {
        return [
            'note_index' => 'integer',
            'expected_midi' => 'integer',
            'detected_midi' => 'integer',
            'detected_hz' => 'float',
            'cents_offset' => 'float',
            'clarity' => 'float',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(PracticeSession::class, 'session_id');
    }
}
