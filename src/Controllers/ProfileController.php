<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\UserModel;
use App\Services\OtpService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Twig\Environment;

class ProfileController
{
    private UserModel $userModel;
    private OtpService $otpService;

    private OrderModel $orderModel;

    public function __construct(
        private Environment $twig,
        private string $basePath,
    ) {
        $this->userModel  = new UserModel();
        $this->otpService = new OtpService();
        $this->orderModel = new OrderModel();
    }

    // GET /profile
    public function index(Request $request, Response $response): Response
    {
        $userId = (int) $_SESSION['user']['id'];
        $user   = $this->userModel->load($userId);

        $html = $this->twig->render('profile.html.twig', [
            'base_path' => $this->basePath,
            'user'      => $user,
            'orders'    => $this->getOrders($userId),
        ]);

        $response->getBody()->write($html);
        return $response;
    }

    // POST /profile/edit
    public function update(Request $request, Response $response): Response
    {
        if (!isset($_SESSION['user'])) {
            return $response->withHeader('Location', $this->basePath . '/login')->withStatus(302);
        }

        $data      = (array) $request->getParsedBody();
        $firstName = trim($data['first_name'] ?? '');
        $lastName  = trim($data['last_name'] ?? '');
        $name      = $firstName . ' ' . $lastName;
        $email     = trim($data['email'] ?? '');
        $phone     = trim($data['phone'] ?? '');

        $user = $this->userModel->load((int) $_SESSION['user']['id']);

        if (empty($firstName) || empty($lastName) || empty($email)) {
            return $this->renderWithError($response, $user, 'First name, Last name and Email are required.', 'account');
        }

        $user->name  = $name;
        $user->email = $email;
        $user->phone = $phone;
        $this->userModel->save($user);

        $_SESSION['user']['name']  = $name;
        $_SESSION['user']['email'] = $email;

        return $response->withHeader('Location', $this->basePath . '/profile')->withStatus(302);
    }

    // POST /profile/change-password
    public function changePassword(Request $request, Response $response): Response
    {
        if (!isset($_SESSION['user'])) {
            return $response->withHeader('Location', $this->basePath . '/login')->withStatus(302);
        }

        $data    = (array) $request->getParsedBody();
        $current = trim($data['current_password'] ?? '');
        $new     = trim($data['new_password'] ?? '');
        $confirm = trim($data['confirm_password'] ?? '');

        $user = $this->userModel->load((int) $_SESSION['user']['id']);

        if (!$this->userModel->verifyPassword($current, $user->password)) {
            return $this->renderWithError($response, $user, 'Current password is incorrect.', 'security');
        }
        if ($new !== $confirm) {
            return $this->renderWithError($response, $user, 'New passwords do not match.', 'security');
        }
        if (strlen($new) < 8) {
            return $this->renderWithError($response, $user, 'Password must be at least 8 characters.', 'security');
        }

        $user->password = password_hash($new, PASSWORD_BCRYPT);
        $this->userModel->save($user);

        return $response->withHeader('Location', $this->basePath . '/profile')->withStatus(302);
    }

    // POST /profile/delete
    public function delete(Request $request, Response $response): Response
    {
        if (!isset($_SESSION['user'])) {
            return $response->withHeader('Location', $this->basePath . '/login')->withStatus(302);
        }

        $user             = $this->userModel->load((int) $_SESSION['user']['id']);
        $user->deleted_at = date('Y-m-d H:i:s', strtotime('+14 days'));
        $this->userModel->save($user);

        session_destroy();

        return $response->withHeader('Location', $this->basePath . '/login')->withStatus(302);
    }

    // POST /profile/totp/setup
    public function setupTotp(Request $request, Response $response): Response
    {
        if (!isset($_SESSION['user'])) {
            return $response->withHeader('Location', $this->basePath . '/login')->withStatus(302);
        }

        $user   = $this->userModel->load((int) $_SESSION['user']['id']);
        $secret = $this->otpService->generateSecret();
        $qrCode = $this->otpService->getQrCode((string) $user->id, $secret);

        $_SESSION['totp_new_secret'] = $secret;

        $html = $this->twig->render('profile.html.twig', [
            'base_path'          => $this->basePath,
            'app_lang'           => $_SESSION['lang'] ?? 'en',
            'user'               => $user,
            'orders'             => $this->getOrders((int) $user->id),
            'active_tab'         => 'security',
            'totp_qr_code'       => $qrCode,
            'totp_secret'        => $secret,
            'totp_pending_setup' => true,
        ]);
        $response->getBody()->write($html);
        return $response;
    }

    // POST /profile/totp/confirm
    public function confirmTotp(Request $request, Response $response): Response
    {
        if (!isset($_SESSION['user'])) {
            return $response->withHeader('Location', $this->basePath . '/login')->withStatus(302);
        }

        $data          = (array) $request->getParsedBody();
        $code          = trim($data['totp_code'] ?? '');
        $pendingSecret = $_SESSION['totp_new_secret'] ?? null;
        $user          = $this->userModel->load((int) $_SESSION['user']['id']);

        if (empty($code) || !preg_match('/^\d{6}$/', $code)) {
            return $this->renderWithError($response, $user, 'Please enter a valid 6-digit code.', 'security');
        }
        if (!$pendingSecret) {
            return $this->renderWithError($response, $user, 'Setup session expired. Please try again.', 'security');
        }
        if (!$this->otpService->verify($code, $pendingSecret)) {
            return $this->renderWithError($response, $user, 'Invalid code. Please try again.', 'security');
        }

        $user->totp_secret = $pendingSecret;
        $this->userModel->save($user);
        unset($_SESSION['totp_new_secret']);

        return $response->withHeader('Location', $this->basePath . '/profile')->withStatus(302);
    }

    // POST /profile/totp/disable
    public function disableTotp(Request $request, Response $response): Response
    {
        if (!isset($_SESSION['user'])) {
            return $response->withHeader('Location', $this->basePath . '/login')->withStatus(302);
        }

        $user              = $this->userModel->load((int) $_SESSION['user']['id']);
        $user->totp_secret = null;
        $this->userModel->save($user);

        return $response->withHeader('Location', $this->basePath . '/profile')->withStatus(302);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function getOrders(int $userId): array
    {
        $orders = $this->orderModel->findByUser($userId);
        foreach ($orders as &$order) {
            $order['items'] = $this->orderModel->getItems((int) $order['id']);
        }
        unset($order);
        return $orders;
    }

    private function renderWithError(Response $response, mixed $user, string $error, string $tab): Response
    {
        $html = $this->twig->render('profile.html.twig', [
            'base_path'  => $this->basePath,
            'app_lang'   => $_SESSION['lang'] ?? 'en',
            'user'       => $user,
            'error'      => $error,
            'active_tab' => $tab,
            'orders'     => $this->getOrders((int) $user->id),
        ]);
        $response->getBody()->write($html);
        return $response;
    }
}