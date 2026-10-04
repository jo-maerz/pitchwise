<?php

declare(strict_types=1);

namespace PracticeApi\Http;

use PracticeApi\ApiException;

final class Request
{
    public const MAX_BODY_BYTES = 1_048_576;

    /** @param array<string, string> $headers lower-cased names */
    public function __construct(
        public readonly string $method,
        public readonly string $path,
        public readonly array $headers = [],
        public readonly string $body = '',
    ) {}

    public static function fromGlobals(): self
    {
        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $headers[strtolower(str_replace('_', '-', substr($key, 5)))] = (string) $value;
            }
        }
        if (isset($_SERVER['CONTENT_TYPE'])) {
            $headers['content-type'] = (string) $_SERVER['CONTENT_TYPE'];
        }
        // Some servers (php -S, FPM without config) hide Authorization from HTTP_*.
        if (! isset($headers['authorization']) && function_exists('getallheaders')) {
            foreach (getallheaders() as $name => $value) {
                if (strtolower($name) === 'authorization') {
                    $headers['authorization'] = $value;
                }
            }
        }

        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        $body = (string) file_get_contents('php://input', false, null, 0, self::MAX_BODY_BYTES + 1);

        return new self(strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET'), $path, $headers, $body);
    }

    public function header(string $name): ?string
    {
        return $this->headers[strtolower($name)] ?? null;
    }

    /** @return array<string, mixed> */
    public function json(): array
    {
        if (strlen($this->body) > self::MAX_BODY_BYTES) {
            throw new ApiException(413, 'too_large', 'Request body is larger than 1 MB.');
        }
        if (trim($this->body) === '') {
            return [];
        }
        try {
            $data = json_decode($this->body, true, 32, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new ApiException(400, 'bad_json', 'Request body is not valid JSON.');
        }
        if (! is_array($data) || array_is_list($data) && $data !== []) {
            throw new ApiException(400, 'bad_json', 'Request body must be a JSON object.');
        }

        return $data;
    }
}
