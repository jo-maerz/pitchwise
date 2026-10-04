<?php

declare(strict_types=1);

namespace PracticeApi\Http;

final class Response
{
    public function __construct(
        public readonly int $status,
        public readonly ?array $data = null,
        public array $headers = [],
    ) {}

    public static function json(array $data, int $status = 200): self
    {
        return new self($status, $data);
    }

    public static function empty(int $status = 204): self
    {
        return new self($status);
    }

    public function withHeaders(array $headers): self
    {
        $this->headers = $headers + $this->headers;

        return $this;
    }

    public function body(): string
    {
        return $this->data === null ? '' : (string) json_encode($this->data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);
    }

    public function send(): void
    {
        http_response_code($this->status);
        if ($this->data !== null) {
            header('Content-Type: application/json; charset=utf-8');
        }
        foreach ($this->headers as $name => $value) {
            header($name.': '.$value);
        }
        echo $this->body();
    }
}
