<?php

namespace App\Support;

/**
 * The instruments a piece can be for and the tuner knows. Keys are stored in pieces.instrument.
 *
 * transpose: semitones from written to sounding pitch (clarinet in B♭: -2), so the tuner can show the written note.
 * tuning: the reference notes the tuner lists, as [name, sounding MIDI]: open strings, or the usual tuning notes.
 */
final class Instruments
{
    public const ALL = [
        'violin' => ['label' => 'Violin', 'family' => 'Strings', 'transpose' => 0, 'tuning' => [['G', 55], ['D', 62], ['A', 69], ['E', 76]]],
        'viola' => ['label' => 'Viola', 'family' => 'Strings', 'transpose' => 0, 'tuning' => [['C', 48], ['G', 55], ['D', 62], ['A', 69]]],
        'cello' => ['label' => 'Cello', 'family' => 'Strings', 'transpose' => 0, 'tuning' => [['C', 36], ['G', 43], ['D', 50], ['A', 57]]],
        'double bass' => ['label' => 'Double bass', 'family' => 'Strings', 'transpose' => 0, 'tuning' => [['E', 28], ['A', 33], ['D', 38], ['G', 43]]],
        'flute' => ['label' => 'Flute', 'family' => 'Woodwinds', 'transpose' => 0, 'tuning' => [['A', 69], ['B♭', 70]]],
        'oboe' => ['label' => 'Oboe', 'family' => 'Woodwinds', 'transpose' => 0, 'tuning' => [['A', 69], ['B♭', 70]]],
        'clarinet' => ['label' => 'Clarinet in B♭', 'family' => 'Woodwinds', 'transpose' => -2, 'tuning' => [['B♭', 70], ['A', 69]]],
        'alto saxophone' => ['label' => 'Alto saxophone in E♭', 'family' => 'Woodwinds', 'transpose' => -9, 'tuning' => [['B♭', 70], ['A', 69]]],
        'tenor saxophone' => ['label' => 'Tenor saxophone in B♭', 'family' => 'Woodwinds', 'transpose' => -14, 'tuning' => [['B♭', 58], ['A', 57]]],
        'baritone saxophone' => ['label' => 'Baritone saxophone in E♭', 'family' => 'Woodwinds', 'transpose' => -21, 'tuning' => [['B♭', 58], ['A', 57]]],
        'bassoon' => ['label' => 'Bassoon', 'family' => 'Woodwinds', 'transpose' => 0, 'tuning' => [['A', 57], ['B♭', 58]]],
        'trumpet' => ['label' => 'Trumpet in B♭', 'family' => 'Brass', 'transpose' => -2, 'tuning' => [['B♭', 70], ['F', 65]]],
        'horn' => ['label' => 'Horn in F', 'family' => 'Brass', 'transpose' => -7, 'tuning' => [['F', 65], ['B♭', 58]]],
        'trombone' => ['label' => 'Trombone', 'family' => 'Brass', 'transpose' => 0, 'tuning' => [['B♭', 58], ['F', 53]]],
        'tuba' => ['label' => 'Tuba', 'family' => 'Brass', 'transpose' => 0, 'tuning' => [['B♭', 46], ['F', 41]]],
        'voice' => ['label' => 'Voice', 'family' => 'Other', 'transpose' => 0, 'tuning' => []],
        'other' => ['label' => 'Other', 'family' => 'Other', 'transpose' => 0, 'tuning' => []],
    ];

    /** @return list<string> */
    public static function keys(): array
    {
        return array_keys(self::ALL);
    }

    public static function label(string $key): string
    {
        return self::ALL[$key]['label'] ?? ucfirst($key);
    }

    /**
     * Keys grouped by family, in display order, for <optgroup>s.
     *
     * @param  list<string>|null  $only  limit to these keys
     * @return array<string, array<string, string>> family => [key => label]
     */
    public static function grouped(?array $only = null): array
    {
        $groups = [];
        foreach (self::ALL as $key => $instrument) {
            if ($only === null || in_array($key, $only, true)) {
                $groups[$instrument['family']][$key] = $instrument['label'];
            }
        }

        return $groups;
    }

    /** What the tuner page needs: every instrument except "other". */
    public static function forTuner(): array
    {
        return collect(self::ALL)->except('other')
            ->map(fn (array $i, string $key) => ['key' => $key, 'label' => $i['label'], 'family' => $i['family'], 'transpose' => $i['transpose'], 'tuning' => $i['tuning']])
            ->values()
            ->all();
    }
}
