<?php

namespace Helpyard\App\Controllers;

use Helpyard\App\Core\Response;

class HomeController
{
    public function index(array $params = []): Response
    {
        $html = <<<'HTML'
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Helpyard.store</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; background: #f5f7fb; color: #1f2937; }
        .wrap { max-width: 1040px; margin: 0 auto; padding: 64px 20px 100px; }
        .card { background: white; border-radius: 14px; box-shadow: 0 12px 30px rgba(15, 23, 42, 0.08); padding: 32px 28px; }
        h1 { margin-top: 0; font-size: 2.4rem; }
        .badge { display: inline-block; background: #dbeafe; color: #1d4ed8; padding: 6px 10px; border-radius: 999px; font-size: 0.8rem; font-weight: 700; letter-spacing: 0.04em; text-transform: uppercase; }
        ul { line-height: 1.8; }
        code { background: #eff6ff; padding: 3px 8px; border-radius: 8px; }
    </style>
</head>
<body>
    <div class="wrap">
        <div class="card">
            <div class="badge">Foundation</div>
            <h1>Helpyard.store</h1>
            <p>This homepage confirms the project foundation for the shared commerce platform described in the implementation plan.</p>
            <ul>
                <li>Shared product catalog across course, software, books, websites, and hardware sections</li>
                <li>Server-side pricing, order state, and payment validation</li>
                <li>Protected digital downloads and secure admin workflows</li>
                <li>REST API first, then PWA and native mobile delivery</li>
            </ul>
            <p>Try the product API at <code>/api/v1/products</code>.</p>
        </div>
    </div>
</body>
</html>
HTML;

        return new Response(200, ['Content-Type' => 'text/html; charset=UTF-8'], $html);
    }
}
