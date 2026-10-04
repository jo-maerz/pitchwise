<?php

namespace App\Services\MusicXml;

/**
 * The melody line of a score, as the player will check it.
 *
 * @phpstan-type ParsedNote array{note_index: int, measure: int, midi_pitch: int, onset_beats: float, duration_beats: float}
 */
final readonly class ParsedScore
{
    /**
     * @param  list<ParsedNote>  $notes
     */
    public function __construct(
        public ?string $title,
        public ?string $composer,
        public int $beatsPerMeasure,
        public array $notes,
    ) {}
}
