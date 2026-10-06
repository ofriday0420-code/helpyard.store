<?php

namespace Helpyard\App\Core;

class Request
{
    public function __construct(
        private array $server,
        private array $query = [],
        private array $body = [],
        private array $files = [],
        private bool $jsonBodyValid = true
    ) {
    }

    public static function fromGlobals(): self
    {
        $server = $_SERVER;
        $body = $_POST;
        $jsonBodyValid = true;
        $contentType = strtolower(trim(explode(';', (string) ($server['CONTENT_TYPE'] ?? ''))[0]));
        if ($contentType === 'application/json') {
            $rawBody = file_get_contents('php://input');
            if (!is_string($rawBody) || strlen($rawBody) > 1_048_576) {
                $body = [];
                $jsonBodyValid = false;
            } else {
                try {
                    $decoded = json_decode($rawBody, true, 64, JSON_THROW_ON_ERROR);
                    if (!is_array($decoded) || !str_starts_with(ltrim($rawBody), '{')) {
                        $body = [];
                        $jsonBodyValid = false;
                    } else {
                        $body = $decoded;
                    }
                } catch (\JsonException) {
                    $body = [];
                    $jsonBodyValid = false;
                }
            }
        }

        return new self($server, $_GET, $body, $_FILES, $jsonBodyValid);
    }

    public function method(): string
    {
        return strtoupper($this->server['REQUEST_METHOD'] ?? 'GET');
    }

    public function path(): string
    {
        $uri = $this->server['REQUEST_URI'] ?? '/';

        return parse_url($uri, PHP_URL_PATH) ?: '/';
    }

    public function query(): array
    {
        return $this->query;
    }

    public function body(): array
    {
        return $this->body;
    }

    public function files(): array
    {
        return $this->files;
    }

    public function header(string $name): ?string
    {
        $serverKey = match (strtolower($name)) {
            'authorization' => 'HTTP_AUTHORIZATION',
            'content-type' => 'CONTENT_TYPE',
            'accept' => 'HTTP_ACCEPT',
            default => 'HTTP_' . strtoupper(str_replace('-', '_', $name)),
        };
        $value = $this->server[$serverKey]
            ?? ($name === 'Authorization' ? ($this->server['REDIRECT_HTTP_AUTHORIZATION'] ?? null) : null);

        return is_string($value) ? $value : null;
    }

    public function hasJsonContentType(): bool
    {
        return strtolower(trim(explode(';', (string) $this->header('Content-Type'))[0])) === 'application/json';
    }

    public function jsonBodyValid(): bool
    {
        return $this->jsonBodyValid;
    }

    public function remoteAddress(): string
    {
        $address = $this->server['REMOTE_ADDR'] ?? '';

        return is_string($address) ? $address : '';
    }
}
