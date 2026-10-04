<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserPitchStat extends Model
{
    public const CREATED_AT = null;

    protected $fillable = ['user_id', 'midi_pitch', 'attempts', 'in_tune', 'avg_cents'];

    protected function casts(): array
    {
        return [
            'midi_pitch' => 'integer',
            'attempts' => 'integer',
            'in_tune' => 'integer',
            'avg_cents' => 'float',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
