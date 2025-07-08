<?php

declare(strict_types=1);

namespace Framework\Routing;

use Framework\Http\Request;
use Framework\Http\Response;

/**
 * Middleware Interface für Request/Response Processing
 */
interface MiddlewareInterface
{
    /**
     * Verarbeitet Request und delegiert an nächste Middleware
     *
     * @param Request $request HTTP Request
     * @param callable $next Nächste Middleware in der Chain
     * @return Response HTTP Response
     */
    public function handle(Request $request, callable $next): Response;
}