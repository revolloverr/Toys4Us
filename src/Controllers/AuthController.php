<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\UserModel;
use App\Services\OtpService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Twig\Environment;

/**
 * AuthController
 *
 * Manages authentication with TOTP 2FA:
 *
 *   Step 1 — User logs in with email + password
 *   Step 2 — If user has TOTP enabled → verify code
 *            If user has no TOTP → offer setup with QR code
 *   Step 3 — On success, set session and redirect
 */
class AuthController
{
    private UserModel $userModel;
    private OtpService $otpService;

    public function __construct(private Environment $twig, private string $basePath)
    {
        $this->userModel  = new UserModel();
        $this->otpService = new OtpService();
    }

    // ── GET /login ──────────────────────────────────────────────────────────
    public function showLogin(Request $request, Response $response): Response
    {
        $html = $this->twig->render('auth.html.twig', [
            'step'      => 'login',
            'base_path' => $this->basePath,
            'app_lang'  => $_SESSION['lang'] ?? 'en',
        ]);
        $response->getBody()->write($html);
        return $response;
    }

    // ── GET /register ───────────────────────────────────────────────────────
    public function showRegister(Request $request, Response $response): Response
    {
        $html = $this->twig->render('auth.html.twig', [
            'step'      => 'register',
            'base_path' => $this->basePath,
            'app_lang'  => $_SESSION['lang'] ?? 'en',
        ]);
        $response->getBody()->write($html);
        return $response;
    }

    // ── POST /login ────────────────────────────────────────────────────────
    public function login(Request $request, Response $response): Response
    {
        $data     = (array) $request->getParsedBody();
        $email    = trim($data['email'] ?? '');
        $password = trim($data['password'] ?? '');

        if (empty($email) || empty($password)) {
            return $this->renderLogin($response, 'Email and password are required.');
        }

        $user = $this->userModel->findByEmail($email);

        if (!$user || !$this->userModel->verifyPassword($password, $user->password)) {
            return $this->renderLogin($response, 'Invalid email or password.');
        }

        // Check soft delete
        if (!empty($user->deleted_at)) {
            $user->deleted_at = null;
            $this->userModel->save($user);
        }

        // ── TOTP 2FA step ─────────────────────────────────────────────────
        // Store the pending user ID in session for TOTP verification
        $_SESSION['totp_user_id'] = (int) $user->id;

        // If the user has a TOTP secret, go straight to verification
        if (!empty($user->totp_secret)) {
            return $response
                ->withHeader('Location', $this->basePath . '/totp/verify')
                ->withStatus(302);
        }

        // User has no TOTP — generate a secret and show setup
        $secret = $this->otpService->generateSecret();
        $_SESSION['totp_pending_secret'] = $secret;
        $qrCode = $this->otpService->getQrCode((string) $user->id, $secret);

        $html = $this->twig->render('totp-verify.html.twig', [
            'step'      => 'setup',
            'base_path' => $this->basePath,
            'app_lang'  => $_SESSION['lang'] ?? 'en',
            'qr_code'   => $qrCode,
            'secret'    => $secret,
        ]);
        $response->getBody()->write($html);
        return $response;
    }

    // ── POST /register ─────────────────────────────────────────────────────
    public function register(Request $request, Response $response): Response
    {
        $data = (array) $request->getParsedBody();
        $firstName = trim($data['first_name'] ?? '');
        $lastName  = trim($data['last_name'] ?? '');
        $name      = $firstName . ' ' . $lastName;
        $email     = strtolower(trim($data['email'] ?? ''));
        $confirmEmail = strtolower(trim($data['confirm_email'] ?? ''));
        $password  = trim($data['password'] ?? '');
        $confirm   = trim($data['confirm_password'] ?? '');

        if (empty($name) || empty($email) || empty($password)) {
            return $this->renderRegister($response, 'All fields are required.');
        }

        if ($email !== $confirmEmail) {
            return $this->renderRegister($response, 'Email addresses do not match.');
        }

        if ($password !== $confirm) {
            return $this->renderRegister($response, 'Passwords do not match.');
        }

        if ($this->userModel->findByEmail($email)) {
            return $this->renderRegister($response, 'An account with this email already exists.');
        }

        $this->userModel->create($name, $email, $password);

        // Log them in immediately — no TOTP for newly registered users (they set it up later in profile)
        $user = $this->userModel->findByEmail($email);
        $_SESSION['user'] = [
            'id'    => $user->id,
            'name'  => $user->name,
            'email' => $user->email,
            'role'  => $user->role,
        ];
        $_SESSION['authenticated'] = true;

        return $response
            ->withHeader('Location', $this->basePath . '/products')
            ->withStatus(302);
    }

    // ── POST /logout ───────────────────────────────────────────────────────
    public function logout(Request $request, Response $response): Response
    {
        $_SESSION = [];
        session_destroy();
        return $response
            ->withHeader('Location', $this->basePath . '/login')
            ->withStatus(302);
    }

    // ── GET /totp/verify ───────────────────────────────────────────────────
    public function showTotpVerify(Request $request, Response $response): Response
    {
        if (!isset($_SESSION['totp_user_id'])) {
            return $response
                ->withHeader('Location', $this->basePath . '/login')
                ->withStatus(302);
        }

        $html = $this->twig->render('totp-verify.html.twig', [
            'step'      => 'verify',
            'base_path' => $this->basePath,
            'app_lang'  => $_SESSION['lang'] ?? 'en',
        ]);
        $response->getBody()->write($html);
        return $response;
    }

    // ── POST /totp/verify ──────────────────────────────────────────────────
    public function verifyTotp(Request $request, Response $response): Response
    {
        $userId = $_SESSION['totp_user_id'] ?? null;
        if (!$userId) {
            return $response
                ->withHeader('Location', $this->basePath . '/login')
                ->withStatus(302);
        }

        $data  = (array) $request->getParsedBody();
        $code  = trim($data['totp_code'] ?? '');

        if (empty($code) || !preg_match('/^\d{6}$/', $code)) {
            return $this->renderTotpError($response, 'Please enter a valid 6-digit code.');
        }

        $user = $this->userModel->load((int) $userId);
        if (!$user) {
            return $response
                ->withHeader('Location', $this->basePath . '/login')
                ->withStatus(302);
        }

        // Determine which secret to use
        $secret = $user->totp_secret
            ?: ($_SESSION['totp_pending_secret'] ?? null);

        if (!$secret) {
            return $response
                ->withHeader('Location', $this->basePath . '/login')
                ->withStatus(302);
        }

        // Verify the code
        if (!$this->otpService->verify($code, $secret)) {
            return $this->renderTotpError($response, 'Invalid code. Please try again.');
        }

        // If this was a new setup (pending secret), save it to the user
        if (!empty($_SESSION['totp_pending_secret']) && empty($user->totp_secret)) {
            $user->totp_secret = $_SESSION['totp_pending_secret'];
            $this->userModel->save($user);
        }

        // Clean up session
        unset($_SESSION['totp_pending_secret']);
        unset($_SESSION['totp_secret']);
        unset($_SESSION['totp_user_id']);

        // Mark as fully authenticated
        $_SESSION['user'] = [
            'id'    => $user->id,
            'name'  => $user->name,
            'email' => $user->email,
            'role'  => $user->role,
        ];
        $_SESSION['authenticated'] = true;

        return $response
            ->withHeader('Location', $this->basePath . '/products')
            ->withStatus(302);
    }

    // ── POST /totp/skip ────────────────────────────────────────────────────
    public function skipTotpSetup(Request $request, Response $response): Response
    {
        $userId = $_SESSION['totp_user_id'] ?? null;
        if (!$userId) {
            return $response
                ->withHeader('Location', $this->basePath . '/login')
                ->withStatus(302);
        }

        $user = $this->userModel->load((int) $userId);
        if (!$user) {
            return $response
                ->withHeader('Location', $this->basePath . '/login')
                ->withStatus(302);
        }

        // Clean up pending
        unset($_SESSION['totp_pending_secret']);
        unset($_SESSION['totp_secret']);
        unset($_SESSION['totp_user_id']);

        // Log them in without TOTP
        $_SESSION['user'] = [
            'id'    => $user->id,
            'name'  => $user->name,
            'email' => $user->email,
            'role'  => $user->role,
        ];
        $_SESSION['authenticated'] = true;

        return $response
            ->withHeader('Location', $this->basePath . '/products')
            ->withStatus(302);
    }

    // ── Private helpers ────────────────────────────────────────────────────

    private function renderLogin(Response $response, string $error): Response
    {
        $html = $this->twig->render('auth.html.twig', [
            'step'      => 'login',
            'error'     => $error,
            'base_path' => $this->basePath,
            'app_lang'  => $_SESSION['lang'] ?? 'en',
        ]);
        $response->getBody()->write($html);
        return $response;
    }

    private function renderRegister(Response $response, string $error): Response
    {
        $html = $this->twig->render('auth.html.twig', [
            'step'      => 'register',
            'error'     => $error,
            'base_path' => $this->basePath,
            'app_lang'  => $_SESSION['lang'] ?? 'en',
        ]);
        $response->getBody()->write($html);
        return $response;
    }

    private function renderTotpError(Response $response, string $error): Response
    {
        $userId = $_SESSION['totp_user_id'] ?? null;
        $qrCode = null;
        $secret = null;

        // If there's a pending setup, regenerate the QR code display
        if (!empty($_SESSION['totp_pending_secret']) && $userId) {
            $user   = $this->userModel->load((int) $userId);
            $secret = $_SESSION['totp_pending_secret'];
            $qrCode = $this->otpService->getQrCode((string) $userId, $secret);
        }

        $html = $this->twig->render('totp-verify.html.twig', [
            'step'      => 'verify',
            'error'     => $error,
            'base_path' => $this->basePath,
            'app_lang'  => $_SESSION['lang'] ?? 'en',
            'qr_code'   => $qrCode,
            'secret'    => $secret,
        ]);
        $response->getBody()->write($html);
        return $response;
    }
}