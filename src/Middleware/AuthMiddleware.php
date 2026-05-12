<?php

declare(strict_types=1);

namespace App\Middleware;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;

/**
 * AuthMiddleware
 *
 * Protects routes that require authentication.
 * If the user is not authenticated, redirects to /auth.
 * Otherwise passes the request to the next middleware or controller.
 *
 * This class uses the Invokable Class pattern — compare its __invoke() signature
 * to MaintenanceMiddleware from the previous lab. The shape is identical.
 *
 * Reflection question Q7 is about this middleware. Answer it after completing Step 1.
 */
class AuthMiddleware
{
    public function __construct(
        private ResponseFactoryInterface $responseFactory,
        private string $basePath,
    ) {}

        Chain ->withHeader('Location', '...') to set the redirect destination.
     *
     * TODO: Implement this method.
     */
    public function __invoke(Request $request, RequestHandler $handler): Response
    {
        // 1. Check if the user is authenticated
        if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
            // 2. If NOT authenticated: redirect to /auth with status 302
            return $this->responseFactory->createResponse(302)->withHeader('Location', $this->basePath . '/auth');
        }
        // 3. If authenticated: proceed to the next middleware/controller
        return $handler->handle($request);
    }
}

