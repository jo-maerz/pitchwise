<?php

declare(strict_types=1);

namespace PracticeApi\Controllers;

use PDO;
use PracticeApi\ApiException;
use PracticeApi\Http\Response;

final class PieceController
{
    public function __construct(private readonly PDO $pdo) {}

    /** GET /api/v1/pieces/{id} — the expected notes the player times and scores against. */
    public function show(int $userId, int $pieceId): Response
    {
        $piece = self::findVisible($this->pdo, $userId, $pieceId);

        $stmt = $this->pdo->prepare(
            'SELECT note_index, measure, midi_pitch, onset_beats, duration_beats
               FROM piece_notes WHERE piece_id = ? ORDER BY note_index'
        );
        $stmt->execute([$pieceId]);
        $notes = array_map(fn (array $n) => [
            'note_index' => (int) $n['note_index'],
            'measure' => (int) $n['measure'],
            'midi_pitch' => (int) $n['midi_pitch'],
            'onset_beats' => (float) $n['onset_beats'],
            'duration_beats' => (float) $n['duration_beats'],
        ], $stmt->fetchAll(PDO::FETCH_ASSOC));

        return Response::json([
            'id' => (int) $piece['id'],
            'title' => $piece['title'],
            'composer' => $piece['composer'],
            'default_bpm' => (int) $piece['default_bpm'],
            'beats_per_measure' => (int) $piece['beats_per_measure'],
            'note_count' => (int) $piece['note_count'],
            'notes' => $notes,
        ])->withHeaders(['Cache-Control' => 'private, max-age=60']);
    }

    /** @return array<string, mixed> the piece row, if this user may play it */
    public static function findVisible(PDO $pdo, int $userId, int $pieceId): array
    {
        $stmt = $pdo->prepare(
            'SELECT id, owner_id, title, composer, default_bpm, beats_per_measure, note_count, parse_status
               FROM pieces WHERE id = ? AND (owner_id IS NULL OR owner_id = ?)'
        );
        $stmt->execute([$pieceId, $userId]);
        $piece = $stmt->fetch(PDO::FETCH_ASSOC);
        if (! $piece) {
            throw ApiException::notFound('Piece');
        }
        if ($piece['parse_status'] !== 'ready') {
            throw new ApiException(409, 'not_ready', 'This piece has not been analysed yet.');
        }

        return $piece;
    }
}
