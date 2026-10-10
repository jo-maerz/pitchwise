<?php

namespace Tests\Feature\Auth;

use App\Enums\Role;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_can_be_rendered(): void
    {
        $response = $this->get('/register');

        $response->assertStatus(200);
    }

    public function test_new_users_can_register(): void
    {
        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_new_users_choose_an_organization_or_none(): void
    {
        $school = Organization::factory()->create(['name' => 'Riverside Strings']);
        $this->get('/register')->assertSee('Riverside Strings')->assertSee('No organization');

        $this->post('/register', [
            'name' => 'Member', 'email' => 'member@example.com', 'password' => 'password', 'password_confirmation' => 'password',
            'organization_id' => $school->id,
        ])->assertRedirect();
        auth()->logout();
        $this->post('/register', [
            'name' => 'Loner', 'email' => 'loner@example.com', 'password' => 'password', 'password_confirmation' => 'password',
            'organization_id' => '',
        ])->assertRedirect();

        $member = User::where('email', 'member@example.com')->sole();
        $this->assertSame([$school->id, Role::User], [$member->organization_id, $member->role]);
        $this->assertNull(User::where('email', 'loner@example.com')->value('organization_id'));
    }

    public function test_an_unknown_organization_is_rejected(): void
    {
        $this->post('/register', [
            'name' => 'X', 'email' => 'x@example.com', 'password' => 'password', 'password_confirmation' => 'password',
            'organization_id' => 999,
        ])->assertSessionHasErrors('organization_id');
        $this->assertGuest();
    }
}
