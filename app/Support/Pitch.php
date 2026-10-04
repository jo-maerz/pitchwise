<?php

namespace App\Support;

/** Small helpers for showing pitches to people. The scoring maths lives in api/src/Verdict.php. */
final class Pitch
{
    private const NAMES = ['C', 'C♯', 'D', 'E♭', 'E', 'F', 'F♯', 'G', 'G♯', 'A', 'B♭', 'B'];

    public static function name(int $midi): string
    {
        return self::NAMES[$midi % 12].(intdiv($midi, 12) - 1);
    }

    public static function frequency(int $midi, float $referenceHz = 440.0): float
    {
        return $referenceHz * 2 ** (($midi - 69) / 12);
    }

    /** "+18 cents", "−7 cents", "0 cents". */
    public static function cents(?float $cents): string
    {
        if ($cents === null) {
            return '–';
        }
        $rounded = (int) round($cents);

        return ($rounded > 0 ? '+' : ($rounded < 0 ? '−' : '')).abs($rounded).' cents';
    }
}
