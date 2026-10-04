<?php

namespace App\Services;

use App\Models\User;

/**
 * Issues the short-lived Sanctum token the player page uses to call the plain-PHP API.
 * The API validates it by reading personal_access_tokens directly (see api/src/TokenGuard.php).
 */
class PlayerTokenService
{
    public const NAME = 'player';

    public const ABILITY = 'practice:write';

    public function issue(User $user): string
    {
        // Housekeeping: drop this user's expired player tokens.
        $user->tokens()->where('name', self::NAME)->where('expires_at', '<', now())->delete();

        return $user->createToken(
            self::NAME,
            [self::ABILITY],
            now()->addMinutes(config('practice.player_token_minutes')),
        )->plainTextToken;
    }
}
