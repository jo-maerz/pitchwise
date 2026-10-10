<?php

namespace Tests\Feature\Api;

use App\Models\Piece;
use App\Models\User;
use App\Services\PlayerTokenService;
use Database\Seeders\CatalogueSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** The player's API, called with the same short-lived Sanctum token the player page hands to the browser. */
class PracticeApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private string $token;

    private Piece $piece;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->seed(CatalogueSeeder::class);
        $this->user = User::factory()->create();
        $this->token = app(PlayerTokenService::class)->issue($this->user);
        $this->piece = Piece::where('title', 'Open Strings and A Major Arpeggio')->sole(); // G3 D4 A4 E5 …
    }

    private function api(string $method, string $path, array $body = [], ?string $token = null): TestResponse
    {
        // Guards remember the user between requests in one test; each call must authenticate on its own.
        $this->app['auth']->forgetGuards();

        return $this->withToken($token ?? $this->token)->json($method, '/api/v1'.$path, $body);
    }

    private function startRun(array $overrides = []): int
    {
        return $this->api('POST', '/sessions', $overrides + ['piece_id' => $this->piece->id, 'bpm' => 60])
            ->assertCreated()
            ->json('id');
    }

    private function heard(int $index, ?float $hz, ?float $clarity = 0.97): array
    {
        $midi = $this->piece->notes()->where('note_index', $index)->value('midi_pitch');

        return ['note_index' => $index, 'expected_midi' => $midi, 'detected_hz' => $hz, 'clarity' => $clarity];
    }

    #[Test]
    public function it_serves_the_expected_notes_of_a_piece(): void
    {
        $this->api('GET', '/pieces/'.$this->piece->id)
            ->assertOk()
            ->assertHeader('Cache-Control', 'max-age=60, private')
            ->assertJsonPath('note_count', 17)
            ->assertJsonPath('notes.0', ['note_index' => 0, 'measure' => 1, 'midi_pitch' => 55, 'onset_beats' => 0, 'duration_beats' => 2]);
    }

    #[Test]
    public function it_rejects_missing_wrong_expired_and_under_scoped_tokens(): void
    {
        $this->api('GET', '/pieces/'.$this->piece->id, token: '')->assertUnauthorized();
        [$id] = explode('|', $this->token);
        $this->api('GET', '/pieces/'.$this->piece->id, token: $id.'|wrong-secret')->assertUnauthorized();

        $expired = $this->user->createToken('player', ['practice:write'], now()->subMinute())->plainTextToken;
        $this->api('GET', '/pieces/'.$this->piece->id, token: $expired)
            ->assertUnauthorized()
            ->assertJsonPath('message', 'The player token is missing or has expired. Reload the page.');

        $readOnly = $this->user->createToken('other', ['profile:read'])->plainTextToken;
        $this->api('GET', '/pieces/'.$this->piece->id, token: $readOnly)->assertForbidden();
    }

    #[Test]
    public function a_user_cannot_see_or_play_another_organizations_piece(): void
    {
        $private = Piece::factory()->inOrganization()->create(['parse_status' => 'ready']);

        $this->api('GET', '/pieces/'.$private->id)->assertForbidden();
        $this->api('POST', '/sessions', ['piece_id' => $private->id, 'bpm' => 60])->assertForbidden();
        $this->assertSame(0, DB::table('practice_sessions')->count());
    }

    #[Test]
    public function a_piece_that_is_not_analysed_yet_cannot_be_played(): void
    {
        $pending = Piece::factory()->for($this->user, 'owner')->create(['parse_status' => 'pending']);

        $this->api('GET', '/pieces/'.$pending->id)->assertForbidden();
        $this->api('GET', '/pieces/999999')->assertNotFound();
    }

    #[Test]
    public function a_full_run_is_judged_on_the_server_with_the_runs_own_rule(): void
    {
        $id = $this->startRun(['tolerance_mode' => 'cents', 'tolerance_value' => 30, 'reference_hz' => 440]);

        // page 1: G3 in tune, D4 40 cents sharp
        $r = $this->api('POST', "/sessions/$id/results", ['results' => [
            $this->heard(0, 196.0),
            $this->heard(1, 293.66 * 2 ** (40 / 1200)),
        ]])->assertCreated();
        $this->assertSame(['in_tune', 'sharp'], array_column($r->json('results'), 'outcome'));
        $this->assertFalse($r->json('finished'));

        // last batch: A4 is played as B-flat, E5 is silent, then finish
        $r = $this->api('POST', "/sessions/$id/results", ['finished' => true, 'results' => [
            $this->heard(2, 466.16),
            $this->heard(3, null, null),
        ]])->assertCreated();
        $this->assertSame(['wrong_note', 'missed'], array_column($r->json('results'), 'outcome'));
        $this->assertEquals(25.0, $r->json('score_pct'));
        $this->assertSame(['in_tune' => 1, 'sharp' => 1, 'flat' => 0, 'wrong_note' => 1, 'missed' => 1], $r->json('counts'));

        $session = DB::table('practice_sessions')->find($id);
        $this->assertNotNull($session->finished_at);
        $this->assertEquals(25, $session->score_pct);
        $this->assertSame(4, DB::table('note_results')->where('session_id', $id)->count());
        $stored = DB::table('note_results')->where('session_id', $id)->where('note_index', 1)->first();
        $this->assertSame(62, (int) $stored->detected_midi);
        $this->assertEqualsWithDelta(40.0, (float) $stored->cents_offset, 0.05);
    }

    #[Test]
    public function the_hz_rule_is_applied_when_the_run_chose_it(): void
    {
        $id = $this->startRun(['tolerance_mode' => 'hz', 'tolerance_value' => 30]);

        // G3 (196 Hz) played as A3 (220 Hz): 24 Hz off, so "in tune" under ±30 Hz — documented trade-off.
        $this->api('POST', "/sessions/$id/results", ['results' => [$this->heard(0, 220.0)], 'finished' => true])
            ->assertJsonPath('results.0.outcome', 'in_tune')
            ->assertJsonPath('results.0.cents', 200);
    }

    #[Test]
    public function the_client_cannot_claim_a_different_expected_pitch_or_outcome(): void
    {
        $id = $this->startRun();
        $fake = ['note_index' => 0, 'expected_midi' => 69, 'detected_hz' => 440.0, 'clarity' => 0.99, 'outcome' => 'in_tune'];

        $this->api('POST', "/sessions/$id/results", ['results' => [$fake]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('results.0.expected_midi');
        $this->assertSame(0, DB::table('note_results')->count());
    }

    #[Test]
    public function a_bad_row_rejects_the_whole_batch(): void
    {
        $id = $this->startRun();

        $r = $this->api('POST', "/sessions/$id/results", ['results' => [
            $this->heard(0, 196.0),
            $this->heard(1, 99999.0),
            ['note_index' => 999, 'expected_midi' => 60, 'detected_hz' => 261.6, 'clarity' => 0.9],
            $this->heard(0, 196.0),
        ]]);

        $r->assertUnprocessable();
        $this->assertEqualsCanonicalizing(['results.1.detected_hz', 'results.2.note_index', 'results.3.note_index'], array_keys($r->json('errors')));
        $this->assertSame(0, DB::table('note_results')->count());
    }

    #[Test]
    public function an_empty_batch_is_only_accepted_to_finish_a_run(): void
    {
        $id = $this->startRun();

        $this->api('POST', "/sessions/$id/results", ['results' => []])->assertJsonValidationErrors('results');
        $this->api('POST', "/sessions/$id/results", ['results' => [], 'finished' => true])->assertCreated();
    }

    #[Test]
    public function duplicates_and_finished_runs_are_refused(): void
    {
        $id = $this->startRun();
        $this->api('POST', "/sessions/$id/results", ['results' => [$this->heard(0, 196.0)]]);

        $this->api('POST', "/sessions/$id/results", ['results' => [$this->heard(0, 196.0), $this->heard(1, 293.7)]])->assertConflict();
        $this->assertSame(1, DB::table('note_results')->where('session_id', $id)->count());

        $this->api('POST', "/sessions/$id/results", ['results' => [], 'finished' => true]);
        $this->api('POST', "/sessions/$id/results", ['results' => [$this->heard(1, 293.7)]])
            ->assertConflict()
            ->assertJsonPath('message', 'This run is already finished.');
    }

    #[Test]
    public function another_users_session_is_off_limits(): void
    {
        $id = $this->startRun();
        $intruder = app(PlayerTokenService::class)->issue(User::factory()->create());

        $this->api('POST', "/sessions/$id/results", ['results' => [$this->heard(0, 196.0)]], $intruder)->assertForbidden();
        $this->assertSame(0, DB::table('note_results')->count());
    }

    #[Test]
    public function session_settings_are_validated(): void
    {
        $this->api('POST', '/sessions', ['piece_id' => $this->piece->id, 'bpm' => 5, 'tolerance_mode' => 'semitones', 'tolerance_value' => 500, 'reference_hz' => 300])
            ->assertUnprocessable()
            ->assertOnlyJsonValidationErrors(['bpm', 'tolerance_mode', 'tolerance_value', 'reference_hz']);
    }

    #[Test]
    public function numbers_sent_as_strings_are_refused(): void
    {
        $this->api('POST', '/sessions', ['piece_id' => (string) $this->piece->id, 'bpm' => '60'])
            ->assertOnlyJsonValidationErrors(['piece_id', 'bpm']);
    }
}
