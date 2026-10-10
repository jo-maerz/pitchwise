<?php

declare(strict_types=1);

namespace PracticeApi;

use PDO;
use PracticeApi\Controllers\PieceController;
use PracticeApi\Controllers\SessionController;
use PracticeApi\Http\Request;
use PracticeApi\Http\Response;
use Throwable;

final class App
{
    private const PREFIX = '/api/v1';

    public function __construct(
        private readonly PDO $pdo,
        private readonly array $allowedOrigins = [],
        private readonly bool $debug = false,
    ) {}

    public function handle(Request $request): Response
    {
        $response = $request->method === 'OPTIONS'
            ? Response::empty(204)
            : $this->dispatch($request);

        return $response->withHeaders($this->corsHeaders($request) + [
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => $response->headers['Cache-Control'] ?? 'no-store',
        ]);
    }

    private function dispatch(Request $request): Response
    {
        try {
            $path = rtrim($request->path, '/');
            if (! str_starts_with($path, self::PREFIX)) {
                throw ApiException::notFound('Route');
            }
            $route = substr($path, strlen(self::PREFIX));
            $guard = new TokenGuard($this->pdo);

            if (preg_match('#^/pieces/(\d+)$#', $route, $m)) {
                $this->allow($request, 'GET');

                return (new PieceController($this->pdo))->show($guard->authenticate($request->header('authorization'), 'practice:write'), (int) $m[1]);
            }
            if ($route === '/sessions') {
                $this->allow($request, 'POST');

                return (new SessionController($this->pdo))->store($guard->authenticate($request->header('authorization'), 'practice:write'), $request);
            }
            if (preg_match('#^/sessions/(\d+)/results$#', $route, $m)) {
                $this->allow($request, 'POST');

                return (new SessionController($this->pdo))->storeResults($guard->authenticate($request->header('authorization'), 'practice:write'), (int) $m[1], $request);
            }

            throw ApiException::notFound('Route');
        } catch (ApiException $e) {
            $error = ['code' => $e->errorCode, 'message' => $e->getMessage()];
            if ($e->details) {
                $error['details'] = $e->details;
            }

            return Response::json(['error' => $error], $e->status);
        } catch (Throwable $e) {
            error_log('[practice-api] '.$e::class.': '.$e->getMessage().' @ '.$e->getFile().':'.$e->getLine());
            $error = ['code' => 'server_error', 'message' => 'Something went wrong on the server.'];
            if ($this->debug) {
                $error['debug'] = $e->getMessage();
            }

            return Response::json(['error' => $error], 500);
        }
    }

    private function allow(Request $request, string $method): void
    {
        if ($request->method !== $method) {
            throw new ApiException(405, 'method_not_allowed', "Use {$method} for this route.");
        }
        if ($method === 'POST' && ! str_contains((string) $request->header('content-type'), 'application/json')) {
            throw new ApiException(415, 'unsupported_media_type', 'Send JSON with Content-Type: application/json.');
        }
    }

    private function corsHeaders(Request $request): array
    {
        $origin = $request->header('origin');
        if (! $origin || ! in_array(rtrim($origin, '/'), $this->allowedOrigins, true)) {
            return ['Vary' => 'Origin'];
        }

        return [
            'Access-Control-Allow-Origin' => $origin,
            'Access-Control-Allow-Headers' => 'Authorization, Content-Type',
            'Access-Control-Allow-Methods' => 'GET, POST, OPTIONS',
            'Access-Control-Max-Age' => '600',
            'Vary' => 'Origin',
        ];
    }
}
