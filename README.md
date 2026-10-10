# Pitchwise — developer README

A tuner and a score follower in one. The browser shows a MusicXML score, moves a cursor at the chosen tempo, listens through the microphone and shows **live** whether the current note is in tune, too high or too low. Each note gets an outcome, each page a summary, and each run a stored report. A dashboard tracks progress and per-note intonation.

It copies Tomplay's shape on a small scale: a **Laravel** website and a **plain-PHP API** sharing one **MySQL** database; real-time audio stays in the browser.

> For musicians who just want to use it: see [README-for-musicians.md](README-for-musicians.md).

## Architecture

```mermaid
flowchart LR
    subgraph Browser
        P[Player page<br/>OSMD score · Web Audio · pitchy]
    end
    subgraph Laravel["Laravel 13 website (:8000)"]
        W[Login, uploads, player page,<br/>reports, dashboard]
        J[Queue: ParseMusicXml job]
        S[Scheduler: practice:aggregate-stats]
    end
    A["Plain-PHP API (:8001)<br/>api/public/index.php"]
    D[(MySQL<br/>one schema)]
    P -- "HTML, MusicXML file,<br/>Sanctum token" --- W
    P -- "JSON: GET piece notes,<br/>POST session, POST results" --> A
    W --- D
    J --- D
    S --- D
    A -- "PDO, prepared statements,<br/>token check in personal_access_tokens" --- D
```

- **Laravel owns the schema** (migrations) and issues a short-lived Sanctum token (ability `practice:write`, 3 h) when the player page loads.
- **The API has no framework.** Composer autoload, PDO, three routes. It validates the Sanctum token itself by reading `personal_access_tokens` (`"42|secret"` → row 42, `sha256(secret)`), checks every submitted `expected_midi` against `piece_notes`, **re-judges every note on the server** from the heard frequency, and inserts each batch in one transaction.
- **Audio never leaves the browser.** Only one frequency + clarity per note is sent.

| Layer | Where |
|---|---|
| Controllers → Services → Repositories → Eloquent | `app/Http/Controllers`, `app/Services`, `app/Repositories`, `app/Models` |
| Validation / access | `app/Http/Requests/StorePieceRequest.php`, `app/Policies/*` |
| MusicXML → expected notes (XMLReader, streaming) | `app/Services/MusicXml/MusicXmlParser.php`, queued by `app/Jobs/ParseMusicXml.php` |
| PDF → MusicXML (Audiveris, optical music recognition) | `app/Jobs/ConvertPdfScore.php`, `app/Services/Omr/OmrSpool.php` (file hand-over), `app/Services/Omr/ScoreSanity.php`, worker `docker/omr/watch.sh` |
| Per-pitch statistics | `app/Services/PitchStatsAggregator.php`, command `practice:aggregate-stats`, scheduled in `routes/console.php` |
| Plain-PHP API | `api/public/index.php` (front controller), `api/src/*` (namespace `PracticeApi\`) |
| Pitch rule (PHP) | `api/src/PitchRule.php` |
| Browser player | `resources/js/player.js` + `resources/js/practice/*` |
| Pitch rule (JS) | `resources/js/practice/pitch-math.js` — same rule as `PitchRule.php`, both tested against `tests/fixtures/pitch-rule-cases.json` |

Stack as built: PHP 8.3, Laravel 13.34, Breeze 2 (Blade), Sanctum 4, PHPUnit 12, OpenSheetMusicDisplay 2.2, pitchy 4.1, Chart.js 4.5, Vite 8, Tailwind 3.

## Run it locally

Requirements: PHP ≥ 8.3 with `pdo_mysql`, `xmlreader`, `zip`; Composer; Node 20+; MySQL 8.

```bash
composer install            # no composer.lock is shipped (see below); this resolves and writes one
cp .env.example .env
php artisan key:generate
```

Database — MySQL in `.env`:

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=pitchwise
DB_USERNAME=root
DB_PASSWORD=
```

Both the website and the API read the same `.env`.

```bash
php artisan migrate --seed  # demo user demo@example.com / password, 3 catalogue pieces, 14 fake runs
npm install
npm run build               # or `npm run dev` while working on the JS
```

Then four processes (four terminals, or Laravel Sail / Herd if you prefer):

```bash
php artisan serve                        # website   http://localhost:8000
composer serve-api                       # API       http://127.0.0.1:8001/api/v1
php artisan queue:work                   # parses uploaded MusicXML, hands PDFs to Audiveris (see PDF scores)
php artisan schedule:work                # rolls finished runs into the dashboard stats every 5 min
```

Open http://localhost:8000, log in as the demo user, **Pieces → ▶ Practise**. The browser asks for the microphone; that only works on `https://` or `localhost`.

Relevant `.env` keys (all in `.env.example`):

| Key | Default | Meaning |
|---|---|---|
| `PRACTICE_API_URL` | `http://localhost:8001/api/v1` | where the browser calls the API |
| `PRACTICE_WEB_ORIGINS` | `APP_URL` | origins allowed by the API's CORS check, comma-separated |
| `PRACTICE_TOLERANCE_MODE` | `cents` | default pitch rule, `cents` or `hz` |
| `PRACTICE_TOLERANCE_VALUE` | `30` | its width |
| `PRACTICE_REFERENCE_HZ` | `440` | default concert A |

In production, serve the API from the same host under `/api/v1` (an nginx `location /api/v1` pointing at `api/public/index.php`), set `PRACTICE_API_URL=/api/v1`, and CORS stops mattering.

## Docker (one command, HTTPS included)

Replaces the four terminals. Five containers: `web` (Caddy: HTTPS, static files, routes `/` to Laravel and `/api/v1` to the plain-PHP API), `app` (php-fpm, runs migrations on start), `queue`, `scheduler` and `db` (MySQL).

```bash
cp .env.docker.example .env.docker
# set APP_KEY (see the comment in the file), DB_PASSWORD and MYSQL_ROOT_PASSWORD
docker compose up -d --build
docker compose exec app su-exec www-data php artisan db:seed --force   # optional demo data
```

Open https://localhost. Caddy signs `localhost` with its own CA, so the browser warns until you trust it:

```bash
docker compose cp web:/data/caddy/pki/authorities/local/root.crt ./caddy-root.crt   # then add it to your keychain
```

**Real domain:** set `SITE_ADDRESS=app.example.com` and `APP_URL=https://app.example.com` in `.env.docker`, point DNS at the host, open ports 80 and 443. Caddy gets and renews a Let's Encrypt certificate itself. Keep the `caddy_data` volume.

Data lives in the volumes `dbdata` (MySQL) and `storage` (uploaded scores). Back both up. `docker compose down` keeps them; `down -v` deletes them.

### PDF scores (Audiveris)

A PDF is a picture, so it is turned into MusicXML first by [Audiveris](https://github.com/Audiveris/audiveris) (open-source optical music recognition). Flow: upload → `ConvertPdfScore` job copies the PDF to `<spool>/in/<id>.pdf` → the Audiveris worker (`docker/omr/watch.sh`) writes `<spool>/out/<id>.mxl` or `<id>.failed` → the job stores the MusicXML and parses it as usual → status `needs_review`. The job does not block the queue worker: it re-checks every 5 s (`practice.omr.wait_minutes`, default 20, then the piece is marked failed).

Recognition is a guess, so a PDF-sourced piece **cannot be practised until its owner confirms it**. The piece page shows the recognised score (OSMD) and warnings from `ScoreSanity` (bars holding more beats than the time signature allows); the owner confirms, or uploads corrected MusicXML instead.

- **Docker:** the `omr` service does all of this. It is `linux/amd64` only (Audiveris ships an Ubuntu x86-64 package with its own Java), so on Apple Silicon it runs under emulation: slower, still works. Image ≈ 1 GB.
- **Without Docker:** install Audiveris, then `AUDIVERIS=audiveris OMR_SPOOL=storage/app/private/omr sh docker/omr/watch.sh` next to `queue:work`.
- **Limits:** PDFs up to 20 MB; Audiveris refuses pages over 20 megapixels (use A4/Letter pages). Works on clean printed parts; handwriting, skewed photos and dense piano scores come out poorly. Only the first movement is read.
- **Verified:** a PDF engraved from the demo "Twinkle" score came back with all 42 notes, pitches and durations identical, through the real container. Not verified: scans, multi-page PDFs, the in-browser review preview (OSMD) and the full `docker compose up` stack.

### MusicXML + PDF, and PDF-only practice

A piece can hold both files. The MusicXML gives the full player (note map, outcomes, reports); the original PDF is kept next to it. Upload both on **Upload a score** (`score` = MusicXML or PDF, optional `pdf` = original PDF), or add either later with **Edit piece**. With MusicXML present the PDF is never recognised. A PDF is only recognised when it is the only file.

| Piece state (`parse_status`) | Full player (`play`) | PDF page (`playPdf`, `/pieces/{id}/play-pdf`) |
|---|---|---|
| `ready` | yes | yes, if it has a PDF |
| `needs_review` (recognised, unconfirmed) | no | yes |
| `pdf_only` (owner dropped the recognised score, or chose the PDF) | no | yes |
| `failed` with a PDF | no | no until the owner picks "Use the PDF with the tuner" (`POST /pieces/{id}/use-pdf`, also offered from `needs_review`) |

The PDF page (`resources/views/player/pdf.blade.php`, `resources/js/pdf-player.js`, pdf.js draws the pages) has no notes to follow, so the target is **the nearest note** to what you play, as on the standalone tuner. `practice/free-play.js` (`NoteSegmenter`, tested in `tests/js/free-play.test.mjs`) cuts the pitch frames into played notes and the page lists each with its offset in cents. A different pitch must last 4 frames to count as a new note, silence ends a note after 100 ms, sounds under 90 ms are dropped. The page and the piece page say that results may be restricted and inaccurate (a wrong note is judged against its neighbour). **Nothing from this mode is saved**: no session, no report, no dashboard stats, because the API only accepts notes that exist in `piece_notes`. Saving nearest-note results as pitch stats would be a separate feature.

**Verified:** PDF page drawn in a browser (2-page PDF), and a synthetic tone fed through the whole chain (microphone → pitchy → nearest note → log): +40 cents on A4 shown as "Too high", −15 on B4 as "In tune", −35 on C5 as "Too low". Not verified with a real microphone or instrument, and not on a phone.

### Why there is no composer.lock

This first version was built in a sandbox without Packagist or npm access; PHP packages were mirrored from GitHub tags. That lock file pointed at local paths, so it was removed. Run `composer install` once and commit the `composer.lock` it writes; same for `package-lock.json` after `npm install`.

## The pitch rule

```
expected Hz = A · 2^((midi − 69) / 12)        A = concert pitch, 440 by default
cents       = 1200 · log2(heard Hz / expected Hz)
```

| Outcome | Rule |
|---|---|
| `in_tune` | within the tolerance: **±30 cents** by default; a setting, in cents or in Hz |
| `sharp` / `flat` | outside the tolerance, up to ±50 cents |
| `wrong_note` | more than 50 cents away (closer to a neighbouring note) |
| `missed` | fewer than 2 frames with clarity ≥ 0.9 in the note's listening window |

The in-tune check runs first. In **Hz mode** that means a fixed window: ±30 Hz is −288/+247 cents on the open G (G3, 196 Hz) but −40/+39 cents at E6 (1319 Hz), so on low strings a neighbouring note counts as in tune. That is why cents is the default; the player says so next to the setting, and each run stores the rule it used (`practice_sessions.tolerance_mode`, `tolerance_value`, `reference_hz`) so old runs keep their meaning.

The playbook first had the wrong-note boundary at 100 cents. A clean semitone slip (B♭ for A, exactly 100 cents) then came out as "too high", so it is 50 here. Change `WRONG_NOTE_CENTS` in both `PitchRule.php` and `pitch-math.js` together; the shared fixture test will tell you if they disagree.

## How a run works (browser)

1. `GET /api/v1/pieces/{id}` returns the expected notes (server-parsed: onset and duration in quarter-note beats). OSMD renders the same MusicXML and `ScoreView.buildNoteMap()` walks its cursor once to find the matching noteheads and their page. If the two counts differ the player warns; scoring is unaffected, only colouring.
2. **Start** opens the microphone with echo cancellation, noise suppression and auto gain **off** (they bend pitch), posts `POST /sessions`, and schedules a one-bar count-in as clicks on the `AudioContext` clock.
3. Every animation frame: read 2048 samples from an `AnalyserNode`, run pitchy (McLeod pitch method) → `{hz, clarity}`.
   - The cursor follows the **written** time (`timeline.js`).
   - Each note is listened to in the middle 60 % of its length, shifted by the *input delay* setting (default 80 ms).
   - The dial compares the median of the last 3 clear frames to the note being heard now.
4. When a note's window closes: median of its clear frames → `classifyNote()` → notehead coloured.
5. At the end of each page, and at the end: `POST /sessions/{id}/results` with that batch (`finished: true` on the last). The server's outcomes replace the browser's if they ever differ. The end-of-run panel shows the score, per-page table, bars to practise and the notes furthest off; the saved report is `/sessions/{id}`.

### Wait-for-me mode

`resources/js/practice/wait-mode.js` (`WaitFollower`, pure logic, tested in `tests/js/wait-mode.test.mjs`) replaces the tempo clock: the cursor sits on a note until frames within ±50 cents of it have been heard for 150 ms (dropouts under 60 ms are tolerated; a repeated pitch needs 80 ms of silence first). The accepted frames go through the same `finalize()` → `classifyNote()` → batch-upload path as a timed run, so outcomes, reports and server re-judging are unchanged. Skip records the note as missed. Runs still store `bpm` and `latency_ms` (the page's values, unused in this mode); the run does not record which mode was used. After a note is accepted the follower is `lingering`: the old pitch is ignored (and the dial stays quiet) until a different pitch is heard, the sound stops for 80 ms, or the loudness dips and rises by 3 dB (a new attack). That is how repeated notes advance without a pause, and why `audio.js` now reports a `level` per frame.

## API reference

All routes need `Authorization: Bearer <id>|<token>` with ability `practice:write`. POSTs need `Content-Type: application/json`. Errors are `{"error": {"code", "message", "details"?}}`.

| Method + path | Body | Success | Notable errors |
|---|---|---|---|
| `GET /api/v1/pieces/{id}` | – | `200` piece + `notes[]` (`note_index`, `measure`, `midi_pitch`, `onset_beats`, `duration_beats`) | `404` not yours / not catalogue, `409 not_ready` |
| `POST /api/v1/sessions` | `piece_id`, `bpm` (20–300), `tolerance_mode` (`cents`\|`hz`), `tolerance_value` (1–100), `reference_hz` (400–480), `latency_ms` | `201` session | `422 validation` |
| `POST /api/v1/sessions/{id}/results` | `results[]` of `{note_index, expected_midi, detected_hz\|null, clarity\|null}` (≤ 2000), `finished` | `201` server outcomes, running `counts`, `score_pct` | `422` expected pitch ≠ score (whole batch rejected), `409 duplicate`, `409 already_finished`, `404` someone else's run |

Example:

```bash
curl -s -H "Authorization: Bearer $TOKEN" -H "Content-Type: application/json" \
  -d '{"results":[{"note_index":0,"expected_midi":55,"detected_hz":196.4,"clarity":0.97}],"finished":true}' \
  http://127.0.0.1:8001/api/v1/sessions/15/results
```

Get a token for manual testing with `php artisan tinker --execute 'echo app(App\Services\PlayerTokenService::class)->issue(App\Models\User::first());'`.

## Data model

| Table | Key columns | Notes |
|---|---|---|
| `pieces` | id, owner_id (NULL = catalogue), title, composer, instrument, musicxml_path, default_bpm, beats_per_measure, note_count, parse_status | `parse_status`: pending → ready / failed |
| `piece_notes` | piece_id, note_index, measure, midi_pitch, onset_beats, duration_beats | unique (piece_id, note_index); written by the parse job |
| `practice_sessions` | user_id, piece_id, bpm, tolerance_mode, tolerance_value, reference_hz, latency_ms, started_at, finished_at, score_pct, aggregated_at | index (user_id, piece_id, finished_at), index (user_id, finished_at), index (finished_at, aggregated_at) |
| `note_results` | session_id, note_index, expected_midi, detected_midi, detected_hz, cents_offset, outcome ENUM, clarity | unique (session_id, note_index) |
| `user_pitch_stats` | user_id, midi_pitch, attempts, in_tune, avg_cents | unique (user_id, midi_pitch); rebuilt per user by the aggregator (idempotent) |

Additions to the playbook's first data model: `onset_beats` (the browser needs note positions, and rests take time), the per-run pitch rule columns, `detected_hz` (so the server can judge), `beats_per_measure` (count-in), `parse_status`, and nullable `owner_id` for the shared catalogue.

Which notes count — the parser and the browser's note map use the same rules: first part, lowest voice, pitched notes, first written note of a chord, no grace or cue notes, tied continuations merged, repeats and numbered endings expanded, plus D.C./D.S./Fine/coda jumps, beats = quarter notes.

## Tests

```bash
php artisan test     # 54 tests: parser edge cases, pitch-rule fixture, uploads + policies, player token,
                     # report, aggregation, dashboard, and the plain-PHP API in-process on Laravel's DB connection
npm test             # 14 tests: pitch-rule fixture (same file as PHP), timeline, report,
                     # and pitchy on synthetic bowed tones with vibrato (needs npm install)
vendor/bin/pint      # code style
```

The API tests build `PracticeApi\App` with `DB::connection()->getPdo()` and Sanctum tokens issued by Laravel, so they exercise exactly the two-systems-one-database contract.

## The EXPLAIN exercise (milestone 3)

```bash
php artisan db:seed --class=LoadTestSeeder                    # ~30,000 runs ≈ 1 million note_results
LOADTEST_SESSIONS=3000 php artisan db:seed --class=LoadTestSeeder   # smaller
php artisan practice:aggregate-stats
```

Run it on MySQL. Then `EXPLAIN` (and `EXPLAIN ANALYZE`) the queries in `app/Repositories/PracticeStatsRepository.php` and `PitchStatsAggregator::recomputeUser()`, drop and re-add the indexes in the migrations, and record before/after here:

| Query | Before: type / key / rows / Extra | After | Index that helped |
|---|---|---|---|
| score history | | | |
| trouble bars | | | |
| recompute user stats | | | |

## Known limits

- One melody line: chords, double stops and piano are out of scope. Transposing instruments and tempo changes inside a piece are not handled; pick simple pieces.
- Pitch only, not rhythm: the cursor sets the time and notes are judged in their window.
- If the browser tab is hidden, animation frames stop and notes come out as missed (the player warns).
- `autoResize` is off on the score so colours survive a run; switching layout re-renders.

## Built with AI: what to check yourself

This version was written with Claude in one session. Verified there: all PHP and JS tests above pass; the API was exercised with `curl` against a running `php -S` server; pitchy was checked on synthetic tones (open G in tune, A 40 cents sharp, E5 35 cents flat, a semitone slip, high A6, silence).

**Not verified** (no browser or microphone in that environment): the OSMD rendering, cursor movement and notehead colouring, the microphone flow, and the Vite/Tailwind build. Check these first:

1. `npm run build` passes.
2. The player loads a catalogue piece without the "melody notes" count warning.
3. On the tuner page, an open A reads about 440 Hz (or your concert A) and the needle is steady.
4. Play the open-strings exercise slowly; noteheads turn colour as you go. If outcomes seem one note late or early, adjust *Input delay*.

| What I checked by hand | Result |
|---|---|
| | |
