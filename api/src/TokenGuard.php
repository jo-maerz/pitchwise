<?php

declare(strict_types=1);

namespace PracticeApi;

use PDO;

/**
 * Validates a Laravel Sanctum personal access token without Laravel:
 * the token "42|abc…" is row 42 in personal_access_tokens, whose `token`
 * column holds sha256("abc…"). Laravel issues it, this API only reads it.
 */
final class TokenGuard
{
    public const USER_TYPE = 'App\\Models\\User';

    public function __construct(private readonly PDO $pdo) {}

    /** @return int the authenticated user id */
    public function authenticate(?string $authorization, string $ability): int
    {
        if (! $authorization || ! preg_match('/^Bearer\s+(\d+)\|(\S+)$/i', $authorization, $m)) {
            throw new ApiException(401, 'unauthenticated', 'A bearer token is required.');
        }
        [, $id, $plain] = $m;

        $stmt = $this->pdo->prepare(
            'SELECT tokenable_type, tokenable_id, token, abilities, expires_at
               FROM personal_access_tokens WHERE id = ?'
        );
        $stmt->execute([(int) $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (! $row || ! hash_equals((string) $row['token'], hash('sha256', $plain))) {
            throw new ApiException(401, 'unauthenticated', 'The token is not valid.');
        }
        if ($row['tokenable_type'] !== self::USER_TYPE) {
            throw new ApiException(401, 'unauthenticated', 'The token does not belong to a user.');
        }
        if ($row['expires_at'] !== null && strtotime($row['expires_at'].' UTC') <= time()) {
            throw new ApiException(401, 'token_expired', 'The token has expired. Reload the player page.');
        }
        $abilities = json_decode((string) $row['abilities'], true) ?: [];
        if (! in_array('*', $abilities, true) && ! in_array($ability, $abilities, true)) {
            throw new ApiException(403, 'forbidden', 'The token may not do this.');
        }

        $this->pdo->prepare('UPDATE personal_access_tokens SET last_used_at = ? WHERE id = ?')
            ->execute([gmdate('Y-m-d H:i:s'), (int) $id]);

        return (int) $row['tokenable_id'];
    }
}
