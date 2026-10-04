<?php

namespace Tests\Feature;

use App\Models\NoteResult;
use App\Models\Piece;
use App\Models\PracticeSession;
use App\Models\User;
use Database\Seeders\CatalogueSeeder;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PlayerAndReportTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function the_player_page_hands_the_browser_a_scoped_short_lived_token(): void
    {
        Storage::fake('local');
        $this->seed(CatalogueSeeder::class);
        $user = User::factory()->create();
        $piece = Piece::where('title', 'Twinkle, Twinkle, Little Star')->sole();

        $response = $this->actingAs($user)->get(route('player.show', $piece))->assertOk()
            ->assertSee('id="player-config"', false)
            ->assertSee('id="gauge"', false);

        preg_match('#<script type="application/json" id="player-config">(.*?)</script>#s', $response->getContent(), $m);
        $config = json_decode(html_entity_decode($m[1]), true);
        $this->assertSame($piece->id, $config['pieceId']);
        $this->assertSame(['mode' => 'cents', 'value' => 30.0], ['mode' => $config['defaults']['toleranceMode'], 'value' => (float) $config['defaults']['toleranceValue']]);
        $this->assertMatchesRegularExpression('/^\d+\|\w+$/', $config['token']);

        $token = $user->tokens()->sole();
        $this->assertSame(['practice:write'], $token->abilities);
        $this->assertTrue($token->expires_at->isFuture());
        $this->assertTrue($token->expires_at->lte(now()->addMinutes(181)));
    }

    #[Test]
    public function the_score_file_is_served_to_people_who_may_see_it(): void
    {
        Storage::fake('local');
        $this->seed(CatalogueSeeder::class);
        $piece = Piece::first();

        $this->actingAs(User::factory()->create())->get(route('pieces.file', $piece))
            ->assertOk()->assertHeader('Content-Type', 'application/vnd.recordare.musicxml');
        auth()->logout();
        $this->get(route('pieces.file', $piece))->assertRedirect(route('login'));
    }

    #[Test]
    public function the_session_report_lists_counts_and_weakest_bars_for_its_owner_only(): void
    {
        Storage::fake('local');
        $this->seed(CatalogueSeeder::class);
        $user = User::factory()->create();
        $piece = Piece::where('title', 'Open Strings and A Major Arpeggio')->sole();
        $session = PracticeSession::factory()->for($user)->for($piece)->create(['finished_at' => now(), 'score_pct' => 50]);
        $notes = $piece->notes()->take(4)->get();
        foreach ($notes as $i => $n) {
            NoteResult::create([
                'session_id' => $session->id, 'note_index' => $n->note_index, 'expected_midi' => $n->midi_pitch,
                'detected_midi' => $n->midi_pitch, 'detected_hz' => 440, 'cents_offset' => [3, 45, -2, -60][$i],
                'verdict' => ['in_tune', 'sharp', 'in_tune', 'flat'][$i], 'clarity' => 0.95,
            ]);
        }

        $this->actingAs($user)->get(route('sessions.show', $session))->assertOk()
            ->assertSeeInOrder(['50%', '2 of 4 notes'])
            ->assertSee('Bar 1: 1 of 2 in tune', false)
            ->assertSee('+45 cents')
            ->assertSee('±30 cents');

        $this->actingAs(User::factory()->create())->get(route('sessions.show', $session))->assertForbidden();
    }

    #[Test]
    public function the_report_and_the_piece_page_carry_per_piece_intonation_by_note(): void
    {
        Storage::fake('local');
        $this->seed(CatalogueSeeder::class);
        $user = User::factory()->create();
        $piece = Piece::where('title', 'Open Strings and A Major Arpeggio')->sole();
        $note = $piece->notes()->first();
        $make = function (string $verdict, float $cents, bool $finished = true) use ($user, $piece, $note) {
            $s = PracticeSession::factory()->for($user)->for($piece)->create(['finished_at' => $finished ? now() : null, 'score_pct' => 50]);
            NoteResult::create(['session_id' => $s->id, 'note_index' => $note->note_index, 'expected_midi' => $note->midi_pitch,
                'detected_midi' => $note->midi_pitch, 'detected_hz' => 200, 'cents_offset' => $cents, 'verdict' => $verdict, 'clarity' => 0.95]);

            return $s;
        };
        $first = $make('sharp', 40);
        $make('in_tune', 10);
        $make('flat', -50, finished: false); // unfinished runs do not count

        $this->actingAs($user)->get(route('pieces.show', $piece))->assertOk()
            ->assertSee('id="chart-piece-pitches"', false)
            ->assertSee('"avgCents":25', false)   // (40 + 10) / 2 over the two finished runs
            ->assertSee('"attempts":2', false);

        $this->actingAs($user)->get(route('sessions.show', $first))->assertOk()
            ->assertSee('id="chart-run-pitches"', false)
            ->assertSee('id="report-data"', false)
            ->assertSee('"avgCents":40', false)    // this run alone
            ->assertSee('"piecePitches"', false);
    }

    #[Test]
    public function aggregation_rolls_finished_runs_into_pitch_stats_once(): void
    {
        Storage::fake('local');
        $this->seed(CatalogueSeeder::class);
        $user = User::factory()->create();
        $piece = Piece::first();
        $session = PracticeSession::factory()->for($user)->for($piece)->create(['finished_at' => now()]);
        $unfinished = PracticeSession::factory()->for($user)->for($piece)->create();
        $note = $piece->notes()->first();
        foreach ([[$session, 'sharp', 40], [$unfinished, 'flat', -50]] as [$s, $verdict, $cents]) {
            NoteResult::create(['session_id' => $s->id, 'note_index' => $note->note_index, 'expected_midi' => $note->midi_pitch,
                'detected_midi' => $note->midi_pitch, 'detected_hz' => 200, 'cents_offset' => $cents, 'verdict' => $verdict, 'clarity' => 0.95]);
        }
        NoteResult::create(['session_id' => $session->id, 'note_index' => $note->note_index + 1, 'expected_midi' => $note->midi_pitch,
            'detected_midi' => null, 'detected_hz' => null, 'cents_offset' => null, 'verdict' => 'missed', 'clarity' => null]);

        $this->artisan('practice:aggregate-stats')->expectsOutput('Aggregated 1 finished session(s).')->assertSuccessful();
        $this->artisan('practice:aggregate-stats')->expectsOutput('Aggregated 0 finished session(s).');

        $stat = $user->pitchStats()->sole();
        $this->assertSame([$note->midi_pitch, 2, 0, 40.0], [$stat->midi_pitch, $stat->attempts, $stat->in_tune, $stat->avg_cents]);
        $this->assertNotNull($session->fresh()->aggregated_at);
        $this->assertNull($unfinished->fresh()->aggregated_at);
    }

    #[Test]
    public function the_seeded_demo_dashboard_renders_with_charts_and_trouble_bars(): void
    {
        Storage::fake('local');
        $this->seed(DatabaseSeeder::class);
        $user = User::where('email', 'demo@example.com')->sole();

        $this->actingAs($user)->get(route('dashboard'))->assertOk()
            ->assertSee('id="chart-history"', false)
            ->assertSee('id="chart-pitches"', false)
            ->assertSee('Trouble bars')
            ->assertSee('Your C♯4 is on average', false)
            ->assertSee('(sharp)', false);

        $this->actingAs(User::factory()->create())->get(route('dashboard'))->assertOk()->assertSee('No runs yet');
    }

    #[Test]
    public function the_tuner_page_renders(): void
    {
        $this->actingAs(User::factory()->create())->get(route('tuner'))->assertOk()->assertSee('id="btn-listen"', false);
    }
}
