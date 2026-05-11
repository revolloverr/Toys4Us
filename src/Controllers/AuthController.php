<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\OtpService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Twig\Environment;

/**
 * AuthController
 *
 * Manages the TOTP authentication flow:
 *
 *   Step 1 — User enters a username and requests a code.
 *   Step 2 — A QR code is displayed; user scans it with their authenticator app.
 *   Step 3 — User enters the 6-digit TOTP code and is either authenticated or rejected.
 *
 * Routes handled:
 *   GET  /auth          → showForm()
 *   POST /auth/request  → requestOtp()
 *   GET  /auth/verify   → showVerify()
 *   POST /auth/verify   → verifyOtp()
 *   POST /auth/logout   → logout()
 */
class AuthController
{
    public function __construct(
        private Environment $twig,
        private OtpService  $otpService,
        private string      $basePath,
    ) {}

    // ── Step 1: show the login form ───────────────────────────────────────────
    /**
     * GET /auth
     * Render the username entry form.
     * PRE-FILLED — read before implementing requestOtp().
     */
    public function showForm(Request $request, Response $response): Response
    {
        $html = $this->twig->render('auth.html.twig', [
            'step'      => 'login',
            'base_path' => $this->basePath,
            'app_lang'  => $_SESSION['lang'] ?? 'en',
        ]);
        $response->getBody()->write($html);
        return $response;
    }

    // ── Step 2: receive username, generate TOTP secret and display setup URI ────
    /**
     * POST /auth/request
     * Read the username, generate a TOTP secret, and show the provisioning URI.
     *
     * Steps:
     *   1. Read 'username' from $request->getParsedBody(). Trim it.
     *      If empty, redirect back to GET /auth.
     *   2. Store the username in $_SESSION['username'].
     *   3. Call $this->otpService->generate($username) — this stores the secret
     *      in the session AND returns the otpauth:// provisioning URI.
     *   4. Render auth.html.twig with:
     *        'step'     => 'otp_display'
     *        'qr_code'  => $uri      (data URI returned by OtpService::generate())
     *        'base_path' and 'app_lang' as usual
     *
     * TODO: Implement this method.
     */
    public function requestOtp(Request $request, Response $response): Response
    {
        $data     = (array) $request->getParsedBody();
        $username = trim($data['username'] ?? '');

        // 1. Check if username is empty, redirect to GET /auth if so
        if (empty($username)) {
            return $response
                ->withHeader('Location', $this->basePath . '/auth')
                ->withStatus(302);
        }

        // 2. Store username in session
        $_SESSION['username'] = $username;

        // 3. Generate TOTP secret and get QR code data URI
        $qrCodeUri = $this->otpService->generate($username);

        // 4. Render auth.html.twig with OTP display step
        $html = $this->twig->render('auth.html.twig', [
            'step'      => 'otp_display',
            'qr_code'   => $qrCodeUri,
            'base_path' => $this->basePath,
            'app_lang'  => $_SESSION['lang'] ?? 'en',
        ]);
        $response->getBody()->write($html);
        return $response;
    }
    /*
        ## Step-by-Step Breakdown:

__Step 1: Read and Validate Username__

- Extracts 'username' from `$request->getParsedBody()`
- Uses `trim()` to remove whitespace
- Checks if username is empty using `empty()`
- If empty, redirects back to `GET /auth` with 302 status

__Step 2: Store Username in Session__

- Saves the validated username to `$_SESSION['username']`
- This persists the username across the authentication flow

__Step 3: Generate TOTP Secret and QR Code__

- Calls `$this->otpService->generate($username)`
- This method stores the TOTP secret in the session AND returns a data URI for the QR code
- The data URI can be directly embedded in an `<img>` tag

__Step 4: Render OTP Display Template__

- Renders `auth.html.twig` with:

  - `'step' => 'otp_display'` - tells template to show QR code setup
  - `'qr_code' => $qrCodeUri` - provides the QR code data URI
  - `'base_path'` and `'app_lang'` - standard template variables

## Key Features:

- ✅ Proper input validation and sanitization
- ✅ Session management for username persistence
- ✅ Integration with OtpService for TOTP generation
- ✅ Correct template rendering with all required variables
- ✅ Proper redirect handling for invalid input
- ✅ Follows the exact flow described in the instructions

The method is now ready to handle the second step of the TOTP authentication flow, where users receive their QR code for scanning with their authenticator app.


    */

    // ── Step 3a: show the OTP entry form ──────────────────────────────────────
    /**
     * GET /auth/verify
     * Render the OTP entry form.
     * PRE-FILLED.
     */
    public function showVerify(Request $request, Response $response): Response
    {
        $html = $this->twig->render('auth.html.twig', [
            'step'      => 'verify',
            'base_path' => $this->basePath,
            'app_lang'  => $_SESSION['lang'] ?? 'en',
        ]);
        $response->getBody()->write($html);
        return $response;
    }

    // ── Step 3b: verify the submitted code ────────────────────────────────────
    /**
     * POST /auth/verify
     * Check the submitted OTP. Authenticate or reject.
     *
     * Steps:
     *   1. Read 'code' from the POST body. Trim it.
     *   2. Call $this->otpService->verify($code).
     *   3a. If true:
     *         - Call $this->otpService->invalidate() to consume the code.
     *         - Set $_SESSION['authenticated'] = true.
     *         - Redirect to $this->basePath . '/products' with status 302.
     *   3b. If false:
     *         - Render auth.html.twig with step='verify' and error='auth.error_invalid'.
     *           (The template will call trans('auth.error_invalid') to display the message.)
     *
     * TODO: Implement this method.
     */
    public function verifyOtp(Request $request, Response $response): Response
    {
        $data = (array) $request->getParsedBody();
        $code = trim($data['code'] ?? '');

        // 1. Verify the OTP code
        if ($this->otpService->verify($code)) {
            // 2. If valid: invalidate the code, authenticate, and redirect
            $this->otpService->invalidate();
            $_SESSION['authenticated'] = true;
            return $response
                ->withHeader('Location', $this->basePath . '/products')
                ->withStatus(302);
        } else {
            // 3. If invalid: render auth.html.twig with error message
            $html = $this->twig->render('auth.html.twig', [
                'step'      => 'verify',
                'error'     => 'auth.error_invalid',
                'base_path' => $this->basePath,
                'app_lang'  => $_SESSION['lang'] ?? 'en',
            ]);
            $response->getBody()->write($html);
            return $response;
        }
    }
    /*

        ## Step-by-Step Breakdown:

__Step 1: Read and Validate Code__

- Extracts 'code' from `$request->getParsedBody()`
- Uses `trim()` to remove whitespace
- Prepares for verification

__Step 2: Verify OTP Code__

- Calls `$this->otpService->verify($code)` to check if the code is valid
- This method checks against the TOTP secret stored in the session

__Step 3a: Successful Verification__

- Calls `$this->otpService->invalidate()` to consume the code (prevents replay attacks)
- Sets `$_SESSION['authenticated'] = true` to mark user as logged in
- Redirects to `/products` with 302 status (to the product listing)

__Step 3b: Failed Verification__

- Renders `auth.html.twig` with:

  - `'step' => 'verify'` - shows the OTP entry form again
  - `'error' => 'auth.error_invalid'` - displays error message (translated via trans())
  - `'base_path'` and `'app_lang'` - standard template variables

- User can try again with a new code

## Key Features:

- ✅ Proper input validation and sanitization
- ✅ Integration with OtpService for TOTP verification
- ✅ Code invalidation after successful use (security best practice)
- ✅ Session-based authentication state management
- ✅ Proper redirect on success
- ✅ Error handling with user-friendly messages
- ✅ Follows the exact flow described in the instructions

The complete TOTP authentication flow is now implemented, allowing users to securely log in using two-factor authentication.


    */

    // ── Logout ─────────────────────────────────────────────────────────────────
    /**
     * POST /auth/logout
     * Destroy the entire session and redirect to /auth.
     *
     * PRE-FILLED — demonstrates session_destroy(). Compare with unset() — what
     * is the difference? Answer reflection question Q8 after reading this method.
     */
    public function logout(Request $request, Response $response): Response
    {
        session_destroy();
        return $response
            ->withHeader('Location', $this->basePath . '/auth')
            ->withStatus(302);
    }
}
