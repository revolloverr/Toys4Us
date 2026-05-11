<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\UserModel;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Twig\Environment;

class ProfileController
{
    private UserModel $userModel;

    public function __construct(
        private Environment $twig,
        private string $basePath,
    ) {
        $this->userModel = new UserModel();
    }

    // GET /profile
    public function index(Request $request, Response $response): Response
    {
        if (!isset($_SESSION['user'])) {
            return $response->withHeader('Location', $this->basePath . '/login')->withStatus(302);
        }

        $user = $this->userModel->load((int) $_SESSION['user']['id']);

        $html = $this->twig->render('profile.html.twig', [
            'base_path' => $this->basePath,
            'app_lang'  => $_SESSION['lang'] ?? 'en',
            'user'      => $user,
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

        $data  = (array) $request->getParsedBody();
        $firstName = trim($data['first_name'] ?? '');
        $lastName = trim($data['last_name'] ?? '');
        $name = $firstName . ' ' . $lastName;
        $email = trim($data['email'] ?? '');
        $phone = trim($data['phone'] ?? '');

        if (empty($firstName) || empty($lastName) || empty($email)) {
            $user = $this->userModel->load((int) $_SESSION['user']['id']);
            $html = $this->twig->render('profile.html.twig', [
                'base_path' => $this->basePath,
                'app_lang'  => $_SESSION['lang'] ?? 'en',
                'user'      => $user,
                'error'     => 'First name, Last name and Email are required.',
            ]);
            $response->getBody()->write($html);
            return $response;
        }

        $user        = $this->userModel->load((int) $_SESSION['user']['id']);
        $user->name  = $name;
        $user->email = $email;
        $user->phone = $phone;
        $this->userModel->save($user);

        // Update session
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

        $data        = (array) $request->getParsedBody();
        $current     = trim($data['current_password'] ?? '');
        $new         = trim($data['new_password'] ?? '');
        $confirm     = trim($data['confirm_password'] ?? '');

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

    private function renderWithError(Response $response, mixed $user, string $error, string $tab): Response
    {
        $html = $this->twig->render('profile.html.twig', [
            'base_path'   => $this->basePath,
            'app_lang'    => $_SESSION['lang'] ?? 'en',
            'user'        => $user,
            'error'       => $error,
            'active_tab'  => $tab,
        ]);
        $response->getBody()->write($html);
        return $response;
    }
}