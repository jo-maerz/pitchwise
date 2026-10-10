<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Folder;
use App\Models\Organization;
use App\Models\Piece;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AdminTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function only_admins_reach_the_admin_dashboard(): void
    {
        $this->actingAs(User::factory()->create())->get(route('admin.index'))->assertForbidden();
        $this->actingAs(User::factory()->orgAdmin()->create())->get(route('admin.index'))->assertForbidden();
        $this->actingAs(User::factory()->orgAdmin()->create())->post(route('admin.organizations.store'), ['name' => 'X'])->assertForbidden();

        $admin = User::factory()->admin()->create();
        Organization::factory()->create(['name' => 'Riverside Strings']);
        $this->actingAs($admin)->get(route('admin.index'))->assertOk()->assertSee('Riverside Strings')->assertSee($admin->email);
        $this->actingAs($admin)->get(route('pieces.index'))->assertSee('Admin');
    }

    #[Test]
    public function admins_create_rename_and_delete_organizations(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post(route('admin.organizations.store'), ['name' => 'Youth Orchestra'])->assertRedirect(route('admin.index'));
        $org = Organization::sole();
        $this->actingAs($admin)->post(route('admin.organizations.store'), ['name' => 'Youth Orchestra'])->assertSessionHasErrors('name');
        $this->actingAs($admin)->put(route('admin.organizations.update', $org), ['name' => 'City Youth Orchestra'])->assertRedirect();
        $this->assertSame('City Youth Orchestra', $org->fresh()->name);
    }

    #[Test]
    public function deleting_an_organization_removes_its_library_and_keeps_its_members(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('pieces/org/a.musicxml', 'x');
        $admin = User::factory()->admin()->create();
        $org = Organization::factory()->create();
        $teacher = User::factory()->orgAdmin($org)->create();
        $folder = Folder::factory()->create(['organization_id' => $org->id]);
        $piece = Piece::factory()->inOrganization($org)->create(['folder_id' => $folder->id, 'musicxml_path' => 'pieces/org/a.musicxml']);

        $this->actingAs($admin)->delete(route('admin.organizations.destroy', $org))->assertRedirect(route('admin.index'));

        $this->assertModelMissing($org);
        $this->assertModelMissing($folder);
        $this->assertModelMissing($piece);
        Storage::disk('local')->assertMissing('pieces/org/a.musicxml');
        $teacher->refresh();
        $this->assertSame([null, Role::User], [$teacher->organization_id, $teacher->role]);
    }

    #[Test]
    public function admins_assign_roles_and_organizations(): void
    {
        $admin = User::factory()->admin()->create();
        $org = Organization::factory()->create();
        $user = User::factory()->create();

        $this->actingAs($admin)->put(route('admin.users.update', $user), ['role' => 'org_admin', 'organization_id' => $org->id])->assertRedirect();
        $user->refresh();
        $this->assertSame([Role::OrgAdmin, $org->id], [$user->role, $user->organization_id]);
        $this->assertTrue($user->can('create', Piece::class));

        $this->actingAs($admin)->put(route('admin.users.update', $user), ['role' => 'org_admin', 'organization_id' => ''])->assertSessionHasErrors('role');
        $this->actingAs($admin)->put(route('admin.users.update', $user), ['role' => 'boss'])->assertSessionHasErrors('role');
        $this->actingAs($admin)->put(route('admin.users.update', $admin), ['role' => 'user'])->assertSessionHasErrors('role');
        $this->assertSame(Role::Admin, $admin->fresh()->role);
    }

    #[Test]
    public function the_first_admin_is_made_from_the_command_line(): void
    {
        $user = User::factory()->unverified()->create(['email' => 'boss@example.com']);

        $this->artisan('practice:make-admin', ['email' => 'boss@example.com'])->assertSuccessful();
        $this->artisan('practice:make-admin', ['email' => 'nobody@example.com'])->assertFailed();

        $this->assertTrue($user->fresh()->isAdmin());
        $this->assertTrue($user->fresh()->hasVerifiedEmail());
    }
}
