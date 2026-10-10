<?php

namespace Database\Seeders;

use App\Services\PieceService;
use Illuminate\Database\Seeder;

/**
 * The shared library every user sees: Warm-ups, showcase Pieces for violin and cello, parts written for
 * B♭ and E♭ instruments in Winds, and single-line scale exercises after Carl Flesch's Scale System in Scales/Violin and Scales/Cello.
 */
class CatalogueSeeder extends Seeder
{
    use SeedsLibrary;

    private const FLESCH = 'Carl Flesch (exercise pattern)';

    private const PIECES = [
        ['folder' => ['Warm-ups'], 'file' => 'open-strings-and-a-major-arpeggio.musicxml', 'title' => 'Open Strings and A Major Arpeggio', 'composer' => 'Exercise', 'bpm' => 60],
        ['folder' => ['Warm-ups'], 'file' => 'g-major-scale-two-octaves.musicxml', 'title' => 'G Major Scale, Two Octaves', 'composer' => 'Exercise', 'bpm' => 72],
        ['folder' => ['Pieces'], 'file' => 'twinkle-twinkle-little-star.musicxml', 'title' => 'Twinkle, Twinkle, Little Star', 'composer' => 'Traditional', 'bpm' => 90],
        ['folder' => ['Pieces'], 'file' => 'pieces/ode-to-joy-violin.musicxml', 'title' => 'Ode to Joy', 'composer' => 'Ludwig van Beethoven', 'bpm' => 100],
        ['folder' => ['Pieces'], 'file' => 'pieces/minuet-in-g-petzold.musicxml', 'title' => 'Minuet in G major, BWV Anh. 114', 'composer' => 'Christian Petzold', 'bpm' => 96],
        ['folder' => ['Pieces'], 'file' => 'pieces/greensleeves.musicxml', 'title' => 'Greensleeves', 'composer' => 'Traditional', 'bpm' => 72],
        ['folder' => ['Pieces'], 'file' => 'pieces/ode-to-joy-cello.musicxml', 'title' => 'Ode to Joy (cello)', 'composer' => 'Ludwig van Beethoven', 'bpm' => 100, 'instrument' => 'cello'],
        ['folder' => ['Pieces'], 'file' => 'pieces/bach-cello-suite-1-prelude-opening.musicxml', 'title' => 'Cello Suite No. 1, Prelude (bars 1-8)', 'composer' => 'Johann Sebastian Bach', 'bpm' => 60, 'instrument' => 'cello'],

        ['folder' => ['Winds', 'B♭ instruments'], 'file' => 'winds/b-flat/ode-to-joy-clarinet.musicxml', 'title' => 'Ode to Joy (clarinet in B♭)', 'composer' => 'Ludwig van Beethoven', 'bpm' => 100, 'instrument' => 'clarinet'],
        ['folder' => ['Winds', 'B♭ instruments'], 'file' => 'winds/b-flat/minuet-in-g-trumpet.musicxml', 'title' => 'Minuet in G (trumpet in B♭)', 'composer' => 'Christian Petzold', 'bpm' => 96, 'instrument' => 'trumpet'],
        ['folder' => ['Winds', 'B♭ instruments'], 'file' => 'winds/b-flat/greensleeves-tenor-sax.musicxml', 'title' => 'Greensleeves (tenor saxophone)', 'composer' => 'Traditional', 'bpm' => 72, 'instrument' => 'tenor saxophone'],
        ['folder' => ['Winds', 'E♭ instruments'], 'file' => 'winds/e-flat/ode-to-joy-alto-sax.musicxml', 'title' => 'Ode to Joy (alto saxophone)', 'composer' => 'Ludwig van Beethoven', 'bpm' => 100, 'instrument' => 'alto saxophone'],
        ['folder' => ['Winds', 'E♭ instruments'], 'file' => 'winds/e-flat/jingle-bells-alto-sax.musicxml', 'title' => 'Jingle Bells (alto saxophone)', 'composer' => 'James Lord Pierpont', 'bpm' => 112, 'instrument' => 'alto saxophone'],
        ['folder' => ['Winds', 'E♭ instruments'], 'file' => 'winds/e-flat/twinkle-baritone-sax.musicxml', 'title' => 'Twinkle, Twinkle, Little Star (baritone saxophone)', 'composer' => 'Traditional', 'bpm' => 90, 'instrument' => 'baritone saxophone'],

        ['folder' => ['Scales', 'Violin'], 'file' => 'scales/violin/1-g-major-one-string-scales.musicxml', 'title' => 'G major: scales on one string', 'bpm' => 72, 'composer' => self::FLESCH],
        ['folder' => ['Scales', 'Violin'], 'file' => 'scales/violin/2-g-major-three-octave-scale.musicxml', 'title' => 'G major: three-octave scale', 'bpm' => 60, 'composer' => self::FLESCH],
        ['folder' => ['Scales', 'Violin'], 'file' => 'scales/violin/3-a-major-arpeggios.musicxml', 'title' => 'A major: arpeggios on A', 'bpm' => 50, 'composer' => self::FLESCH],
        ['folder' => ['Scales', 'Violin'], 'file' => 'scales/violin/4-d-major-broken-thirds.musicxml', 'title' => 'D major: scale in broken thirds', 'bpm' => 66, 'composer' => self::FLESCH],
        ['folder' => ['Scales', 'Violin'], 'file' => 'scales/violin/5-chromatic-scale-on-g.musicxml', 'title' => 'Chromatic scale on G, two octaves', 'bpm' => 60, 'composer' => self::FLESCH],
        ['folder' => ['Scales', 'Cello'], 'file' => 'scales/cello/1-c-major-one-string-scales.musicxml', 'title' => 'C major: scales on one string', 'bpm' => 72, 'instrument' => 'cello', 'composer' => self::FLESCH],
        ['folder' => ['Scales', 'Cello'], 'file' => 'scales/cello/2-c-major-three-octave-scale.musicxml', 'title' => 'C major: three-octave scale', 'bpm' => 60, 'instrument' => 'cello', 'composer' => self::FLESCH],
        ['folder' => ['Scales', 'Cello'], 'file' => 'scales/cello/3-c-major-arpeggios.musicxml', 'title' => 'C major: arpeggios on C', 'bpm' => 50, 'instrument' => 'cello', 'composer' => self::FLESCH],
        ['folder' => ['Scales', 'Cello'], 'file' => 'scales/cello/4-g-major-broken-thirds.musicxml', 'title' => 'G major: scale in broken thirds', 'bpm' => 66, 'instrument' => 'cello', 'composer' => self::FLESCH],
        ['folder' => ['Scales', 'Cello'], 'file' => 'scales/cello/5-chromatic-scale-on-c.musicxml', 'title' => 'Chromatic scale on C, two octaves', 'bpm' => 60, 'instrument' => 'cello', 'composer' => self::FLESCH],
    ];

    public function run(PieceService $service): void
    {
        $this->seedPieces($service, self::PIECES, null, null, 'catalogue');
    }
}
