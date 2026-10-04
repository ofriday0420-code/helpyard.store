<?php

namespace Helpyard\App\Core;

class Response
{
    public function __construct(
        private int $status = 200,
        private array $headers = [],
        private mixed $body = null
    ) {
    }

    public function status(): int
    {
        return $this->status;
    }

    public function send(): void
    {
        http_response_code($this->status);

        foreach ($this->headers as $name => $value) {
            header($name . ': ' . $value);
        }

        if (is_array($this->body) || is_object($this->body)) {
            header('Content-Type: application/json; charset=UTF-8');
            echo json_encode($this->body, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            return;
        }

        echo (string) $this->body;
    }
}
