<?php

declare(strict_types=1);

namespace PracticeApi;

/**
 * The pitch rule. Mirrors resources/js/practice/pitch-math.js; both are checked against
 * tests/fixtures/pitch-rule-cases.json so the live display and the stored result always agree.
 *
 *   expected Hz = reference · 2^((midi − 69) / 12)
 *   cents       = 1200 · log2(played / expected)
 *
 * In tune  : within the tolerance — ±N cents (default 30) or ±N Hz, a per-run setting
 * Sharp    : above the tolerance, up to +50 cents
 * Flat     : below the tolerance, down to −50 cents
 * Wrong    : more than 50 cents away, i.e. closer to a neighbouring note than to the
 *            written one (and outside the tolerance)
 * Missed   : no clear pitch in the note's window
 */
final class PitchRule
{
    public const IN_TUNE = 'in_tune';

    public const SHARP = 'sharp';

    public const FLAT = 'flat';

    public const WRONG_NOTE = 'wrong_note';

    public const MISSED = 'missed';

    public const WRONG_NOTE_CENTS = 50.0;

    public const MIN_CLARITY = 0.9;

    public const MODES = ['cents', 'hz'];

    public const TOLERANCE_MIN = 1.0;

    public const TOLERANCE_MAX = 100.0;

    public static function expectedHz(int $midi, float $referenceHz = 440.0): float
    {
        return $referenceHz * 2 ** (($midi - 69) / 12);
    }

    public static function cents(float $playedHz, float $expectedHz): float
    {
        return 1200 * log($playedHz / $expectedHz, 2);
    }

    public static function nearestMidi(float $hz, float $referenceHz = 440.0): int
    {
        return max(0, min(127, (int) round(69 + 12 * log($hz / $referenceHz, 2))));
    }

    /**
     * @return array{outcome: string, cents: ?float, detected_midi: ?int}
     */
    public static function classify(
        int $expectedMidi,
        ?float $detectedHz,
        ?float $clarity,
        string $mode = 'cents',
        float $tolerance = 30.0,
        float $referenceHz = 440.0,
    ): array {
        if ($detectedHz === null || $detectedHz <= 0 || ($clarity !== null && $clarity < self::MIN_CLARITY)) {
            return ['outcome' => self::MISSED, 'cents' => null, 'detected_midi' => null];
        }

        $expectedHz = self::expectedHz($expectedMidi, $referenceHz);
        $cents = self::cents($detectedHz, $expectedHz);
        $inTune = $mode === 'hz'
            ? abs($detectedHz - $expectedHz) <= $tolerance
            : abs($cents) <= $tolerance;

        $outcome = match (true) {
            $inTune => self::IN_TUNE,
            abs($cents) > self::WRONG_NOTE_CENTS => self::WRONG_NOTE,
            $cents > 0 => self::SHARP,
            default => self::FLAT,
        };

        return [
            'outcome' => $outcome,
            'cents' => round($cents, 2),
            'detected_midi' => self::nearestMidi($detectedHz, $referenceHz),
        ];
    }
}
