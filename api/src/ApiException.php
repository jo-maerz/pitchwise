<?php

declare(strict_types=1);

namespace PracticeApi;

use RuntimeException;

/** An error the client caused or should know about; rendered as JSON with its status code. */
final class ApiException extends RuntimeException
{
    /** @param array<string, mixed> $details */
    public function __construct(
        public readonly int $status,
        public readonly string $errorCode,
        string $message,
        public readonly array $details = [],
    ) {
        parent::__construct($message);
    }

    public static function validation(array $details, string $message = 'The request data is invalid.'): self
    {
        return new self(422, 'validation', $message, $details);
    }

    public static function notFound(string $what): self
    {
        return new self(404, 'not_found', "{$what} not found.");
    }
}
