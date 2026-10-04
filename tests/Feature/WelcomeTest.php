<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class WelcomeTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function guests_see_the_welcome_page_and_members_go_to_their_pieces(): void
    {
        $this->get('/')->assertOk()->assertSee('Pitchwise');
        $this->actingAs(User::factory()->create())->get('/')->assertRedirect(route('pieces.index'));
    }
}
