<?php

return [
    // Where the browser player reaches the plain-PHP API (see api/public/index.php).
    'api_url' => env('PRACTICE_API_URL', 'http://localhost:8001/api/v1'),

    // Lifetime of the token the player page gets for talking to the API.
    'player_token_minutes' => (int) env('PRACTICE_PLAYER_TOKEN_MINUTES', 180),

    // Default pitch rule. Players can change it on the player page; each run stores the rule it used.
    'tolerance' => [
        'mode' => env('PRACTICE_TOLERANCE_MODE', 'cents'),   // 'cents' or 'hz'
        'value' => (float) env('PRACTICE_TOLERANCE_VALUE', 30),
    ],

    'reference_hz' => (float) env('PRACTICE_REFERENCE_HZ', 440),

    // Upload limit for MusicXML files, in kilobytes.
    'max_upload_kb' => 4096,
];
