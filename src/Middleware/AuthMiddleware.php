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

    /**
     * Invoked by Slim for every request that matches a route in the protected group.
     *
     * Steps:
     *   1. Check if $_SESSION['authenticated'] is strictly true.
     *   2. If NOT authenticated: create a response, set the Location header to
     *      $this->basePath . '/auth', set status 302, and return it immediately.
     *   3. If authenticated: call $handler->handle($request) and return the result.
     *
     * Hint: $this->responseFactory->createResponse(302) creates an empty 302 response.
     *       Chain ->withHeader('Location', '...') to set the redirect destination.
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

/*
## Step-by-Step Breakdown:

__Step 1: Authentication Check__

- Uses `!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true` to check if the user is NOT authenticated
- This ensures the session variable exists AND is strictly equal to `true`

__Step 2: Redirect for Unauthenticated Users__

- Creates a 302 redirect response using `$this->responseFactory->createResponse(302)`
- Sets the Location header to `$this->basePath . '/auth'` (e.g., `/todo-app-lab10/auth`)
- Returns the redirect response immediately, preventing further processing

__Step 3: Proceed for Authenticated Users__

- If the user is authenticated, calls `$handler->handle($request)` to continue the middleware chain
- Returns the result of the handler, allowing the request to proceed to the protected route

## Key Features:

- ✅ Strict type checking for authentication status
- ✅ Proper redirect using ResponseFactory with 302 status
- ✅ Correct Location header construction using basePath
- ✅ Clean separation of authenticated vs unauthenticated logic
- ✅ Follows the exact pattern described in the instructions

The middleware is now ready to protect routes that require authentication by redirecting unauthenticated users to the login page.
*/