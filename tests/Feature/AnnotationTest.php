<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\Piece;
use App\Models\PieceAnnotation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AnnotationTest extends TestCase
{
    use RefreshDatabase;

    private const PAGES = [[['type' => 'Text', 'text' => 'Lighter bow here', 'left' => 100, 'top' => 200]], []];

    private function save(User $user, Piece $piece, string $layer, array $pages = self::PAGES)
    {
        return $this->actingAs($user)->putJson(route('annotations.update', [$piece, $layer]), ['pages' => $pages]);
    }

    #[Test]
    public function members_keep_private_annotations_and_outsiders_get_none(): void
    {
        $school = Organization::factory()->create();
        $piece = Piece::factory()->inOrganization($school)->create();
        $student = User::factory()->create(['organization_id' => $school->id]);
        $classmate = User::factory()->create(['organization_id' => $school->id]);

        $this->actingAs($student)->get(route('annotations.show', $piece))->assertOk();
        $this->save($student, $piece, 'mine')->assertOk();
        $this->assertSame(self::PAGES, PieceAnnotation::where('user_id', $student->id)->sole()->pages);

        $this->actingAs($classmate)->get(route('annotations.show', $piece))
            ->assertOk()
            ->assertDontSee('Lighter bow here');

        $outsider = User::factory()->create(['organization_id' => Organization::factory()->create()->id]);
        $this->actingAs($outsider)->get(route('annotations.show', $piece))->assertForbidden();
        $this->save($outsider, $piece, 'mine')->assertForbidden();
    }

    #[Test]
    public function shared_library_pieces_take_personal_annotations_only(): void
    {
        $piece = Piece::factory()->create();
        foreach ([User::factory()->create(), User::factory()->admin()->create(), User::factory()->orgAdmin()->create()] as $user) {
            $this->actingAs($user)->get(route('annotations.show', $piece))
                ->assertOk()
                ->assertDontSee('Draw on')
                ->assertDontSee('annotate-show-shared');
            $this->save($user, $piece, 'mine')->assertOk();
            $this->save($user, $piece, 'shared')->assertForbidden();
        }
        $this->assertSame(0, PieceAnnotation::shared()->count());
    }

    #[Test]
    public function the_shared_layer_is_edited_by_members_given_the_instrument_and_seen_by_everyone(): void
    {
        $school = Organization::factory()->create();
        $violin = Piece::factory()->inOrganization($school)->create(['instrument' => 'violin']);
        $flute = Piece::factory()->inOrganization($school)->create(['instrument' => 'flute']);
        $stringsLead = User::factory()->create(['organization_id' => $school->id, 'annotation_instruments' => ['violin', 'viola', 'cello']]);
        $student = User::factory()->create(['organization_id' => $school->id]);
        $teacher = User::factory()->orgAdmin($school)->create();

        $this->save($stringsLead, $violin, 'shared')->assertOk();
        $this->save($stringsLead, $flute, 'shared')->assertForbidden();
        $this->save($student, $violin, 'shared')->assertForbidden();
        $this->save($teacher, $flute, 'shared')->assertOk();

        $this->assertSame($stringsLead->id, PieceAnnotation::shared()->where('piece_id', $violin->id)->sole()->updated_by);
        $this->actingAs($student)->get(route('annotations.show', $violin))
            ->assertOk()
            ->assertSee('Lighter bow here')
            ->assertSee($stringsLead->name);
    }

    #[Test]
    public function practice_shows_the_shared_layer_under_the_users_own_on_the_score_they_were_drawn_on(): void
    {
        $school = Organization::factory()->create();
        $piece = Piece::factory()->inOrganization($school)->create(['parse_status' => 'ready']);
        $student = User::factory()->create(['organization_id' => $school->id]);
        $classmate = User::factory()->create(['organization_id' => $school->id]);
        $own = [[['type' => 'Text', 'text' => 'Mine']]];
        $theirs = [[['type' => 'Text', 'text' => 'Theirs']]];
        $this->save(User::factory()->orgAdmin($school)->create(), $piece, 'shared')->assertOk();
        $this->save($student, $piece, 'mine', $own)->assertOk();
        $this->save($classmate, $piece, 'mine', $theirs)->assertOk();

        $annotations = $this->actingAs($student)->get(route('player.show', $piece))->assertOk()->viewData('config')['annotations'];
        $this->assertSame([self::PAGES, $own], $annotations['layers']);
        $this->assertFalse($annotations['outdated']);

        $newcomer = User::factory()->create(['organization_id' => $school->id]);
        $this->assertSame([self::PAGES], $this->actingAs($newcomer)->get(route('player.show', $piece))->viewData('config')['annotations']['layers']);

        $piece->update(['source_pdf_path' => 'pieces/score.pdf']);
        $this->assertNull($this->actingAs($student)->get(route('player.show', $piece))->viewData('config')['annotations']);
        $this->assertTrue($this->actingAs($student)->get(route('player.pdf', $piece))->assertOk()->assertSee('earlier upload')->viewData('config')['annotations']['outdated']);
    }

    #[Test]
    public function private_users_see_their_own_marks_while_practising_shared_library_pieces(): void
    {
        $piece = Piece::factory()->create(['parse_status' => 'ready']);
        $user = User::factory()->create();

        $this->assertNull($this->actingAs($user)->get(route('player.show', $piece))->viewData('config')['annotations']);
        $this->save($user, $piece, 'mine')->assertOk();
        $this->actingAs($user)->get(route('player.show', $piece))->assertSee('Show annotations');
        $this->assertSame([self::PAGES], $this->actingAs($user)->get(route('player.show', $piece))->viewData('config')['annotations']['layers']);
    }

    #[Test]
    public function only_drawn_shapes_and_text_are_accepted(): void
    {
        $piece = Piece::factory()->inOrganization()->create();
        $student = User::factory()->create(['organization_id' => $piece->organization_id]);

        $this->save($student, $piece, 'mine', [[['type' => 'Image', 'src' => 'https://example.com/track.png']]])->assertUnprocessable();
        $this->save($student, $piece, 'mine', [[['type' => 'Rect', 'fill' => ['type' => 'pattern', 'source' => 'https://example.com/x.png']]]])->assertUnprocessable();
        $this->save($student, $piece, 'mine', ['not a page'])->assertUnprocessable();
        $this->save($student, $piece, 'mine', [[['type' => 'Path', 'path' => [['M', 0, 0], ['L', 10, 10]]], ['type' => 'Polyline', 'points' => [['x' => 0, 'y' => 0]]]]])->assertOk();
        $this->assertSame(1, PieceAnnotation::count());
    }

    #[Test]
    public function a_new_upload_marks_existing_annotations_as_outdated(): void
    {
        $piece = Piece::factory()->inOrganization()->create(['musicxml_path' => 'pieces/a.musicxml']);
        $student = User::factory()->create(['organization_id' => $piece->organization_id]);
        $this->save($student, $piece, 'mine')->assertOk();

        $this->actingAs($student)->get(route('annotations.show', $piece))->assertDontSee('earlier upload');
        $piece->update(['musicxml_path' => 'pieces/b.musicxml']);
        $this->actingAs($student)->get(route('annotations.show', $piece))->assertSee('earlier upload');
    }

    #[Test]
    public function organization_admins_choose_who_annotates_which_instruments(): void
    {
        $school = Organization::factory()->create();
        $teacher = User::factory()->orgAdmin($school)->create();
        $student = User::factory()->create(['organization_id' => $school->id]);
        $outsider = User::factory()->create(['organization_id' => Organization::factory()->create()->id]);

        $this->actingAs($teacher)->get(route('organizations.members', $school))->assertOk()->assertSee($student->name);
        $this->actingAs($teacher)->put(route('organizations.members.update', [$school, $student]), ['instruments' => ['violin', 'cello', 'violin']])
            ->assertSessionHasNoErrors();
        $this->assertSame(['violin', 'cello'], $student->fresh()->annotation_instruments);

        $this->actingAs($teacher)->put(route('organizations.members.update', [$school, $student]), ['instruments' => ['banjo']])->assertSessionHasErrors('instruments.0');
        $this->actingAs($teacher)->put(route('organizations.members.update', [$school, $outsider]), ['instruments' => ['violin']])->assertNotFound();
        $this->actingAs($student)->get(route('organizations.members', $school))->assertForbidden();
        $this->actingAs($teacher)->get(route('organizations.members', $outsider->organization))->assertForbidden();
        $this->actingAs(User::factory()->admin()->create())->get(route('organizations.members', $school))->assertOk();

        $this->actingAs($teacher)->put(route('organizations.members.update', [$school, $student]), [])->assertSessionHasNoErrors();
        $this->assertNull($student->fresh()->annotation_instruments);
    }

    #[Test]
    public function annotation_rights_end_when_a_member_changes_organization(): void
    {
        $student = User::factory()->create(['organization_id' => Organization::factory()->create()->id, 'annotation_instruments' => ['violin']]);

        $student->update(['organization_id' => Organization::factory()->create()->id]);

        $this->assertNull($student->fresh()->annotation_instruments);
    }
}
