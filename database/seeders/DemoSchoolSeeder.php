<?php

namespace Database\Seeders;

use App\Models\Organization;
use App\Models\User;
use App\Services\PieceService;
use Illuminate\Database\Seeder;

/** Demo Music School's own library, uploaded by its organization admin: only its members see it. */
class DemoSchoolSeeder extends Seeder
{
    use SeedsLibrary;

    private const PIECES = [
        ['folder' => ['Beginners'], 'file' => 'demo-school/frere-jacques-violin.musicxml', 'title' => 'Frère Jacques', 'composer' => 'Traditional', 'bpm' => 96],
        ['folder' => ['Beginners'], 'file' => 'demo-school/hot-cross-buns-flute.musicxml', 'title' => 'Hot Cross Buns', 'composer' => 'Traditional', 'bpm' => 90, 'instrument' => 'flute'],
        ['folder' => ['Beginners'], 'file' => 'demo-school/mary-had-a-little-lamb-clarinet.musicxml', 'title' => 'Mary Had a Little Lamb', 'composer' => 'Traditional', 'bpm' => 100, 'instrument' => 'clarinet'],
        ['folder' => ['Winter concert'], 'file' => 'demo-school/jingle-bells-violin.musicxml', 'title' => 'Jingle Bells', 'composer' => 'James Lord Pierpont', 'bpm' => 112],
        ['folder' => ['Winter concert'], 'file' => 'demo-school/jingle-bells-cello.musicxml', 'title' => 'Jingle Bells (cello)', 'composer' => 'James Lord Pierpont', 'bpm' => 112, 'instrument' => 'cello'],
        ['folder' => ['Winter concert'], 'file' => 'demo-school/jingle-bells-trumpet.musicxml', 'title' => 'Jingle Bells (trumpet)', 'composer' => 'James Lord Pierpont', 'bpm' => 112, 'instrument' => 'trumpet'],
        // Every part sounds in F major, so the whole group can play together.
        ['folder' => ['Spring concert'], 'file' => 'demo-school/spring/happy-birthday-violin.musicxml', 'title' => 'Happy Birthday (violin)', 'composer' => 'Mildred and Patty Hill', 'bpm' => 100],
        ['folder' => ['Spring concert'], 'file' => 'demo-school/spring/happy-birthday-cello.musicxml', 'title' => 'Happy Birthday (cello)', 'composer' => 'Mildred and Patty Hill', 'bpm' => 100, 'instrument' => 'cello'],
        ['folder' => ['Spring concert'], 'file' => 'demo-school/spring/happy-birthday-clarinet.musicxml', 'title' => 'Happy Birthday (clarinet in B♭)', 'composer' => 'Mildred and Patty Hill', 'bpm' => 100, 'instrument' => 'clarinet'],
        ['folder' => ['Spring concert'], 'file' => 'demo-school/spring/happy-birthday-alto-sax.musicxml', 'title' => 'Happy Birthday (alto saxophone)', 'composer' => 'Mildred and Patty Hill', 'bpm' => 100, 'instrument' => 'alto saxophone'],
        ['folder' => ['Spring concert'], 'file' => 'demo-school/spring/yankee-doodle-flute.musicxml', 'title' => 'Yankee Doodle (flute)', 'composer' => 'Traditional', 'bpm' => 120, 'instrument' => 'flute'],
        ['folder' => ['Spring concert'], 'file' => 'demo-school/spring/yankee-doodle-trumpet.musicxml', 'title' => 'Yankee Doodle (trumpet in B♭)', 'composer' => 'Traditional', 'bpm' => 120, 'instrument' => 'trumpet'],
        ['folder' => ['Spring concert'], 'file' => 'demo-school/spring/yankee-doodle-tenor-sax.musicxml', 'title' => 'Yankee Doodle (tenor saxophone)', 'composer' => 'Traditional', 'bpm' => 120, 'instrument' => 'tenor saxophone'],
    ];

    public function run(PieceService $service): void
    {
        $school = Organization::where('name', DatabaseSeeder::SCHOOL)->firstOrFail();
        $teacher = User::where('email', 'teacher@example.com')->first();

        $this->seedPieces($service, self::PIECES, $school->id, $teacher?->id, 'pieces/org-'.$school->id);
    }
}
