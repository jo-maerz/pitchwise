<?php

namespace Tests\Feature;

use App\Jobs\ConvertPdfScore;
use App\Models\Piece;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PdfScoreTest extends TestCase
{
    use RefreshDatabase;

    private string $spool;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->spool = sys_get_temp_dir().'/omr-spool-'.uniqid();
        config(['practice.omr.spool' => $this->spool]);
        $this->app->forgetInstance(\App\Services\Omr\OmrSpool::class);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->spool);
        parent::tearDown();
    }

    private function pdf(string $name = 'part.pdf', string $content = "%PDF-1.7\n%fake\n"): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, $content);
    }

    private function uploadPdf(User $user): Piece
    {
        Queue::fake();
        $this->actingAs($user)->post(route('pieces.store'), [
            'title' => 'From PDF', 'instrument' => 'violin', 'default_bpm' => 70, 'score' => $this->pdf(),
        ]);

        return Piece::sole();
    }

    private function runJob(Piece $piece): ConvertPdfScore
    {
        $job = (new ConvertPdfScore($piece))->withFakeQueueInteractions();
        $job->handle(app(\App\Services\Omr\OmrSpool::class), app(\App\Services\PieceService::class), app(\App\Repositories\PieceRepository::class));

        return $job;
    }

    #[Test]
    public function a_pdf_is_stored_and_queued_for_recognition(): void
    {
        $piece = $this->uploadPdf(User::factory()->create());

        $this->assertNull($piece->musicxml_path);
        Storage::disk('local')->assertExists($piece->source_pdf_path);
        $this->assertSame('pending', $piece->parse_status);
        Queue::assertPushed(ConvertPdfScore::class, fn ($job) => $job->piece->is($piece));
    }

    #[Test]
    public function a_file_that_is_not_a_pdf_is_rejected(): void
    {
        $this->actingAs(User::factory()->create())->post(route('pieces.store'), [
            'title' => 'Nope', 'instrument' => 'violin', 'default_bpm' => 70, 'score' => $this->pdf('nope.pdf', 'hello'),
        ])->assertSessionHasErrors('score');

        $this->assertSame(0, Piece::count());
    }

    #[Test]
    public function the_job_hands_the_pdf_to_the_spool_and_waits(): void
    {
        $piece = $this->uploadPdf(User::factory()->create());

        $job = $this->runJob($piece);

        $this->assertFileExists($this->spool.'/in/'.$piece->id.'.pdf');
        $this->assertSame('converting', $piece->fresh()->parse_status);
        $job->assertReleased(5);
    }

    #[Test]
    public function a_recognised_score_waits_for_the_owners_confirmation(): void
    {
        $owner = User::factory()->create();
        $piece = $this->uploadPdf($owner);
        $this->runJob($piece); // submits
        copy(base_path('tests/fixtures/edge-cases.musicxml'), $this->spool.'/out/'.$piece->id.'.mxl');

        $job = $this->runJob($piece);

        $piece->refresh();
        $job->assertNotReleased();
        $this->assertSame('needs_review', $piece->parse_status);
        $this->assertSame(5, $piece->note_count);
        $this->assertNotNull($piece->musicxml_path);
        $this->assertFileDoesNotExist($this->spool.'/out/'.$piece->id.'.mxl', 'spool cleaned up');
        $this->assertFalse($owner->can('play', $piece), 'not playable before confirmation');

        $this->actingAs($owner)->post(route('pieces.confirm', $piece))->assertRedirect(route('pieces.show', $piece));

        $this->assertSame('ready', $piece->fresh()->parse_status);
        $this->assertTrue($owner->can('play', $piece->fresh()));
    }

    #[Test]
    public function only_the_owner_can_confirm_and_only_while_review_is_pending(): void
    {
        $owner = User::factory()->create();
        $piece = Piece::factory()->for($owner, 'owner')->create(['parse_status' => 'needs_review']);

        $this->actingAs(User::factory()->create())->post(route('pieces.confirm', $piece))->assertForbidden();

        $piece->update(['parse_status' => 'ready']);
        $this->actingAs($owner)->post(route('pieces.confirm', $piece))->assertStatus(409);
    }

    #[Test]
    public function a_failed_recognition_marks_the_piece_failed(): void
    {
        $piece = $this->uploadPdf(User::factory()->create());
        $this->runJob($piece);
        file_put_contents($this->spool.'/out/'.$piece->id.'.failed', 'Audiveris found no music in this PDF.');

        $this->runJob($piece);

        $this->assertSame('failed', $piece->fresh()->parse_status);
    }

    #[Test]
    public function the_review_page_shows_warnings_and_the_confirm_button(): void
    {
        $owner = User::factory()->create();
        $piece = Piece::factory()->for($owner, 'owner')->create([
            'parse_status' => 'needs_review', 'musicxml_path' => 'pieces/x.mxl', 'review_notes' => 'Bars with more beats than allowed: 3.',
        ]);

        $this->actingAs($owner)->get(route('pieces.show', $piece))
            ->assertOk()
            ->assertSee('Check the recognised score')
            ->assertSee('Bars with more beats than allowed: 3.')
            ->assertSee(route('pieces.confirm', $piece));
    }

    #[Test]
    public function short_and_long_bars_are_flagged_with_their_numbers(): void
    {
        $bar = fn (int $n, string $notes) => '<measure number="'.$n.'"'.($n === 1 ? '' : '').'>'
            .($n === 1 ? '<attributes><divisions>1</divisions><time><beats>4</beats><beat-type>4</beat-type></time></attributes>' : '')
            .$notes.'</measure>';
        $note = fn (int $d) => '<note><pitch><step>C</step><octave>4</octave></pitch><duration>'.$d.'</duration><voice>1</voice></note>';
        $rest = fn (int $d) => '<note><rest/><duration>'.$d.'</duration><voice>1</voice></note>';
        $xml = '<score-partwise><part-list/><part id="P1">'
            .$bar(1, $note(2).$note(1))                  // short first bar: a pickup, allowed
            .$bar(2, $note(2).$rest(2))                  // 4 beats, rest counted: fine
            .$bar(3, $note(2).$note(1))                  // 3 of 4: a note went missing
            .$bar(4, $note(2).$note(2).$note(1))         // 5 of 4: too long
            .$bar(5, $note(2).$note(1))                  // short last bar: allowed
            .'</part></score-partwise>';

        $messages = (new \App\Services\Omr\ScoreSanity)->check($xml, 9);

        $this->assertCount(1, $messages);
        $this->assertStringContainsString('3 (3 of 4 beats), 4 (5 of 4 beats)', $messages[0]);
        $this->assertStringNotContainsString('2 (', $messages[0]);
        $this->assertSame([], (new \App\Services\Omr\ScoreSanity)->check('<score-partwise><part-list/><part id="P1">'.$bar(1, $note(4)).$bar(2, $note(4)).'</part></score-partwise>', 2));
        $this->assertNotSame([], (new \App\Services\Omr\ScoreSanity)->check('<score-partwise/>', 0));
    }

    #[Test]
    public function warnings_from_the_recogniser_reach_the_review_notes(): void
    {
        $piece = $this->uploadPdf(User::factory()->create());
        $this->runJob($piece); // submits
        copy(base_path('tests/fixtures/edge-cases.musicxml'), $this->spool.'/out/'.$piece->id.'.mxl');
        file_put_contents($this->spool.'/out/'.$piece->id.'.warn', "Page 2 could not be read and was skipped.\n");

        $this->runJob($piece);

        $this->assertStringContainsString('Page 2 could not be read', $piece->fresh()->review_notes);
        $this->assertFileDoesNotExist($this->spool.'/out/'.$piece->id.'.warn');
    }

    // --- both files, and the PDF-only mode --------------------------------

    private function xml(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('edge.musicxml', file_get_contents(base_path('tests/fixtures/edge-cases.musicxml')));
    }

    #[Test]
    public function musicxml_and_pdf_together_give_a_normal_piece_that_also_has_the_pdf(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('pieces.store'), [
            'title' => 'Both', 'instrument' => 'violin', 'default_bpm' => 70, 'score' => $this->xml(), 'pdf' => $this->pdf(),
        ])->assertSessionHasNoErrors();

        $piece = Piece::sole()->fresh();
        $this->assertSame('ready', $piece->parse_status, 'MusicXML is trusted: no review, no recognition');
        $this->assertSame(5, $piece->note_count);
        Storage::disk('local')->assertExists($piece->musicxml_path);
        Storage::disk('local')->assertExists($piece->source_pdf_path);
        $this->assertTrue($user->can('play', $piece));
        $this->assertTrue($user->can('playPdf', $piece));
    }

    #[Test]
    public function the_pdf_is_not_recognised_when_musicxml_came_with_it(): void
    {
        Queue::fake();

        $this->actingAs(User::factory()->create())->post(route('pieces.store'), [
            'title' => 'Both', 'instrument' => 'violin', 'default_bpm' => 70, 'score' => $this->xml(), 'pdf' => $this->pdf(),
        ]);

        Queue::assertPushed(\App\Jobs\ParseMusicXml::class);
        Queue::assertNotPushed(ConvertPdfScore::class);
    }

    #[Test]
    public function a_second_pdf_next_to_a_pdf_score_is_rejected(): void
    {
        $this->actingAs(User::factory()->create())->post(route('pieces.store'), [
            'title' => 'Twice', 'instrument' => 'violin', 'default_bpm' => 70, 'score' => $this->pdf(), 'pdf' => $this->pdf('two.pdf'),
        ])->assertSessionHasErrors('pdf');

        $this->actingAs(User::factory()->create())->post(route('pieces.store'), [
            'title' => 'Fake', 'instrument' => 'violin', 'default_bpm' => 70, 'score' => $this->xml(), 'pdf' => $this->pdf('x.pdf', 'not a pdf'),
        ])->assertSessionHasErrors('pdf');
    }

    #[Test]
    public function the_pdf_page_and_file_are_for_people_who_may_see_a_piece_with_a_pdf(): void
    {
        Storage::disk('local')->put('pieces/1/a.pdf', "%PDF-1.7\n");
        $owner = User::factory()->create();
        $piece = Piece::factory()->for($owner, 'owner')->create(['parse_status' => 'pdf_only', 'musicxml_path' => null, 'source_pdf_path' => 'pieces/1/a.pdf']);
        $noPdf = Piece::factory()->for($owner, 'owner')->create(['parse_status' => 'ready']);

        $this->actingAs($owner)->get(route('pieces.pdf', $piece))->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->actingAs($owner)->get(route('player.pdf', $piece))
            ->assertOk()
            ->assertSee('results may be restricted and inaccurate')
            ->assertSee('Notes you played')
            ->assertSee('nearest');

        $this->actingAs(User::factory()->create())->get(route('pieces.pdf', $piece))->assertForbidden();
        $this->actingAs(User::factory()->create())->get(route('player.pdf', $piece))->assertForbidden();
        $this->actingAs($owner)->get(route('player.pdf', $noPdf))->assertForbidden();
    }

    #[Test]
    public function a_recognised_score_can_be_dropped_in_favour_of_the_pdf(): void
    {
        $owner = User::factory()->create();
        $piece = $this->uploadPdf($owner);
        $this->runJob($piece);
        copy(base_path('tests/fixtures/edge-cases.musicxml'), $this->spool.'/out/'.$piece->id.'.mxl');
        $this->runJob($piece);
        $piece->refresh();
        $recognised = $piece->musicxml_path;
        $this->assertSame('needs_review', $piece->parse_status);

        $this->actingAs($owner)->post(route('pieces.use-pdf', $piece))->assertRedirect(route('pieces.show', $piece));

        $piece->refresh();
        $this->assertSame('pdf_only', $piece->parse_status);
        $this->assertNull($piece->musicxml_path);
        $this->assertSame(0, $piece->notes()->count());
        Storage::disk('local')->assertMissing($recognised);
        Storage::disk('local')->assertExists($piece->source_pdf_path);
        $this->assertFalse($owner->can('play', $piece));
        $this->assertTrue($owner->can('playPdf', $piece));
    }

    #[Test]
    public function a_failed_recognition_can_fall_back_to_the_pdf_but_only_the_owner_and_only_then(): void
    {
        $owner = User::factory()->create();
        $piece = Piece::factory()->for($owner, 'owner')->create(['parse_status' => 'failed', 'musicxml_path' => null, 'source_pdf_path' => 'pieces/1/a.pdf']);

        $this->actingAs(User::factory()->create())->post(route('pieces.use-pdf', $piece))->assertForbidden();
        $this->actingAs($owner)->post(route('pieces.use-pdf', $piece))->assertRedirect();
        $this->assertSame('pdf_only', $piece->fresh()->parse_status);

        $ready = Piece::factory()->for($owner, 'owner')->create(['parse_status' => 'ready', 'source_pdf_path' => 'pieces/1/b.pdf']);
        $this->actingAs($owner)->post(route('pieces.use-pdf', $ready))->assertStatus(409);
    }

    #[Test]
    public function adding_musicxml_to_a_pdf_only_piece_makes_it_fully_playable_and_keeps_the_pdf(): void
    {
        $owner = User::factory()->create();
        $piece = Piece::factory()->for($owner, 'owner')->create(['parse_status' => 'pdf_only', 'musicxml_path' => null, 'source_pdf_path' => 'pieces/1/a.pdf']);
        Storage::disk('local')->put('pieces/1/a.pdf', "%PDF-1.7\n");

        $this->actingAs($owner)->put(route('pieces.update', $piece), [
            'title' => 'Now with notes', 'instrument' => 'violin', 'default_bpm' => 70, 'score' => $this->xml(),
        ])->assertSessionHasNoErrors();

        $piece->refresh();
        $this->assertSame('ready', $piece->parse_status);
        $this->assertSame(5, $piece->note_count);
        $this->assertSame('pieces/1/a.pdf', $piece->source_pdf_path);
        Storage::disk('local')->assertExists('pieces/1/a.pdf');
    }

    #[Test]
    public function replacing_the_pdf_of_a_piece_with_musicxml_does_not_start_recognition(): void
    {
        Queue::fake();
        $owner = User::factory()->create();
        Storage::disk('local')->put('pieces/1/old.pdf', "%PDF-1.7\n");
        Storage::disk('local')->put('pieces/1/a.musicxml', 'x');
        $piece = Piece::factory()->for($owner, 'owner')->create(['parse_status' => 'ready', 'musicxml_path' => 'pieces/1/a.musicxml', 'source_pdf_path' => 'pieces/1/old.pdf']);

        $this->actingAs($owner)->put(route('pieces.update', $piece), [
            'title' => 'T', 'instrument' => 'violin', 'default_bpm' => 70, 'pdf' => $this->pdf('new.pdf'),
        ])->assertSessionHasNoErrors();

        $piece->refresh();
        $this->assertSame('ready', $piece->parse_status);
        $this->assertSame('pieces/1/a.musicxml', $piece->musicxml_path);
        $this->assertNotSame('pieces/1/old.pdf', $piece->source_pdf_path);
        Storage::disk('local')->assertMissing('pieces/1/old.pdf');
        Queue::assertNothingPushed();
    }

    #[Test]
    public function a_new_pdf_for_a_pdf_only_piece_is_recognised_again(): void
    {
        Queue::fake();
        $owner = User::factory()->create();
        $piece = Piece::factory()->for($owner, 'owner')->create(['parse_status' => 'pdf_only', 'musicxml_path' => null, 'source_pdf_path' => 'pieces/1/a.pdf']);

        $this->actingAs($owner)->put(route('pieces.update', $piece), [
            'title' => 'T', 'instrument' => 'violin', 'default_bpm' => 70, 'score' => $this->pdf('better.pdf'),
        ])->assertSessionHasNoErrors();

        $this->assertSame('pending', $piece->fresh()->parse_status);
        Queue::assertPushed(ConvertPdfScore::class);
    }
}
