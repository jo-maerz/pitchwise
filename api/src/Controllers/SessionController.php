<?php

declare(strict_types=1);

namespace PracticeApi\Controllers;

use PDO;
use PracticeApi\ApiException;
use PracticeApi\Http\Request;
use PracticeApi\Http\Response;
use PracticeApi\PitchRule;
use Throwable;

final class SessionController
{
    public const MAX_RESULTS_PER_BATCH = 2000;

    private const INSERT_CHUNK = 400;

    public function __construct(private readonly PDO $pdo) {}

    public function store(int $userId, Request $request): Response
    {
        $in = $request->json();
        $errors = [];

        $pieceId = self::int($in, 'piece_id', $errors, 1, PHP_INT_MAX);
        $bpm = self::int($in, 'bpm', $errors, 20, 300);
        $mode = $in['tolerance_mode'] ?? 'cents';
        if (! in_array($mode, PitchRule::MODES, true)) {
            $errors['tolerance_mode'] = 'Must be "cents" or "hz".';
        }
        $tolerance = self::number($in, 'tolerance_value', $errors, PitchRule::TOLERANCE_MIN, PitchRule::TOLERANCE_MAX, 30.0);
        $reference = self::number($in, 'reference_hz', $errors, 400.0, 480.0, 440.0);
        $latency = self::int($in, 'latency_ms', $errors, -500, 1000, 0);
        if ($errors) {
            throw ApiException::validation($errors);
        }

        PieceController::findVisible($this->pdo, $userId, $pieceId);

        $now = gmdate('Y-m-d H:i:s');
        $this->pdo->prepare(
            'INSERT INTO practice_sessions
                (user_id, piece_id, bpm, tolerance_mode, tolerance_value, reference_hz, latency_ms, started_at, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([$userId, $pieceId, $bpm, $mode, $tolerance, $reference, $latency, $now, $now, $now]);

        return Response::json([
            'id' => (int) $this->pdo->lastInsertId(),
            'piece_id' => $pieceId,
            'bpm' => $bpm,
            'tolerance_mode' => $mode,
            'tolerance_value' => $tolerance,
            'reference_hz' => $reference,
            'latency_ms' => $latency,
            'started_at' => $now,
        ], 201);
    }

    public function storeResults(int $userId, int $sessionId, Request $request): Response
    {
        $in = $request->json();
        $results = $in['results'] ?? null;
        $finished = ($in['finished'] ?? false) === true;
        if (! is_array($results) || ! array_is_list($results)) {
            throw ApiException::validation(['results' => 'Must be a list.']);
        }
        if (count($results) > self::MAX_RESULTS_PER_BATCH) {
            throw ApiException::validation(['results' => 'At most '.self::MAX_RESULTS_PER_BATCH.' results per request.']);
        }
        if ($results === [] && ! $finished) {
            throw ApiException::validation(['results' => 'Send at least one result, or finished: true.']);
        }

        $session = $this->findSession($userId, $sessionId);
        if ($session['finished_at'] !== null) {
            throw new ApiException(409, 'already_finished', 'This run is already finished.');
        }

        $rows = $this->validateBatch($results, $session);

        // One transaction per batch. A savepoint when a transaction is already open (tests run inside one).
        $nested = $this->pdo->inTransaction();
        $nested ? $this->pdo->exec('SAVEPOINT results_batch') : $this->pdo->beginTransaction();
        try {
            $this->assertNotStored($sessionId, array_column($rows, 'note_index'));
            foreach (array_chunk($rows, self::INSERT_CHUNK) as $chunk) {
                $this->insertChunk($sessionId, $chunk);
            }
            $summary = $this->summary($sessionId);
            if ($finished) {
                $now = gmdate('Y-m-d H:i:s');
                $this->pdo->prepare(
                    'UPDATE practice_sessions SET finished_at = ?, score_pct = ?, updated_at = ? WHERE id = ? AND finished_at IS NULL'
                )->execute([$now, $summary['score_pct'], $now, $sessionId]);
            }
            $nested ? $this->pdo->exec('RELEASE SAVEPOINT results_batch') : $this->pdo->commit();
        } catch (Throwable $e) {
            $nested ? $this->pdo->exec('ROLLBACK TO SAVEPOINT results_batch') : $this->pdo->rollBack();
            if ($e instanceof \PDOException && in_array($e->getCode(), ['23000', '23505'], true)) {
                throw new ApiException(409, 'duplicate', 'Some of these notes were already stored for this run.');
            }
            throw $e;
        }

        return Response::json([
            'session_id' => $sessionId,
            'finished' => $finished,
            'stored' => count($rows),
            'score_pct' => $summary['score_pct'],
            'counts' => $summary['counts'],
            'results' => array_map(fn (array $r) => [
                'note_index' => $r['note_index'],
                'outcome' => $r['outcome'],
                'cents' => $r['cents_offset'],
                'detected_midi' => $r['detected_midi'],
            ], $rows),
        ], 201);
    }

    /** @return array<string, mixed> */
    private function findSession(int $userId, int $sessionId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT s.id, s.piece_id, s.tolerance_mode, s.tolerance_value, s.reference_hz, s.finished_at
               FROM practice_sessions s WHERE s.id = ? AND s.user_id = ?'
        );
        $stmt->execute([$sessionId, $userId]);
        $session = $stmt->fetch(PDO::FETCH_ASSOC);
        if (! $session) {
            throw ApiException::notFound('Session');
        }

        return $session;
    }

    private function validateBatch(array $results, array $session): array
    {
        $stmt = $this->pdo->prepare('SELECT note_index, midi_pitch FROM piece_notes WHERE piece_id = ?');
        $stmt->execute([(int) $session['piece_id']]);
        $expected = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $n) {
            $expected[(int) $n['note_index']] = (int) $n['midi_pitch'];
        }

        $errors = [];
        $seen = [];
        $rows = [];
        foreach ($results as $i => $r) {
            if (! is_array($r)) {
                $errors["results.$i"] = 'Must be an object.';

                continue;
            }
            $index = $r['note_index'] ?? null;
            $midi = $r['expected_midi'] ?? null;
            $hz = $r['detected_hz'] ?? null;
            $clarity = $r['clarity'] ?? null;

            if (! is_int($index) || ! array_key_exists($index, $expected)) {
                $errors["results.$i.note_index"] = 'Unknown note index for this piece.';

                continue;
            }
            if (isset($seen[$index])) {
                $errors["results.$i.note_index"] = 'Duplicate note index in this batch.';

                continue;
            }
            $seen[$index] = true;
            if ($midi !== $expected[$index]) {
                $errors["results.$i.expected_midi"] = "Expected pitch does not match the score (note {$index} is MIDI {$expected[$index]}).";
            }
            if ($hz !== null && (! is_int($hz) && ! is_float($hz) || $hz < 20 || $hz > 5000)) {
                $errors["results.$i.detected_hz"] = 'Must be null or a frequency between 20 and 5000 Hz.';
            }
            if ($clarity !== null && (! is_int($clarity) && ! is_float($clarity) || $clarity < 0 || $clarity > 1)) {
                $errors["results.$i.clarity"] = 'Must be null or between 0 and 1.';
            }
            if (isset($errors["results.$i.expected_midi"]) || isset($errors["results.$i.detected_hz"]) || isset($errors["results.$i.clarity"])) {
                continue;
            }

            $classified = PitchRule::classify(
                $expected[$index],
                $hz === null ? null : (float) $hz,
                $clarity === null ? null : (float) $clarity,
                (string) $session['tolerance_mode'],
                (float) $session['tolerance_value'],
                (float) $session['reference_hz'],
            );
            $rows[] = [
                'note_index' => $index,
                'expected_midi' => $expected[$index],
                'detected_midi' => $classified['detected_midi'],
                'detected_hz' => $classified['outcome'] === PitchRule::MISSED || $hz === null ? null : round((float) $hz, 2),
                'cents_offset' => $classified['cents'],
                'outcome' => $classified['outcome'],
                'clarity' => $clarity === null ? null : round((float) $clarity, 3),
            ];
        }

        if ($errors) {
            throw ApiException::validation($errors, 'Some results do not match this piece.');
        }

        return $rows;
    }

    private function assertNotStored(int $sessionId, array $indexes): void
    {
        if ($indexes === []) {
            return;
        }
        foreach (array_chunk($indexes, 500) as $chunk) {
            $marks = implode(',', array_fill(0, count($chunk), '?'));
            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM note_results WHERE session_id = ? AND note_index IN ($marks)");
            $stmt->execute([$sessionId, ...$chunk]);
            if ((int) $stmt->fetchColumn() > 0) {
                throw new ApiException(409, 'duplicate', 'Some of these notes were already stored for this run.');
            }
        }
    }

    private function insertChunk(int $sessionId, array $chunk): void
    {
        $columns = ['session_id', 'note_index', 'expected_midi', 'detected_midi', 'detected_hz', 'cents_offset', 'outcome', 'clarity'];
        $row = '('.implode(',', array_fill(0, count($columns), '?')).')';
        $sql = 'INSERT INTO note_results ('.implode(',', $columns).') VALUES '.implode(',', array_fill(0, count($chunk), $row));
        $params = [];
        foreach ($chunk as $r) {
            array_push($params, $sessionId, $r['note_index'], $r['expected_midi'], $r['detected_midi'],
                $r['detected_hz'], $r['cents_offset'], $r['outcome'], $r['clarity']);
        }
        $this->pdo->prepare($sql)->execute($params);
    }

    /** @return array{score_pct: float, counts: array<string, int>} */
    private function summary(int $sessionId): array
    {
        $stmt = $this->pdo->prepare('SELECT outcome, COUNT(*) AS n FROM note_results WHERE session_id = ? GROUP BY outcome');
        $stmt->execute([$sessionId]);
        $counts = array_fill_keys([PitchRule::IN_TUNE, PitchRule::SHARP, PitchRule::FLAT, PitchRule::WRONG_NOTE, PitchRule::MISSED], 0);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $counts[$row['outcome']] = (int) $row['n'];
        }
        $total = array_sum($counts);

        return [
            'score_pct' => $total === 0 ? 0.0 : round(100 * $counts[PitchRule::IN_TUNE] / $total, 2),
            'counts' => $counts,
        ];
    }

    private static function int(array $in, string $key, array &$errors, int $min, int $max, ?int $default = null): int
    {
        $value = $in[$key] ?? $default;
        if (! is_int($value) || $value < $min || $value > $max) {
            $errors[$key] = "Must be a whole number between {$min} and {$max}.";

            return 0;
        }

        return $value;
    }

    private static function number(array $in, string $key, array &$errors, float $min, float $max, float $default): float
    {
        $value = $in[$key] ?? $default;
        if ((! is_int($value) && ! is_float($value)) || $value < $min || $value > $max) {
            $errors[$key] = "Must be a number between {$min} and {$max}.";

            return 0.0;
        }

        return (float) $value;
    }
}
