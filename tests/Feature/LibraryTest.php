<?php

namespace Tests\Feature;

use App\Models\Folder;
use App\Models\Organization;
use App\Models\Piece;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class LibraryTest extends TestCase
{
    use RefreshDatabase;

    private function score(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('edge.musicxml', file_get_contents(base_path('tests/fixtures/edge-cases.musicxml')));
    }

    #[Test]
    public function an_organization_admin_manages_folders_and_pieces_of_their_organization_only(): void
    {
        Storage::fake('local');
        Queue::fake();
        $school = Organization::factory()->create();
        $teacher = User::factory()->orgAdmin($school)->create();
        $otherSchool = Organization::factory()->create();

        $this->actingAs($teacher)->post(route('folders.store'), ['location' => 'root:'.$school->id, 'name' => 'Etudes'])->assertRedirect();
        $etudes = Folder::sole();
        $this->assertSame($school->id, $etudes->organization_id);

        $this->actingAs($teacher)->post(route('folders.store'), ['location' => 'folder:'.$etudes->id, 'name' => 'Kreutzer'])->assertRedirect();
        $kreutzer = Folder::where('name', 'Kreutzer')->sole();
        $this->assertSame([$etudes->id, $school->id], [$kreutzer->parent_id, $kreutzer->organization_id]);

        $this->actingAs($teacher)->post(route('pieces.store'), [
            'location' => 'folder:'.$kreutzer->id, 'title' => 'No. 2', 'instrument' => 'violin', 'default_bpm' => 60, 'score' => $this->score(),
        ])->assertSessionHasNoErrors();
        $piece = Piece::sole();
        $this->assertSame([$school->id, $kreutzer->id], [$piece->organization_id, $piece->folder_id]);

        foreach (['root:shared', 'root:'.$otherSchool->id] as $elsewhere) {
            $this->actingAs($teacher)->post(route('folders.store'), ['location' => $elsewhere, 'name' => 'X'])->assertForbidden();
            $this->actingAs($teacher)->post(route('pieces.store'), [
                'location' => $elsewhere, 'title' => 'X', 'instrument' => 'violin', 'default_bpm' => 60, 'score' => $this->score(),
            ])->assertSessionHasErrors('location');
        }
        $shared = Folder::factory()->create();
        $this->actingAs($teacher)->put(route('folders.update', $shared), ['name' => 'Mine now'])->assertForbidden();
        $this->actingAs($teacher)->delete(route('folders.destroy', $shared))->assertForbidden();
        $this->assertSame(1, Piece::count());
    }

    #[Test]
    public function members_browse_folders_and_outsiders_cannot_open_them(): void
    {
        $school = Organization::factory()->create();
        $member = User::factory()->create(['organization_id' => $school->id]);
        $folder = Folder::factory()->create(['organization_id' => $school->id, 'name' => 'Orchestra parts']);
        $sub = Folder::factory()->create(['organization_id' => $school->id, 'parent_id' => $folder->id, 'name' => 'Second violins']);
        Piece::factory()->inOrganization($school)->create(['folder_id' => $sub->id, 'title' => 'Finlandia', 'parse_status' => 'ready']);

        $this->actingAs($member)->get(route('pieces.index'))->assertSee('Orchestra parts')->assertDontSee('Finlandia');
        $this->actingAs($member)->get(route('folders.show', $sub))->assertOk()->assertSee('Finlandia')->assertSee('Orchestra parts')
            ->assertDontSee('Add folder');
        $this->actingAs(User::factory()->create())->get(route('folders.show', $folder))->assertForbidden();
    }

    #[Test]
    public function folders_need_unique_names_and_only_empty_ones_can_be_deleted(): void
    {
        $admin = User::factory()->admin()->create();
        $folder = Folder::factory()->create(['name' => 'Scales']);
        $child = Folder::factory()->create(['parent_id' => $folder->id, 'name' => 'Violin']);

        $this->actingAs($admin)->post(route('folders.store'), ['location' => 'root:shared', 'name' => 'Scales'])->assertSessionHasErrors('name');
        $this->actingAs($admin)->post(route('folders.store'), ['location' => 'root:shared', 'name' => 'a/b'])->assertSessionHasErrors('name');
        $this->actingAs($admin)->delete(route('folders.destroy', $folder))->assertSessionHasErrors('folder');
        $this->assertModelExists($folder);

        $this->actingAs($admin)->delete(route('folders.destroy', $child))->assertRedirect(route('folders.show', $folder));
        $this->actingAs($admin)->put(route('folders.update', $folder), ['name' => 'Technique'])->assertRedirect();
        $this->assertSame('Technique', $folder->fresh()->name);
    }

    #[Test]
    public function a_piece_can_be_moved_to_another_folder(): void
    {
        $admin = User::factory()->admin()->create();
        $school = Organization::factory()->create();
        $folder = Folder::factory()->create(['organization_id' => $school->id]);
        $piece = Piece::factory()->create();

        $this->actingAs($admin)->get(route('pieces.edit', $piece))->assertOk()->assertSee($school->name.' / '.$folder->name);
        $this->actingAs($admin)->put(route('pieces.update', $piece), [
            'location' => 'folder:'.$folder->id, 'title' => $piece->title, 'instrument' => 'violin', 'default_bpm' => 80,
        ])->assertSessionHasNoErrors();

        $this->assertSame([$school->id, $folder->id], [$piece->fresh()->organization_id, $piece->fresh()->folder_id]);
    }

    #[Test]
    public function the_instrument_filter_hides_other_pieces_and_folders_without_matches(): void
    {
        $user = User::factory()->create();
        $strings = Folder::factory()->create(['name' => 'Strings corner']);
        $winds = Folder::factory()->create(['name' => 'Wind band']);
        $nested = Folder::factory()->create(['name' => 'Flute duets', 'parent_id' => $winds->id]);
        Piece::factory()->create(['folder_id' => $strings->id, 'title' => 'Cello thing', 'instrument' => 'cello']);
        Piece::factory()->create(['folder_id' => $nested->id, 'title' => 'Flute thing', 'instrument' => 'flute']);
        Piece::factory()->create(['title' => 'Loose violin', 'instrument' => 'violin']);

        $this->actingAs($user)->get(route('pieces.index'))
            ->assertSee('Strings corner')->assertSee('Wind band')->assertSee('Loose violin')
            ->assertSee('<option value="flute"', false)->assertDontSee('<option value="oboe"', false);
        $this->actingAs($user)->get(route('pieces.index', ['instrument' => 'flute']))
            ->assertSee('Wind band')->assertDontSee('Strings corner')->assertDontSee('Loose violin')
            ->assertSee(route('folders.show', [$winds, 'instrument' => 'flute']), false);
        $this->actingAs($user)->get(route('folders.show', [$nested, 'instrument' => 'cello']))
            ->assertDontSee('Flute thing')->assertSee('Nothing for this instrument here.');
        $this->actingAs($user)->get(route('pieces.index', ['instrument' => 'banjo']))->assertOk()->assertSee('Loose violin');
    }

    #[Test]
    public function folder_names_can_be_edited_in_place_by_those_who_manage_them(): void
    {
        $school = Organization::factory()->create();
        $teacher = User::factory()->orgAdmin($school)->create();
        $folder = Folder::factory()->create(['organization_id' => $school->id, 'name' => 'Etudes']);

        $this->actingAs($teacher)->get(route('pieces.index'))->assertSee('Rename Etudes')->assertSee(route('folders.update', $folder), false);
        $this->actingAs($teacher)->get(route('folders.show', $folder))->assertSee('Rename Etudes');
        $this->actingAs(User::factory()->create(['organization_id' => $school->id]))->get(route('folders.show', $folder))
            ->assertSee('Etudes')->assertDontSee('Rename Etudes');

        $this->actingAs($teacher)->from(route('pieces.index'))->put(route('folders.update', $folder), ['name' => 'Studies'])
            ->assertRedirect(route('pieces.index'));
        $this->assertSame('Studies', $folder->fresh()->name);
    }

    #[Test]
    public function folder_rows_say_what_is_inside(): void
    {
        $folder = Folder::factory()->create(['name' => 'Etudes']);
        Folder::factory()->create(['parent_id' => $folder->id]);
        Piece::factory()->count(3)->create(['folder_id' => $folder->id]);

        $this->actingAs(User::factory()->create())->get(route('pieces.index'))->assertSee('1 folder · 3 pieces');
    }
}
