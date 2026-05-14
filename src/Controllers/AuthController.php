<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\UserModel;
use App\Services\OtpService;
use App\Services\FlashService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Twig\Environment;
use App\Models\CartModel;

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
    private FlashService $flash;
    private CartModel $cartModel;

    public function __construct(private Environment $twig, private string $basePath)
    {
        $this->userModel  = new UserModel();
        $this->otpService = new OtpService();
        $this->flash      = new FlashService();
        $this->cartModel  = new CartModel();
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
            $this->flash->error('flash.login_required');
            return $response
                ->withHeader('Location', $this->basePath . '/login')
                ->withStatus(302);
        }

        $user = $this->userModel->findByEmail($email);

        if (!$user || !$this->userModel->verifyPassword($password, $user->password)) {
            $this->flash->error('flash.login_invalid');
            return $response
                ->withHeader('Location', $this->basePath . '/login')
                ->withStatus(302);
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
            $this->flash->error('flash.register_fields_required');
            return $response
                ->withHeader('Location', $this->basePath . '/register')
                ->withStatus(302);
        }

        if ($email !== $confirmEmail) {
            $this->flash->error('flash.register_email_mismatch');
            return $response
                ->withHeader('Location', $this->basePath . '/register')
                ->withStatus(302);
        }

        if ($password !== $confirm) {
            $this->flash->error('flash.register_password_mismatch');
            return $response
                ->withHeader('Location', $this->basePath . '/register')
                ->withStatus(302);
        }

        if ($this->userModel->findByEmail($email)) {
            $this->flash->error('flash.register_email_exists');
            return $response
                ->withHeader('Location', $this->basePath . '/register')
                ->withStatus(302);
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
        $this->mergeAndLoadCart((int) $user->id);
        $this->flash->success('flash.register_success');

        return $response
            ->withHeader('Location', $this->basePath . '/products')
            ->withStatus(302);
    }

    // ── POST /logout ───────────────────────────────────────────────────────
    public function logout(Request $request, Response $response): Response
    {
        $_SESSION = [];
        session_destroy();
        // Re-start session to set the flash message
        session_start();
        $this->flash->info('flash.logout_success');
        return $response
            ->withHeader('Location', $this->basePath . '/login')
            ->withStatus(302);
    }

    // ── GET /totp/verify ───────────────────────────────────────────────────
    public function showTotpVerify(Request $request, Response $response): Response
    {
        $userId = $_SESSION['totp_user_id'] ?? null;
        if (!$userId) {
            return $response
                ->withHeader('Location', $this->basePath . '/login')
                ->withStatus(302);
        }

        // Check if the user already has TOTP enabled
        $user = $this->userModel->load((int) $userId);
        $hasTotp = $user && !empty($user->totp_secret);

        $html = $this->twig->render('totp-verify.html.twig', [
            'step'      => 'verify',
            'base_path' => $this->basePath,
            'app_lang'  => $_SESSION['lang'] ?? 'en',
            'has_totp'  => $hasTotp,
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
            $this->flash->error('flash.totp_invalid');
            return $response
                ->withHeader('Location', $this->basePath . '/totp/verify')
                ->withStatus(302);
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
            $this->flash->error('flash.totp_invalid_code');
            return $response
                ->withHeader('Location', $this->basePath . '/totp/verify')
                ->withStatus(302);
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
        $this->mergeAndLoadCart((int) $user->id);
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
        $this->mergeAndLoadCart((int) $user->id);
        return $response
            ->withHeader('Location', $this->basePath . '/products')
            ->withStatus(302);
    }

    private function mergeAndLoadCart(int $userId): void
    {
        // Merge session cart into DB
        if (!empty($_SESSION['cart'])) {
            $this->cartModel->mergeSessionCart($userId, $_SESSION['cart']);
        }

        // Load full DB cart into session
        $_SESSION['cart'] = $this->cartModel->toSessionFormat($userId);
    }
}