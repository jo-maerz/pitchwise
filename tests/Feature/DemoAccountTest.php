<?php

namespace Tests\Feature;

use App\Models\Folder;
use App\Models\Organization;
use App\Models\Piece;
use App\Models\PracticeSession;
use App\Models\User;
use App\Services\PieceService;
use App\Support\Demo;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

/** The demo on a public site: its own password, accounts nobody can take over, and a nightly reset. */
class DemoAccountTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'violin-demo-2026';

    private function onPublicSite(?string $demoPassword = self::PASSWORD): void
    {
        $this->app['env'] = 'production';
        config(['app.url' => 'https://pitchwise.example.com', 'practice.demo_password' => $demoPassword]);
    }

    private function seedDemo(): void
    {
        $this->app->make(DatabaseSeeder::class)->setContainer($this->app)->__invoke();
    }

    #[Test]
    public function a_public_site_seeds_the_demo_with_its_own_password_and_without_the_admin(): void
    {
        Storage::fake('local');
        $this->onPublicSite();

        $this->seedDemo();

        $teacher = User::where('email', 'teacher@example.com')->sole();
        $this->assertTrue(Hash::check(self::PASSWORD, $teacher->password));
        $this->assertFalse(Hash::check('password', $teacher->password));
        $this->assertTrue(User::where('email', 'demo@example.com')->sole()->hasVerifiedEmail());
        $this->assertFalse(User::where('email', 'admin@example.com')->exists(), 'a shared admin could demote the real admins');
    }

    #[Test]
    public function a_short_demo_password_is_refused_on_a_public_site(): void
    {
        $this->onPublicSite('short');

        $this->expectException(RuntimeException::class);
        try {
            $this->seedDemo();
        } finally {
            $this->assertSame(0, User::count());
        }
    }

    #[Test]
    public function demo_accounts_cannot_be_renamed_given_a_new_password_or_deleted(): void
    {
        $demo = User::factory()->create(['email' => 'demo@example.com', 'name' => 'Demo Violinist']);

        $this->actingAs($demo)->get('/profile')->assertOk()->assertSee('Shared demo account')->assertDontSee('Delete Account');
        $this->actingAs($demo)->patch('/profile', ['name' => 'Taken', 'email' => 'mine@example.org'])->assertForbidden();
        $this->actingAs($demo)->put('/password', ['current_password' => 'password', 'password' => 'new-password', 'password_confirmation' => 'new-password'])->assertForbidden();
        $this->actingAs($demo)->delete('/profile', ['password' => 'password'])->assertForbidden();

        $demo->refresh();
        $this->assertSame(['Demo Violinist', 'demo@example.com'], [$demo->name, $demo->email]);
        $this->assertTrue(Hash::check('password', $demo->password));
    }

    #[Test]
    public function demo_accounts_get_no_password_reset_mail(): void
    {
        Notification::fake();
        $demo = User::factory()->create(['email' => 'teacher@example.com']);

        $this->post('/forgot-password', ['email' => $demo->email])->assertRedirect();

        Notification::assertNotSentTo($demo, ResetPassword::class);
    }

    #[Test]
    public function the_nightly_reset_puts_the_demo_back_and_leaves_everyone_else_alone(): void
    {
        Storage::fake('local');
        $this->onPublicSite();
        $this->seedDemo();
        $school = Organization::where('name', Demo::SCHOOL)->sole();
        $catalogue = Piece::whereNull('organization_id')->pluck('id')->sort()->values()->all();
        $twinkle = Piece::where('title', 'Twinkle, Twinkle, Little Star')->sole();

        // What visitors do with the shared accounts during the day.
        $teacher = User::where('email', 'teacher@example.com')->sole();
        app(PieceService::class)->delete(Piece::where('title', 'Frère Jacques')->sole());
        Piece::factory()->inOrganization($school)->create(['owner_id' => $teacher->id, 'title' => 'Visitor upload']);
        Folder::where('organization_id', $school->id)->where('name', 'Beginners')->update(['name' => 'Renamed']);
        $demo = User::where('email', 'demo@example.com')->sole();
        $demo->forceFill(['name' => 'Taken', 'password' => 'changed-by-someone'])->save();
        $demo->createToken('player');
        PracticeSession::factory()->create(['user_id' => $demo->id, 'piece_id' => $twinkle->id]);
        // Someone who registered into the school with their own account.
        $member = User::factory()->create(['organization_id' => $school->id]);
        $ownRun = PracticeSession::factory()->create(['user_id' => $member->id, 'piece_id' => $twinkle->id]);

        $this->artisan('practice:reset-demo')->assertSuccessful();

        $this->assertSame(13, Piece::where('organization_id', $school->id)->where('parse_status', 'ready')->count());
        $this->assertTrue(Piece::where('title', 'Frère Jacques')->exists());
        $this->assertFalse(Piece::where('title', 'Visitor upload')->exists());
        $this->assertSame(['Beginners', 'Spring concert', 'Winter concert'], Folder::where('organization_id', $school->id)->orderBy('name')->pluck('name')->all());

        $this->assertNull(User::find($demo->id), 'logged-in visitors are signed out');
        $this->assertSame(0, $demo->tokens()->count());
        $demo = User::where('email', 'demo@example.com')->sole();
        $this->assertSame('Demo Violinist', $demo->name);
        $this->assertTrue(Hash::check(self::PASSWORD, $demo->password));
        $this->assertSame(60, $demo->practiceSessions()->count(), 'the seeded history and nothing else');

        $this->assertSame($school->id, $member->fresh()->organization_id);
        $this->assertTrue($ownRun->exists(), 'runs on shared-library pieces are kept');
        $this->assertSame($catalogue, Piece::whereNull('organization_id')->pluck('id')->sort()->values()->all());
        $this->assertFalse(User::where('email', 'admin@example.com')->exists());
    }

    #[Test]
    public function the_reset_does_nothing_on_a_public_site_without_a_demo_password(): void
    {
        $this->onPublicSite(null);
        $demo = User::factory()->create(['email' => 'demo@example.com']);

        $this->artisan('practice:reset-demo')->assertFailed();

        $this->assertNotNull($demo->fresh());
    }

    #[Test]
    public function the_reset_runs_every_night_only_where_a_demo_password_is_set(): void
    {
        $event = collect($this->app->make(Schedule::class)->events())
            ->sole(fn (Event $e) => str_contains($e->command, 'practice:reset-demo'));
        $this->assertSame('0 3 * * *', $event->expression);

        config(['practice.demo_password' => null]);
        $this->assertFalse($event->filtersPass($this->app));
        config(['practice.demo_password' => self::PASSWORD]);
        $this->assertTrue($event->filtersPass($this->app));
    }
}
