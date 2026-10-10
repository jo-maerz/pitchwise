<?php

namespace Tests\Unit;

use App\Support\PitchRule;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/** The same cases run in tests/js/pitch-math.test.mjs, so server and browser cannot drift apart. */
class PitchRuleTest extends TestCase
{
    #[Test]
    public function every_shared_case_gets_the_expected_outcome(): void
    {
        $cases = json_decode(file_get_contents(dirname(__DIR__).'/fixtures/pitch-rule-cases.json'), true);
        $this->assertNotEmpty($cases);

        foreach ($cases as $c) {
            $got = PitchRule::classify(
                $c['expected_midi'],
                $c['detected_hz'] === null ? null : (float) $c['detected_hz'],
                $c['clarity'] === null ? null : (float) $c['clarity'],
                $c['mode'],
                (float) $c['tolerance'],
                (float) $c['reference_hz'],
            );
            $this->assertSame($c['outcome'], $got['outcome'], $c['name']);
            $this->assertSame($c['cents'] === null ? null : (float) $c['cents'], $got['cents'], $c['name'].' (cents)');
            $this->assertSame($c['detected_midi'], $got['detected_midi'], $c['name'].' (detected midi)');
        }
    }

    #[Test]
    public function the_hz_rule_accepts_a_whole_tone_on_the_g_string_but_cents_does_not(): void
    {
        // The reason cents is the default: 30 Hz is two semitones at G3 but under 40 cents at E6.
        $this->assertSame('in_tune', PitchRule::classify(55, 220.0, 1.0, 'hz', 30)['outcome']);
        $this->assertSame('wrong_note', PitchRule::classify(55, 220.0, 1.0, 'cents', 30)['outcome']);
    }
}
