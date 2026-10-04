<?php

namespace App\Services\MusicXml;

use XMLReader;
use ZipArchive;

/**
 * Streams a MusicXML (.musicxml/.xml) or compressed MusicXML (.mxl) file and extracts
 * the notes the player will check: first part, voice 1, pitched notes only.
 *
 * Rules (kept identical to the browser's note mapping in resources/js/practice/score-view.js):
 *  - rests, grace notes, cue notes and unpitched notes are skipped;
 *  - in a chord only the first written note counts (<chord/> notes are skipped);
 *  - a tied note continues the previous note: its duration is added, no new note;
 *  - repeats are expanded the way they are played: forward/backward repeat bars (with an
 *    optional `times`), numbered endings (voltas) and the jumps written as <sound> marks:
 *    D.C., D.S. (segno), Fine and To Coda. On the way back repeats are not played again and
 *    the highest-numbered ending is taken. The written measure number is kept;
 *  - beats are quarter notes, whatever the time signature.
 */
class MusicXmlParser
{
    private const STEPS = ['C' => 0, 'D' => 2, 'E' => 4, 'F' => 5, 'G' => 7, 'A' => 9, 'B' => 11];

    public function parseFile(string $path): ParsedScore
    {
        return $this->parseString($this->readXml($path));
    }

    public function parseString(string $xml): ParsedScore
    {
        $reader = new XMLReader;
        // LIBXML_NONET: never fetch external DTDs. Entities are not substituted (no LIBXML_NOENT).
        if (! @$reader->XML($xml, null, LIBXML_NONET | LIBXML_COMPACT)) {
            throw new MusicXmlException('The file is not readable XML.');
        }

        $title = $movementTitle = $composer = null;
        $beatsPerMeasure = null;
        $measures = [];          // every written measure: its notes and repeat marks
        $current = null;
        $endingNumbers = [];     // volta numbers in force; carried until the ending stops
        $endingStopped = false;

        $inFirstPart = false;
        $partsSeen = 0;
        $divisions = 1;
        $measureIndex = 0;
        $pos = 0.0;              // beats from start of current measure
        $measureLength = 0.0;
        $sawRoot = false;

        $ok = @$reader->read();
        while ($ok) {
            $this->skipped = false;
            if ($reader->nodeType === XMLReader::ELEMENT) {
                $name = $reader->localName;
                if (! $sawRoot) {
                    if ($name !== 'score-partwise') {
                        throw new MusicXmlException($name === 'score-timewise'
                            ? 'Timewise MusicXML is not supported; export as partwise MusicXML.'
                            : 'This is not a MusicXML score.');
                    }
                    $sawRoot = true;
                }

                if (! $inFirstPart) {
                    match ($name) {
                        'work-title' => $title = $this->text($reader),
                        'movement-title' => $movementTitle = $this->text($reader),
                        'creator' => $reader->getAttribute('type') === 'composer' ? $composer = $this->text($reader) : null,
                        default => null,
                    };
                    if ($name === 'part' && $reader->depth === 1) {
                        $partsSeen++;
                        $inFirstPart = $partsSeen === 1;
                        if (! $inFirstPart) {
                            break; // only the first part is checked
                        }
                    }
                    $ok = @$reader->read();

                    continue;
                }

                switch ($name) {
                    case 'measure':
                        $measureIndex++;
                        $pos = 0.0;
                        $measureLength = 0.0;
                        $current = ['index' => $measureIndex, 'length' => 0.0, 'notes' => [], 'repeatStart' => false, 'repeatEnd' => null, 'endings' => $endingNumbers, 'segno' => [], 'coda' => [], 'jump' => null, 'fine' => false, 'toCoda' => null];
                        break;
                    case 'direction':
                        $direction = $this->element($reader);
                        foreach ($direction->sound as $sound) {
                            $this->markSound($current, $sound);
                        }
                        break;
                    case 'sound':
                        $this->markSound($current, $this->element($reader));
                        break;
                    case 'barline':
                        $barline = $this->element($reader);
                        if ($current === null) {
                            break;
                        }
                        if (isset($barline->repeat)) {
                            if ((string) $barline->repeat['direction'] === 'forward') {
                                $current['repeatStart'] = true;
                            } else {
                                $times = (int) $barline->repeat['times'];
                                $current['repeatEnd'] = $times > 1 ? $times : 2; // total plays of the section
                            }
                        }
                        if (isset($barline->ending)) {
                            $type = (string) $barline->ending['type'];
                            if ($type === 'start') {
                                $endingNumbers = array_map('intval', preg_split('/[\s,]+/', trim((string) $barline->ending['number']), -1, PREG_SPLIT_NO_EMPTY));
                                $current['endings'] = $endingNumbers;
                            } else {
                                $endingStopped = true;
                            }
                        }
                        break;
                    case 'attributes':
                        $attrs = $this->element($reader);
                        if (isset($attrs->divisions) && (float) $attrs->divisions > 0) {
                            $divisions = (float) $attrs->divisions;
                        }
                        if ($beatsPerMeasure === null && isset($attrs->time->beats, $attrs->time->{'beat-type'})) {
                            // "3+2" style compound numerators: add the parts.
                            $num = array_sum(array_map('floatval', explode('+', (string) $attrs->time->beats)));
                            $den = (float) $attrs->time->{'beat-type'};
                            if ($num > 0 && $den > 0) {
                                $beatsPerMeasure = max(1, (int) round($num * 4 / $den));
                            }
                        }
                        break;
                    case 'backup':
                        $pos -= $this->duration($this->element($reader), $divisions);
                        break;
                    case 'forward':
                        $pos += $this->duration($this->element($reader), $divisions);
                        $measureLength = max($measureLength, $pos);
                        break;
                    case 'note':
                        $note = $this->element($reader);
                        $isChord = isset($note->chord);
                        $isGrace = isset($note->grace);
                        $duration = $isGrace ? 0.0 : $this->duration($note, $divisions);
                        $onset = $isChord ? $pos - $duration : $pos;
                        if (! $isChord) {
                            $pos += $duration;
                            $measureLength = max($measureLength, $pos);
                        }

                        $voice = isset($note->voice) ? trim((string) $note->voice) : '1';
                        if ($isChord || $isGrace || isset($note->cue) || ! isset($note->pitch) || $voice !== '1') {
                            break;
                        }

                        $current['notes'][] = [
                            'measure' => $measureIndex,
                            'midi_pitch' => $this->midi($note->pitch),
                            'onset' => round($onset, 4),
                            'duration' => round($duration, 4),
                            'tie_stop' => in_array('stop', array_map(fn ($tie) => (string) $tie['type'], iterator_to_array($note->tie, false)), true),
                        ];
                        break;
                }
            } elseif ($reader->nodeType === XMLReader::END_ELEMENT && $inFirstPart) {
                if ($reader->localName === 'measure') {
                    if ($current !== null) {
                        $current['length'] = $measureLength;
                        $measures[] = $current;
                        $current = null;
                    }
                    if ($endingStopped) {
                        $endingNumbers = [];
                        $endingStopped = false;
                    }
                } elseif ($reader->localName === 'part') {
                    break;
                }
            }
            // element() already moved the reader past the subtree it consumed.
            $ok = $this->skipped ? $this->skippedOk : @$reader->read();
        }
        $reader->close();

        $notes = $this->unroll($measures);

        if (! $sawRoot) {
            throw new MusicXmlException('This is not a MusicXML score.');
        }
        if ($notes === []) {
            throw new MusicXmlException('No pitched notes found in the first part.');
        }

        return new ParsedScore(
            title: $title ?? $movementTitle,
            composer: $composer,
            beatsPerMeasure: $beatsPerMeasure ?? 4,
            notes: $notes,
        );
    }

    /** Record segno/coda/jump marks of a <sound> element on the measure being read. */
    private function markSound(?array &$measure, \SimpleXMLElement $sound): void
    {
        if ($measure === null) {
            return;
        }
        if (isset($sound['segno'])) {
            $measure['segno'][] = (string) $sound['segno'];
        }
        if (isset($sound['coda'])) {
            $measure['coda'][] = (string) $sound['coda'];
        }
        if (isset($sound['dacapo']) && (string) $sound['dacapo'] === 'yes') {
            $measure['jump'] = ['segno' => null];
        }
        if (isset($sound['dalsegno'])) {
            $measure['jump'] = ['segno' => (string) $sound['dalsegno']];
        }
        if (isset($sound['fine'])) {
            $measure['fine'] = true;
        }
        if (isset($sound['tocoda'])) {
            $measure['toCoda'] = (string) $sound['tocoda'];
        }
    }

    /**
     * Lay the written measures out in playing order and number the notes along the way.
     *
     * @param  list<array<string, mixed>>  $measures
     * @return list<array<string, mixed>>
     */
    private function unroll(array $measures): array
    {
        $notes = [];
        $start = 0;          // where the open repeat section begins
        $pass = 1;           // which time through that section we are on
        $measureStart = 0.0; // beats from start of piece
        $guard = 0;
        $jumped = false;     // after a D.C./D.S.: no repeats, last ending, stop at Fine
        $segnos = $codas = [];
        $lastEnding = 0;
        foreach ($measures as $i => $m) {
            foreach ($m['segno'] as $name) {
                $segnos[$name] ??= $i;
            }
            foreach ($m['coda'] as $name) {
                $codas[$name] ??= $i;
            }
            $lastEnding = max($lastEnding, 0, ...$m['endings']);
        }

        for ($i = 0, $count = count($measures); $i < $count && $guard++ < 100000; $i++) {
            $m = $measures[$i];
            if (! $jumped && $m['repeatStart'] && $i !== $start && $pass === 1) {
                $start = $i;
            }
            if ($m['endings'] !== [] && ! in_array($jumped ? $lastEnding : $pass, $m['endings'], true)) {
                continue; // an ending that belongs to another pass
            }

            foreach ($m['notes'] as $n) {
                $last = array_key_last($notes);
                if ($n['tie_stop'] && $last !== null && $notes[$last]['midi_pitch'] === $n['midi_pitch']) {
                    $notes[$last]['duration_beats'] = round($notes[$last]['duration_beats'] + $n['duration'], 4);
                    continue;
                }
                $notes[] = [
                    'note_index' => count($notes),
                    'measure' => $n['measure'],
                    'midi_pitch' => $n['midi_pitch'],
                    'onset_beats' => round($measureStart + $n['onset'], 4),
                    'duration_beats' => $n['duration'],
                ];
            }
            $measureStart += $m['length'];

            if ($m['repeatEnd'] !== null && ! $jumped) {
                if ($pass < $m['repeatEnd']) {
                    $pass++;
                    $i = $start - 1; // the loop's $i++ lands on the section start

                    continue;
                }
                $pass = 1;
                $start = $i + 1;
            }

            if ($jumped) {
                if ($m['fine']) {
                    break;
                }
                if ($m['toCoda'] !== null && isset($codas[$m['toCoda']])) {
                    $i = $codas[$m['toCoda']] - 1;
                }
            } elseif ($m['jump'] !== null) {
                $target = $m['jump']['segno'] === null ? 0 : ($segnos[$m['jump']['segno']] ?? null);
                if ($target !== null) {
                    $jumped = true;
                    $i = $target - 1;
                }
            }
        }

        return $notes;
    }

    /** The score's XML text, unpacking a compressed .mxl if needed. */
    public function readXml(string $path): string
    {
        $handle = @fopen($path, 'rb');
        if ($handle === false) {
            throw new MusicXmlException('The file could not be opened.');
        }
        $magic = fread($handle, 2);
        fclose($handle);

        if ($magic !== 'PK') {
            return (string) file_get_contents($path);
        }

        // Compressed MusicXML: a zip whose META-INF/container.xml names the score file.
        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            throw new MusicXmlException('The .mxl archive could not be opened.');
        }
        try {
            $container = $zip->getFromName('META-INF/container.xml');
            $root = null;
            if ($container !== false) {
                $xml = @simplexml_load_string($container, options: LIBXML_NONET);
                $root = $xml ? (string) ($xml->rootfiles->rootfile['full-path'] ?? '') : null;
            }
            if (! $root) {
                for ($i = 0; $i < $zip->numFiles; $i++) {
                    $name = (string) $zip->getNameIndex($i);
                    if (! str_starts_with($name, 'META-INF/') && preg_match('/\.(xml|musicxml)$/i', $name)) {
                        $root = $name;
                        break;
                    }
                }
            }
            $content = $root ? $zip->getFromName($root) : false;
            if ($content === false) {
                throw new MusicXmlException('No score found inside the .mxl archive.');
            }

            return $content;
        } finally {
            $zip->close();
        }
    }

    private function element(XMLReader $reader): \SimpleXMLElement
    {
        $xml = $reader->readOuterXml();
        $element = @simplexml_load_string($xml, options: LIBXML_NONET);
        if ($element === false) {
            throw new MusicXmlException('Malformed element <'.$reader->localName.'>.');
        }
        // Jump past the subtree we just consumed; the main loop must not call read() again.
        $this->skipped = true;
        $this->skippedOk = $reader->next();

        return $element;
    }

    private bool $skipped = false;

    private bool $skippedOk = false;

    private function text(XMLReader $reader): ?string
    {
        $value = trim($reader->readString());

        return $value === '' ? null : $value;
    }

    private function duration(\SimpleXMLElement $el, float $divisions): float
    {
        return isset($el->duration) ? (float) $el->duration / $divisions : 0.0;
    }

    private function midi(\SimpleXMLElement $pitch): int
    {
        $step = strtoupper(trim((string) $pitch->step));
        if (! isset(self::STEPS[$step])) {
            throw new MusicXmlException("Unknown pitch step '{$step}'.");
        }
        $octave = (int) $pitch->octave;
        $alter = isset($pitch->alter) ? (float) $pitch->alter : 0.0;
        $midi = (int) round(($octave + 1) * 12 + self::STEPS[$step] + $alter);
        if ($midi < 0 || $midi > 127) {
            throw new MusicXmlException('Pitch out of range.');
        }

        return $midi;
    }
}
