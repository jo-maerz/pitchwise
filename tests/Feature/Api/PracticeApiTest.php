<?php

namespace Tests\Feature\Api;

use App\Models\Piece;
use App\Models\User;
use App\Services\PlayerTokenService;
use Database\Seeders\CatalogueSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use PracticeApi\App;
use PracticeApi\Http\Request;
use PracticeApi\Http\Response;
use Tests\TestCase;

/**
 * Drives the plain-PHP API in-process, on the same database connection Laravel uses,
 * with tokens issued by Laravel Sanctum — exactly the two-systems-one-database setup.
 */
class PracticeApiTest extends TestCase
{
    use RefreshDatabase;

    private App $app_;

    private User $user;

    private string $token;

    private Piece $piece;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->seed(CatalogueSeeder::class);
        $this->app_ = new App(DB::connection()->getPdo(), ['http://localhost:8000'], debug: true);
        $this->user = User::factory()->create();
        $this->token = app(PlayerTokenService::class)->issue($this->user);
        $this->piece = Piece::where('title', 'Open Strings and A Major Arpeggio')->sole(); // G3 D4 A4 E5 …
    }

    private function api(string $method, string $path, ?array $body = null, ?string $token = null, array $headers = []): Response
    {
        $headers = array_change_key_case($headers) + [
            'authorization' => 'Bearer '.($token ?? $this->token),
            'content-type' => 'application/json',
        ];

        return $this->app_->handle(new Request($method, '/api/v1'.$path, $headers, $body === null ? '' : json_encode($body)));
    }

    private function startRun(array $overrides = []): int
    {
        $r = $this->api('POST', '/sessions', $overrides + ['piece_id' => $this->piece->id, 'bpm' => 60]);
        $this->assertSame(201, $r->status, $r->body());

        return $r->data['id'];
    }

    private function heard(int $index, ?float $hz, ?float $clarity = 0.97): array
    {
        $midi = $this->piece->notes()->where('note_index', $index)->value('midi_pitch');

        return ['note_index' => $index, 'expected_midi' => $midi, 'detected_hz' => $hz, 'clarity' => $clarity];
    }

    #[Test]
    public function it_serves_the_expected_notes_of_a_piece(): void
    {
        $r = $this->api('GET', '/pieces/'.$this->piece->id);

        $this->assertSame(200, $r->status);
        $this->assertSame(17, $r->data['note_count']);
        $this->assertSame(['note_index' => 0, 'measure' => 1, 'midi_pitch' => 55, 'onset_beats' => 0.0, 'duration_beats' => 2.0], $r->data['notes'][0]);
    }

    #[Test]
    public function it_rejects_missing_wrong_expired_and_under_scoped_tokens(): void
    {
        $this->assertSame(401, $this->api('GET', '/pieces/1', token: '')->status);
        [$id] = explode('|', $this->token);
        $this->assertSame(401, $this->api('GET', '/pieces/1', token: $id.'|wrong-secret')->status);

        $expired = $this->user->createToken('player', ['practice:write'], now()->subMinute())->plainTextToken;
        $this->assertSame('token_expired', $this->api('GET', '/pieces/1', token: $expired)->data['error']['code']);

        $readOnly = $this->user->createToken('other', ['profile:read'])->plainTextToken;
        $this->assertSame(403, $this->api('GET', '/pieces/1', token: $readOnly)->status);
    }

    #[Test]
    public function a_user_cannot_see_or_play_someone_elses_upload(): void
    {
        $private = Piece::factory()->for(User::factory(), 'owner')->create(['parse_status' => 'ready']);

        $this->assertSame(404, $this->api('GET', '/pieces/'.$private->id)->status);
        $this->assertSame(404, $this->api('POST', '/sessions', ['piece_id' => $private->id, 'bpm' => 60])->status);
    }

    #[Test]
    public function a_full_run_is_judged_on_the_server_with_the_runs_own_rule(): void
    {
        $id = $this->startRun(['tolerance_mode' => 'cents', 'tolerance_value' => 30, 'reference_hz' => 440]);

        // page 1: G3 in tune, D4 40 cents sharp
        $r = $this->api('POST', "/sessions/$id/results", ['results' => [
            $this->heard(0, 196.0),
            $this->heard(1, 293.66 * 2 ** (40 / 1200)),
        ]]);
        $this->assertSame(201, $r->status, $r->body());
        $this->assertSame(['in_tune', 'sharp'], array_column($r->data['results'], 'outcome'));
        $this->assertFalse($r->data['finished']);

        // last batch: A4 is played as B-flat, E5 is silent, then finish
        $r = $this->api('POST', "/sessions/$id/results", ['finished' => true, 'results' => [
            $this->heard(2, 466.16),
            $this->heard(3, null, null),
        ]]);
        $this->assertSame(['wrong_note', 'missed'], array_column($r->data['results'], 'outcome'));
        $this->assertSame(25.0, $r->data['score_pct']);
        $this->assertSame(['in_tune' => 1, 'sharp' => 1, 'flat' => 0, 'wrong_note' => 1, 'missed' => 1], $r->data['counts']);

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
        $r = $this->api('POST', "/sessions/$id/results", ['results' => [$this->heard(0, 220.0)], 'finished' => true]);

        $this->assertSame('in_tune', $r->data['results'][0]['outcome']);
        $this->assertSame(200.0, $r->data['results'][0]['cents']);
    }

    #[Test]
    public function the_client_cannot_claim_a_different_expected_pitch_or_outcome(): void
    {
        $id = $this->startRun();
        $fake = ['note_index' => 0, 'expected_midi' => 69, 'detected_hz' => 440.0, 'clarity' => 0.99, 'outcome' => 'in_tune'];

        $r = $this->api('POST', "/sessions/$id/results", ['results' => [$fake]]);

        $this->assertSame(422, $r->status);
        $this->assertArrayHasKey('results.0.expected_midi', $r->data['error']['details']);
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

        $this->assertSame(422, $r->status);
        $this->assertSame(['results.1.detected_hz', 'results.2.note_index', 'results.3.note_index'], array_keys($r->data['error']['details']));
        $this->assertSame(0, DB::table('note_results')->count());
    }

    #[Test]
    public function duplicates_and_finished_runs_are_refused(): void
    {
        $id = $this->startRun();
        $this->api('POST', "/sessions/$id/results", ['results' => [$this->heard(0, 196.0)]]);

        $this->assertSame('duplicate', $this->api('POST', "/sessions/$id/results", ['results' => [$this->heard(0, 196.0)]])->data['error']['code']);

        $this->api('POST', "/sessions/$id/results", ['results' => [], 'finished' => true]);
        $this->assertSame('already_finished', $this->api('POST', "/sessions/$id/results", ['results' => [$this->heard(1, 293.7)]])->data['error']['code']);
    }

    #[Test]
    public function another_users_session_is_not_found(): void
    {
        $id = $this->startRun();
        $intruder = app(PlayerTokenService::class)->issue(User::factory()->create());

        $r = $this->api('POST', "/sessions/$id/results", ['results' => [$this->heard(0, 196.0)]], $intruder);

        $this->assertSame(404, $r->status);
    }

    #[Test]
    public function session_settings_are_validated(): void
    {
        $r = $this->api('POST', '/sessions', ['piece_id' => $this->piece->id, 'bpm' => 5, 'tolerance_mode' => 'semitones', 'tolerance_value' => 500, 'reference_hz' => 300]);

        $this->assertSame(422, $r->status);
        $this->assertEqualsCanonicalizing(['bpm', 'tolerance_mode', 'tolerance_value', 'reference_hz'], array_keys($r->data['error']['details']));
    }

    #[Test]
    public function http_details_bad_json_wrong_method_unknown_route_and_cors(): void
    {
        $bad = $this->app_->handle(new Request('POST', '/api/v1/sessions', ['authorization' => 'Bearer '.$this->token, 'content-type' => 'application/json'], '{nope'));
        $this->assertSame(400, $bad->status);
        $this->assertSame(405, $this->api('GET', '/sessions')->status);
        $this->assertSame(415, $this->api('POST', '/sessions', ['bpm' => 60], headers: ['content-type' => 'text/plain'])->status);
        $this->assertSame(404, $this->api('GET', '/nothing')->status);

        $preflight = $this->app_->handle(new Request('OPTIONS', '/api/v1/sessions', ['origin' => 'http://localhost:8000']));
        $this->assertSame(204, $preflight->status);
        $this->assertSame('http://localhost:8000', $preflight->headers['Access-Control-Allow-Origin']);

        $foreign = $this->app_->handle(new Request('OPTIONS', '/api/v1/sessions', ['origin' => 'https://evil.example']));
        $this->assertArrayNotHasKey('Access-Control-Allow-Origin', $foreign->headers);
    }
}
