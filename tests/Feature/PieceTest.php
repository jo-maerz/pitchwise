<?php

namespace Tests\Feature;

use App\Jobs\ParseMusicXml;
use App\Models\Organization;
use App\Models\Piece;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PieceTest extends TestCase
{
    use RefreshDatabase;

    private function scoreUpload(string $name = 'edge.musicxml'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, file_get_contents(base_path('tests/fixtures/edge-cases.musicxml')));
    }

    #[Test]
    public function uploading_a_score_stores_it_and_queues_the_parse_job(): void
    {
        Storage::fake('local');
        Queue::fake();
        $user = User::factory()->admin()->create();

        $response = $this->actingAs($user)->post(route('pieces.store'), ['location' => 'root:shared',
            'title' => 'Edge cases', 'instrument' => 'violin', 'default_bpm' => 72, 'score' => $this->scoreUpload(),
        ]);

        $piece = Piece::sole();
        $response->assertRedirect(route('pieces.show', $piece));
        $this->assertSame($user->id, $piece->owner_id);
        $this->assertSame('pending', $piece->parse_status);
        Storage::disk('local')->assertExists($piece->musicxml_path);
        Queue::assertPushed(ParseMusicXml::class, fn ($job) => $job->piece->is($piece));
    }

    #[Test]
    public function the_parse_job_saves_the_expected_notes(): void
    {
        Storage::fake('local');
        $user = User::factory()->admin()->create();

        // Sync queue in tests: the job runs during the request.
        $this->actingAs($user)->post(route('pieces.store'), ['location' => 'root:shared',
            'title' => 'Edge cases', 'instrument' => 'violin', 'default_bpm' => 72, 'score' => $this->scoreUpload(),
        ]);

        $piece = Piece::sole()->fresh();
        $this->assertSame('ready', $piece->parse_status);
        $this->assertSame(5, $piece->note_count);
        $this->assertSame(3, $piece->beats_per_measure);
        $this->assertSame([62, 67, 69, 70, 76], $piece->notes()->pluck('midi_pitch')->all());
        $this->assertSame('Test', $piece->composer, 'composer taken from the file when left empty');
    }

    #[Test]
    public function a_broken_score_is_marked_failed(): void
    {
        Storage::fake('local');
        $user = User::factory()->admin()->create();
        $file = UploadedFile::fake()->createWithContent('rests.musicxml',
            '<?xml version="1.0"?><score-partwise><part-list/><part id="P1"><measure><note><rest/><duration>1</duration></note></measure></part></score-partwise>');

        $this->actingAs($user)->post(route('pieces.store'), ['location' => 'root:shared',
            'title' => 'Only rests', 'instrument' => 'violin', 'default_bpm' => 60, 'score' => $file,
        ]);

        $this->assertSame('failed', Piece::sole()->parse_status);
    }

    #[Test]
    public function uploads_must_look_like_musicxml(): void
    {
        $user = User::factory()->admin()->create();

        $this->actingAs($user)->post(route('pieces.store'), ['location' => 'root:shared',
            'title' => 'Nope', 'instrument' => 'violin', 'default_bpm' => 60,
            'score' => UploadedFile::fake()->createWithContent('notes.xml', '<html>hi</html>'),
        ])->assertSessionHasErrors('score');

        $this->actingAs($user)->post(route('pieces.store'), ['location' => 'root:shared',
            'title' => 'Nope', 'instrument' => 'violin', 'default_bpm' => 999,
            'score' => UploadedFile::fake()->create('song.mp3', 10),
        ])->assertSessionHasErrors(['score', 'default_bpm']);

        $this->assertSame(0, Piece::count());
    }

    #[Test]
    public function organization_pieces_are_for_members_and_the_shared_library_for_everyone(): void
    {
        $school = Organization::factory()->create();
        $member = User::factory()->create(['organization_id' => $school->id]);
        $outsider = User::factory()->create();
        $private = Piece::factory()->inOrganization($school)->create(['parse_status' => 'ready', 'title' => 'School piece']);
        $shared = Piece::factory()->create(['parse_status' => 'ready', 'title' => 'Shared piece']);

        $this->actingAs($outsider)->get(route('pieces.show', $private))->assertForbidden();
        $this->actingAs($outsider)->get(route('player.show', $private))->assertForbidden();
        $this->actingAs($outsider)->get(route('pieces.index'))->assertOk()->assertSee('Shared piece')->assertDontSee('School piece');
        $this->actingAs($member)->get(route('pieces.index'))->assertSee('Shared piece')->assertSee('School piece')->assertSee($school->name);
        $this->actingAs($member)->get(route('player.show', $private))->assertOk();
        $this->actingAs($member)->delete(route('pieces.destroy', $shared))->assertForbidden();
        $this->actingAs($member)->delete(route('pieces.destroy', $private))->assertForbidden();
    }

    #[Test]
    public function plain_users_cannot_upload(): void
    {
        $user = User::factory()->create(['organization_id' => Organization::factory()->create()->id]);

        $this->actingAs($user)->get(route('pieces.create'))->assertForbidden();
        $this->actingAs($user)->post(route('pieces.store'), [
            'location' => 'root:'.$user->organization_id, 'title' => 'Nope', 'instrument' => 'violin', 'default_bpm' => 60, 'score' => $this->scoreUpload(),
        ])->assertForbidden();
        $this->actingAs($user)->get(route('pieces.index'))->assertDontSee('Upload a score');
    }

    #[Test]
    public function a_piece_that_is_not_ready_cannot_be_played(): void
    {
        $owner = User::factory()->admin()->create();
        $piece = Piece::factory()->for($owner, 'owner')->inOrganization()->create(['parse_status' => 'pending']);

        $this->actingAs($owner)->get(route('player.show', $piece))->assertForbidden();
    }

    #[Test]
    public function the_owner_can_delete_a_piece_and_its_file(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('pieces/x.musicxml', '<score-partwise/>');
        $owner = User::factory()->admin()->create();
        $piece = Piece::factory()->for($owner, 'owner')->inOrganization()->create(['musicxml_path' => 'pieces/x.musicxml']);

        $this->actingAs($owner)->delete(route('pieces.destroy', $piece))->assertRedirect(route('pieces.index'));

        $this->assertModelMissing($piece);
        Storage::disk('local')->assertMissing('pieces/x.musicxml');
    }

    #[Test]
    public function the_owner_can_edit_details_without_a_new_file(): void
    {
        Storage::fake('local');
        Queue::fake();
        $user = User::factory()->admin()->create();
        $this->actingAs($user)->post(route('pieces.store'), ['location' => 'root:shared',
            'title' => 'Old', 'instrument' => 'violin', 'default_bpm' => 72, 'score' => $this->scoreUpload(),
        ]);
        $piece = Piece::sole();
        $path = $piece->musicxml_path;

        $this->actingAs($user)->put(route('pieces.update', $piece), [
            'title' => 'New', 'composer' => 'Bach', 'instrument' => 'cello', 'default_bpm' => 60,
        ])->assertRedirect(route('pieces.show', $piece));

        $piece->refresh();
        $this->assertSame(['New', 'Bach', 'cello', 60, $path], [$piece->title, $piece->composer, $piece->instrument, $piece->default_bpm, $piece->musicxml_path]);
        Queue::assertPushed(ParseMusicXml::class, 1);
    }

    #[Test]
    public function replacing_the_score_swaps_the_file_and_reparses(): void
    {
        Storage::fake('local');
        Queue::fake();
        $user = User::factory()->admin()->create();
        $this->actingAs($user)->post(route('pieces.store'), ['location' => 'root:shared',
            'title' => 'Old', 'instrument' => 'violin', 'default_bpm' => 72, 'score' => $this->scoreUpload(),
        ]);
        $piece = Piece::sole();
        $oldPath = $piece->musicxml_path;

        $this->actingAs($user)->put(route('pieces.update', $piece), [
            'title' => 'Old', 'instrument' => 'violin', 'default_bpm' => 72, 'score' => $this->scoreUpload('again.musicxml'),
        ])->assertRedirect();

        $piece->refresh();
        $this->assertNotSame($oldPath, $piece->musicxml_path);
        Storage::disk('local')->assertMissing($oldPath);
        Storage::disk('local')->assertExists($piece->musicxml_path);
        $this->assertSame('pending', $piece->parse_status);
        Queue::assertPushed(ParseMusicXml::class, 2);
    }

    #[Test]
    public function other_users_cannot_edit_a_piece(): void
    {
        Storage::fake('local');
        Queue::fake();
        $this->actingAs(User::factory()->admin()->create())->post(route('pieces.store'), ['location' => 'root:shared',
            'title' => 'Mine', 'instrument' => 'violin', 'default_bpm' => 72, 'score' => $this->scoreUpload(),
        ]);

        $this->actingAs(User::factory()->orgAdmin()->create())->put(route('pieces.update', Piece::sole()), [
            'title' => 'Hijacked', 'instrument' => 'violin', 'default_bpm' => 72,
        ])->assertForbidden();
        $this->assertSame('Mine', Piece::sole()->title);
    }
}
