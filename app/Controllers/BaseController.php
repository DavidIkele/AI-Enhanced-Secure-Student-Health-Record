<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\View;

/**
 * Base controller. Holds the request/response objects and rendering helpers.
 */
abstract class BaseController
{
    protected Request $request;
    protected Response $response;

    public function __construct(Request $request, Response $response)
    {
        $this->request = $request;
        $this->response = $response;
    }

    /**
     * Render a view (optionally within a layout).
     *
     * @param array<string, mixed> $data
     */
    protected function render(string $template, array $data = [], ?string $layout = 'main'): void
    {
        View::render($template, $data, $layout);
    }

    protected function renderJson(array $payload, int $status = 200): void
    {
        $this->response->json($payload, $status);
    }

    protected function redirect(string $path, int $status = 302): void
    {
        $this->response->redirect($path, $status);
    }

    protected function abort(int $status, string $message): void
    {
        $this->response->abort($status, $message);
    }
}