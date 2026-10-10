<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TunerTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function the_tuner_offers_wind_instruments_with_their_transposition(): void
    {
        $response = $this->actingAs(User::factory()->create())->get(route('tuner'))->assertOk()
            ->assertSee('<optgroup label="Woodwinds">', false)->assertSee('<optgroup label="Brass">', false)
            ->assertSee('Clarinet in B♭');

        preg_match('#<script type="application/json" id="tuner-config">(.*?)</script>#s', $response->getContent(), $m);
        $instruments = collect(json_decode(html_entity_decode($m[1]), true)['instruments'])->keyBy('key');
        $this->assertSame(-2, $instruments['clarinet']['transpose']);
        $this->assertSame([['F', 65], ['B♭', 58]], $instruments['horn']['tuning']);
        $this->assertFalse($instruments->has('other'));
    }
}
