<?php

namespace Tests\Feature;

use App\Models\Folder;
use App\Models\Organization;
use App\Models\Piece;
use App\Models\User;
use Database\Seeders\CatalogueSeeder;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** What the seeders put in the shared and Demo Music School libraries. */
class DemoContentTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function the_shared_library_has_flesch_scales_for_violin_and_cello_and_showcase_pieces(): void
    {
        Storage::fake('local');
        $this->seed(CatalogueSeeder::class);

        $scales = Folder::whereNull('parent_id')->where('name', 'Scales')->sole();
        foreach (['Violin' => 'violin', 'Cello' => 'cello'] as $name => $instrument) {
            $folder = $scales->children()->where('name', $name)->sole();
            $this->assertSame(5, $folder->pieces()->where('instrument', $instrument)->where('parse_status', 'ready')->count());
        }
        $this->assertSame(0, Piece::whereNotNull('organization_id')->count());
        $this->assertSame(0, Piece::where('parse_status', '!=', 'ready')->count());
        $this->assertSame(0, Piece::whereNull('folder_id')->count(), 'the top level holds folders only');
        $this->assertSame(['Pieces', 'Scales', 'Warm-ups', 'Winds'], Folder::whereNull('parent_id')->orderBy('name')->pluck('name')->all());
        $winds = Folder::whereNull('parent_id')->where('name', 'Winds')->sole();
        $this->assertSame(['B♭ instruments' => 3, 'E♭ instruments' => 3], $winds->children()->withCount('pieces')->pluck('pieces_count', 'name')->all());
        $clarinet = Piece::where('title', 'Ode to Joy (clarinet in B♭)')->sole();
        $violin = Piece::where('title', 'Ode to Joy')->sole();
        $this->assertSame(
            $violin->notes()->pluck('midi_pitch')->map(fn ($m) => $m + 3)->all(),
            $clarinet->notes()->pluck('midi_pitch')->all(),
            'written in G for B♭ clarinet, it sounds in F: a minor third above the violin part in D',
        );

        $pieces = Folder::whereNull('parent_id')->where('name', 'Pieces')->sole();
        $this->actingAs(User::factory()->create())->get(route('folders.show', $pieces))
            ->assertSee('Minuet in G major')->assertSee('Cello Suite No. 1');
    }

    #[Test]
    public function the_demo_school_library_is_for_its_members_and_reads_transposing_parts_at_concert_pitch(): void
    {
        Storage::fake('local');
        $this->seed(DatabaseSeeder::class);
        $school = Organization::where('name', DatabaseSeeder::SCHOOL)->sole();

        $this->assertSame(13, Piece::where('organization_id', $school->id)->where('parse_status', 'ready')->count());
        $spring = Folder::where('organization_id', $school->id)->where('name', 'Spring concert')->sole();
        $sounding = $spring->pieces()->where('title', 'like', 'Happy Birthday%')->get()
            ->map(fn (Piece $p) => $p->notes()->pluck('midi_pitch')->map(fn ($m) => $m % 12)->all())->unique();
        $this->assertCount(1, $sounding, 'all Happy Birthday parts sound the same notes (the cello an octave lower)');
        $violin = Piece::where('title', 'Jingle Bells')->sole();
        $trumpet = Piece::where('title', 'Jingle Bells (trumpet)')->sole();
        $this->assertSame($violin->notes()->pluck('midi_pitch')->all(), $trumpet->notes()->pluck('midi_pitch')->all(), 'B♭ trumpet part sounds like the violin');

        $demo = User::where('email', 'demo@example.com')->sole();
        $this->assertTrue($demo->can('play', $trumpet));
        $this->assertFalse(User::factory()->create()->can('view', $trumpet));
        $this->assertGreaterThanOrEqual(50, $demo->practiceSessions()->whereNotNull('finished_at')->count());
        $this->assertGreaterThan(5, $demo->practiceSessions()->distinct()->count('piece_id'));

        $private = User::where('email', 'private@example.com')->sole();
        $this->assertNull($private->organization_id);
        $this->assertFalse($private->can('view', $trumpet));
        $this->assertTrue($private->can('view', Piece::whereNull('organization_id')->first()));
    }

    #[Test]
    public function demo_users_are_verified_even_if_they_existed_unverified_before_seeding(): void
    {
        Storage::fake('local');
        User::factory()->unverified()->create(['email' => 'admin@example.com']);

        $this->seed(DatabaseSeeder::class);

        $demoEmails = ['admin@example.com', 'teacher@example.com', 'demo@example.com', 'private@example.com'];
        $this->assertSame(4, User::whereIn('email', $demoEmails)->whereNotNull('email_verified_at')->count());
        $this->actingAs(User::where('email', 'private@example.com')->sole())->get(route('dashboard'))->assertOk();
    }
}
