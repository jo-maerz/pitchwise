<?php

namespace App\Services\Omr;

/**
 * Plausibility checks on a recognised score, run on the MusicXML itself so rests count.
 *
 * Recognition mistakes (a missed note, a dot read as nothing, a beam read as the wrong value) make
 * a bar hold more or fewer beats than the time signature says. That is the most useful thing to
 * show the owner: the bar numbers to look at first.
 */
class ScoreSanity
{
    private const MAX_LISTED = 10;

    /** @return list<string> messages for the owner; empty when nothing looks wrong */
    public function check(string $xml, int $noteCount): array
    {
        if ($noteCount === 0) {
            return ['No notes were recognised in the first part.'];
        }

        $bad = $this->badBars($xml);
        if ($bad === []) {
            return [];
        }

        $shown = array_map(fn (array $b) => $b['bar'].' ('.$this->beats($b['has']).' of '.$this->beats($b['should']).' beats)', array_slice($bad, 0, self::MAX_LISTED));
        $more = count($bad) > self::MAX_LISTED ? ' and '.(count($bad) - self::MAX_LISTED).' more' : '';

        return ['Bars that do not add up to the time signature, so a note or rhythm was probably misread: '.implode(', ', $shown).$more.'.'];
    }

    /**
     * The first part's first voice, bar by bar. A short first bar (pickup) and a short last bar are normal and skipped.
     *
     * @return list<array{bar:string, has:float, should:float}>
     */
    private function badBars(string $xml): array
    {
        $root = @simplexml_load_string($xml, options: LIBXML_NONET);
        if ($root === false || ! isset($root->part[0])) {
            return [];
        }

        $measures = $root->part[0]->measure;
        $last = count($measures) - 1;
        $divisions = 1;
        $should = 4.0;
        $bad = [];

        foreach ($measures as $i => $measure) {
            if (isset($measure->attributes->divisions)) {
                $divisions = max(1, (int) $measure->attributes->divisions);
            }
            if (isset($measure->attributes->time->beats)) {
                $should = (float) $measure->attributes->time->beats * 4 / max(1, (int) $measure->attributes->time->{'beat-type'});
            }

            $has = $this->voiceLength($measure, $divisions);
            if (abs($has - $should) < 0.01) {
                continue;
            }
            $short = $has < $should;
            if ($short && ($i === 0 || $i === $last)) {
                continue;
            }
            $bad[] = ['bar' => (string) $measure['number'], 'has' => $has, 'should' => $should];
        }

        return $bad;
    }

    /** Quarter-note length of the first voice in a bar: notes and rests, plus skipped time. */
    private function voiceLength(\SimpleXMLElement $measure, int $divisions): float
    {
        $first = null;
        $total = 0;
        foreach ($measure->children() as $element) {
            $name = $element->getName();
            if ($name === 'note') {
                if (isset($element->chord)) {
                    continue;
                }
                $voice = (string) ($element->voice ?? '1');
                $first ??= $voice;
                if ($voice === $first) {
                    $total += (int) $element->duration;
                }
            } elseif ($name === 'forward') {
                $total += (int) $element->duration;
            } elseif ($name === 'backup') {
                $total -= (int) $element->duration;
            }
        }

        return $total / $divisions;
    }

    private function beats(float $beats): string
    {
        return rtrim(rtrim(number_format($beats, 2, '.', ''), '0'), '.');
    }
}
