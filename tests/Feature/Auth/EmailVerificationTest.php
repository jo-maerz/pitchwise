<?php

namespace Tests\Feature\Auth;

use App\Models\Organization;
use App\Models\Piece;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_email_verification_screen_can_be_rendered(): void
    {
        $user = User::factory()->unverified()->create();

        $response = $this->actingAs($user)->get('/verify-email');

        $response->assertStatus(200)->assertSee($user->email);
    }

    public function test_registering_sends_a_verification_link(): void
    {
        Notification::fake();

        $this->post('/register', [
            'name' => 'Test User', 'email' => 'test@example.com', 'password' => 'password', 'password_confirmation' => 'password',
            'account_type' => 'private',
        ]);

        Notification::assertSentTo(User::where('email', 'test@example.com')->sole(), VerifyEmail::class);
    }

    public function test_unverified_members_cannot_reach_their_organizations_library(): void
    {
        $organization = Organization::factory()->create();
        $piece = Piece::factory()->inOrganization($organization)->create();
        $member = User::factory()->unverified()->create(['organization_id' => $organization->id]);

        foreach (['pieces.index', 'pieces.show', 'pieces.file', 'pieces.pdf', 'annotations.show', 'player.show'] as $route) {
            $this->actingAs($member)->get(route($route, $piece))->assertRedirect(route('verification.notice'));
        }
        $this->actingAs($member)->put(route('annotations.update', [$piece, 'shared']), ['pages' => []])
            ->assertRedirect(route('verification.notice'));
    }

    public function test_unverified_users_can_still_correct_their_email_address(): void
    {
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)->get('/profile')->assertOk();
    }

    public function test_email_can_be_verified(): void
    {
        $user = User::factory()->unverified()->create();

        Event::fake();

        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1($user->email)]
        );

        $response = $this->actingAs($user)->get($verificationUrl);

        Event::assertDispatched(Verified::class);
        $this->assertTrue($user->fresh()->hasVerifiedEmail());
        $response->assertRedirect(route('dashboard', absolute: false).'?verified=1');
    }

    public function test_email_is_not_verified_with_invalid_hash(): void
    {
        $user = User::factory()->unverified()->create();

        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1('wrong-email')]
        );

        $this->actingAs($user)->get($verificationUrl);

        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }
}
