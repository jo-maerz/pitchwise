<?php

namespace Tests\Unit;

use App\Services\MusicXml\MusicXmlException;
use App\Services\MusicXml\MusicXmlParser;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ZipArchive;

class MusicXmlParserTest extends TestCase
{
    private function fixture(string $name): string
    {
        return dirname(__DIR__).'/fixtures/'.$name;
    }

    #[Test]
    public function it_reads_the_melody_line_and_skips_what_the_player_does_not_check(): void
    {
        $score = (new MusicXmlParser)->parseFile($this->fixture('edge-cases.musicxml'));

        $this->assertSame('Edge Cases', $score->title);
        $this->assertSame('Test', $score->composer);
        $this->assertSame(3, $score->beatsPerMeasure, '6/8 = three quarter-note beats');

        $this->assertSame([
            // pickup D4, half a beat
            ['note_index' => 0, 'measure' => 1, 'midi_pitch' => 62, 'onset_beats' => 0.0, 'duration_beats' => 0.5],
            // grace F#4 skipped; double stop G4+B4 → G4 only
            ['note_index' => 1, 'measure' => 2, 'midi_pitch' => 67, 'onset_beats' => 0.5, 'duration_beats' => 1.0],
            ['note_index' => 2, 'measure' => 2, 'midi_pitch' => 69, 'onset_beats' => 1.5, 'duration_beats' => 1.0],
            // voice 2 and the rest skipped; B-flat tied over the bar line counts once, 0.5 + 0.5 beats
            ['note_index' => 3, 'measure' => 2, 'midi_pitch' => 70, 'onset_beats' => 3.0, 'duration_beats' => 1.0],
            // cue note skipped (but takes its time); the second part (piano) is ignored
            ['note_index' => 4, 'measure' => 3, 'midi_pitch' => 76, 'onset_beats' => 4.5, 'duration_beats' => 2.0],
        ], $score->notes);
    }

    #[Test]
    public function it_plays_repeats_and_numbered_endings(): void
    {
        $note = fn (string $step, string $extra = '') => "<note><pitch><step>$step</step><octave>4</octave></pitch><duration>4</duration>$extra</note>";
        $measure = fn (int $n, string $body) => "<measure number=\"$n\"><attributes><divisions>1</divisions><time><beats>4</beats><beat-type>4</beat-type></time></attributes>$body</measure>";
        $xml = '<score-partwise><part-list/><part id="P1">'
            .$measure(1, '<barline location="left"><repeat direction="forward"/></barline>'.$note('C'))
            .$measure(2, '<barline location="left"><ending number="1" type="start"/></barline>'.$note('D')
                .'<barline location="right"><ending number="1" type="stop"/><repeat direction="backward"/></barline>')
            .$measure(3, '<barline location="left"><ending number="2" type="start"/></barline>'.$note('E')
                .'<barline location="right"><ending number="2" type="discontinue"/></barline>')
            .$measure(4, $note('F'))
            .'</part></score-partwise>';

        $notes = (new MusicXmlParser)->parseString($xml)->notes;

        // C D | C E | F
        $this->assertSame([60, 62, 60, 64, 65], array_column($notes, 'midi_pitch'));
        $this->assertSame([1, 2, 1, 3, 4], array_column($notes, 'measure'));
        $this->assertSame([0.0, 4.0, 8.0, 12.0, 16.0], array_column($notes, 'onset_beats'));
        $this->assertSame([0, 1, 2, 3, 4], array_column($notes, 'note_index'));
    }

    #[Test]
    public function it_follows_da_capo_and_dal_segno_jumps(): void
    {
        $note = fn (string $step) => "<note><pitch><step>$step</step><octave>4</octave></pitch><duration>4</duration></note>";
        $measure = fn (int $n, string $body) => "<measure number=\"$n\"><attributes><divisions>1</divisions></attributes>$body</measure>";
        $sound = fn (string $attrs) => "<direction><sound $attrs/></direction>";
        $wrap = fn (string $m) => "<score-partwise><part-list/><part id=\"P1\">$m</part></score-partwise>";

        // A B(Fine) C(D.C. al Fine) → A B C A B
        $daCapo = $wrap($measure(1, $note('A')).$measure(2, $note('B').$sound('fine="yes"')).$measure(3, $note('C').$sound('dacapo="yes"')));
        $this->assertSame([69, 71, 60, 69, 71], array_column((new MusicXmlParser)->parseString($daCapo)->notes, 'midi_pitch'));

        // A B(segno) C(To Coda) D(D.S. al Coda) E(coda) → A B C D B C E
        $dalSegno = $wrap($measure(1, $note('A')).$measure(2, $sound('segno="s"').$note('B')).$measure(3, $note('C').$sound('tocoda="c"'))
            .$measure(4, $note('D').$sound('dalsegno="s"')).$measure(5, $sound('coda="c"').$note('E')));
        $this->assertSame([69, 71, 60, 62, 71, 60, 64], array_column((new MusicXmlParser)->parseString($dalSegno)->notes, 'midi_pitch'));
    }

    #[Test]
    public function it_reads_compressed_mxl_files(): void
    {
        $mxl = tempnam(sys_get_temp_dir(), 'mxl');
        $zip = new ZipArchive;
        $zip->open($mxl, ZipArchive::OVERWRITE);
        $zip->addFromString('META-INF/container.xml', '<?xml version="1.0"?><container><rootfiles><rootfile full-path="score/piece.xml"/></rootfiles></container>');
        $zip->addFromString('score/piece.xml', file_get_contents($this->fixture('edge-cases.musicxml')));
        $zip->close();

        $score = (new MusicXmlParser)->parseFile($mxl);
        unlink($mxl);

        $this->assertCount(5, $score->notes);
    }

    #[Test]
    public function the_demo_catalogue_scores_parse(): void
    {
        $dir = dirname(__DIR__, 2).'/database/seeders/scores';
        $expected = ['g-major-scale-two-octaves.musicxml' => 29, 'open-strings-and-a-major-arpeggio.musicxml' => 17, 'twinkle-twinkle-little-star.musicxml' => 42];
        foreach ($expected as $file => $count) {
            $this->assertCount($count, (new MusicXmlParser)->parseFile("$dir/$file")->notes, $file);
        }
    }

    #[Test]
    public function a_transposing_part_is_read_at_sounding_pitch(): void
    {
        // Clarinet in B♭: written D5 sounds C5. With an octave change (horn in F, bass clef): written C5 sounds F3.
        $part = fn (string $transpose) => '<score-partwise><part-list/><part id="P1"><measure><attributes><divisions>1</divisions>'.$transpose.'</attributes>'
            .'<note><pitch><step>D</step><octave>5</octave></pitch><duration>1</duration></note>'
            .'<note><pitch><step>C</step><octave>5</octave></pitch><duration>1</duration></note></measure></part></score-partwise>';
        $pitches = fn (string $xml) => array_column((new MusicXmlParser)->parseString($xml)->notes, 'midi_pitch');

        $this->assertSame([72, 70], $pitches($part('<transpose><diatonic>-1</diatonic><chromatic>-2</chromatic></transpose>')));
        $this->assertSame([55, 53], $pitches($part('<transpose><diatonic>-4</diatonic><chromatic>-7</chromatic><octave-change>-1</octave-change></transpose>')));
        $this->assertSame([74, 72], $pitches($part('')));
    }

    #[Test]
    public function it_rejects_files_that_are_not_partwise_musicxml(): void
    {
        foreach ([
            '<html><body>hi</body></html>' => 'not a MusicXML score',
            '<score-timewise/>' => 'Timewise',
            '<score-partwise><part-list/><part id="P1"><measure><note><rest/><duration>1</duration></note></measure></part></score-partwise>' => 'No pitched notes',
            'not xml at all' => 'not a MusicXML score',
        ] as $xml => $message) {
            try {
                (new MusicXmlParser)->parseString($xml);
                $this->fail("Expected an exception for: $xml");
            } catch (MusicXmlException $e) {
                $this->assertStringContainsString($message, $e->getMessage());
            }
        }
    }

    #[Test]
    public function it_does_not_expand_external_entities(): void
    {
        $xml = '<?xml version="1.0"?><!DOCTYPE score-partwise [<!ENTITY x SYSTEM "file:///etc/passwd">]>'
            .'<score-partwise><work><work-title>&x;</work-title></work><part-list/><part id="P1"><measure>'
            .'<note><pitch><step>A</step><octave>4</octave></pitch><duration>1</duration></note></measure></part></score-partwise>';

        $score = (new MusicXmlParser)->parseString($xml);

        $this->assertStringNotContainsString('root:', (string) $score->title);
    }
}
