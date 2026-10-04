<?php

declare(strict_types=1);

use PracticeApi\App;
use PracticeApi\Database;
use PracticeApi\Env;
use PracticeApi\Http\Request;
use PracticeApi\Http\Response;

$basePath = dirname(__DIR__, 2);
require $basePath.'/vendor/autoload.php';

$env = new Env($basePath.'/.env');
$debug = filter_var($env->get('APP_DEBUG', 'false'), FILTER_VALIDATE_BOOL);
$origins = array_filter(array_map(
    fn ($o) => rtrim(trim($o), '/'),
    explode(',', (string) $env->get('PRACTICE_WEB_ORIGINS', (string) $env->get('APP_URL', 'http://localhost:8000'))),
));

try {
    $pdo = Database::connect($env, $basePath);
} catch (Throwable $e) {
    error_log('[practice-api] database connection failed: '.$e->getMessage());
    Response::json(['error' => ['code' => 'unavailable', 'message' => 'Database unavailable.']], 503)->send();

    return;
}

(new App($pdo, array_values($origins), $debug))->handle(Request::fromGlobals())->send();
