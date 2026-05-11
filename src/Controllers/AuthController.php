<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\UserModel;
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
    private UserModel $userModel;

    public function __construct(private Environment $twig, private string $basePath,)
    {
        $this->userModel = new UserModel();
    }

    // show the login
    /**
     * GET /auth
     * Render the entry form.
     */
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

    // GET /register
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

    // POST /login
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

        // check soft delete
        if (!empty($user->deleted_at)) {
            // cancel the deletion since they logged back in
            $user->deleted_at = null;
            $this->userModel->save($user);
        }

        $_SESSION['user'] = [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
        ];

        return $response
            ->withHeader('Location', $this->basePath . '/products')
            ->withStatus(302);
    }

    // POST /register
    public function register(Request $request, Response $response): Response
    {
        $data = (array) $request->getParsedBody();
        $firstName = trim($data['first_name'] ?? '');
        $lastName = trim($data['last_name'] ?? '');
        $name = $firstName . ' ' . $lastName;
        $email = strtolower(trim($data['email'] ?? ''));
        $confirmEmail = strtolower(trim($data['confirm_email'] ?? ''));
        $password = trim($data['password'] ?? '');
        $confirm = trim($data['confirm_password'] ?? '');

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

        // log them in immediately after register
        $user = $this->userModel->findByEmail($email);
        $_SESSION['user'] = [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
        ];

        return $response
            ->withHeader('Location', $this->basePath . '/products')
            ->withStatus(302);
    }

    // POST /logout
    public function logout(Request $request, Response $response): Response
    {
        session_destroy();
        return $response
            ->withHeader('Location', $this->basePath . '/login')
            ->withStatus(302);
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    private function renderLogin(Response $response, string $error): Response
    {
        $html = $this->twig->render('auth.html.twig', [
            'step' => 'login',
            'error' => $error,
            'base_path' => $this->basePath,
            'app_lang' => $_SESSION['lang'] ?? 'en',
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
}