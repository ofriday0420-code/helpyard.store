<?php

namespace Helpyard\App\Core;

class Request
{
    public function __construct(
        private array $server,
        private array $query = [],
        private array $body = []
    ) {
    }

    public static function fromGlobals(): self
    {
        return new self($_SERVER, $_GET, $_POST);
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
}
