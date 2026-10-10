<?php

namespace App\Services;

use App\Models\User;

/**
 * Issues the short-lived Sanctum token the player page uses to call the practice API (routes/api.php).
 */
class PlayerTokenService
{
    public const NAME = 'player';

    public const ABILITY = 'practice:write';

    public function issue(User $user): string
    {
        $user->tokens()->where('name', self::NAME)->where('expires_at', '<', now())->delete();

        return $user->createToken(
            self::NAME,
            [self::ABILITY],
            now()->addMinutes(config('practice.player_token_minutes')),
        )->plainTextToken;
    }
}
