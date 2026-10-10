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
            'account_type' => 'private',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_new_users_register_as_private_users_or_members_of_an_institution(): void
    {
        $school = Organization::factory()->create(['name' => 'Riverside Strings']);
        $this->get('/register')->assertSee('Riverside Strings')->assertSee('Are you a private user?');

        $this->post('/register', [
            'name' => 'Member', 'email' => 'member@example.com', 'password' => 'password', 'password_confirmation' => 'password',
            'account_type' => 'organization', 'organization' => 'Riverside Strings',
        ])->assertRedirect();
        auth()->logout();
        $this->post('/register', [
            'name' => 'Loner', 'email' => 'loner@example.com', 'password' => 'password', 'password_confirmation' => 'password',
            'account_type' => 'private', 'organization' => 'Riverside Strings',
        ])->assertRedirect();

        $member = User::where('email', 'member@example.com')->sole();
        $this->assertSame([$school->id, Role::User], [$member->organization_id, $member->role]);
        $this->assertNull(User::where('email', 'loner@example.com')->value('organization_id'));
    }

    public function test_the_account_type_must_be_chosen(): void
    {
        $this->post('/register', [
            'name' => 'X', 'email' => 'x@example.com', 'password' => 'password', 'password_confirmation' => 'password',
        ])->assertSessionHasErrors('account_type');
        $this->assertGuest();
    }

    public function test_members_must_name_a_known_institution(): void
    {
        foreach (['', 'Nowhere Academy'] as $organization) {
            $this->post('/register', [
                'name' => 'X', 'email' => 'x@example.com', 'password' => 'password', 'password_confirmation' => 'password',
                'account_type' => 'organization', 'organization' => $organization,
            ])->assertSessionHasErrors('organization');
        }
        $this->assertGuest();
    }
}
