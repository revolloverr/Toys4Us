<?php

declare(strict_types=1);

namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use RedBeanPHP\R;
use Twig\Environment;

class AdminController
{
    public function __construct(
        private Environment $twig,
        private string $basePath,
    ) {}

    // GET /admin
    public function index(Request $request, Response $response): Response
    {
        $html = $this->twig->render('admin/index.html.twig', [
            'base_path'  => $this->basePath,
            'app_lang' => $_SESSION['lang'] ?? 'en',
            'counts'   => [
                'products'    => R::count('product'),
                'categories'  => R::count('category'),
                'bases'       => R::count('plush_base'),
                'accessories' => R::count('plush_accessory'),
                'orders'      => R::count('order'),
                'users'       => R::count('user'),
            ],
        ]);

        $response->getBody()->write($html);
        return $response;
    }

    // ── PRODUCTS ─────────────────────────────────────────────────────────────

    public function products(Request $request, Response $response): Response
    {
        $html = $this->twig->render('admin/products.html.twig', [
            'base_path'  => $this->basePath,
            'products'   => R::findAll('product', 'ORDER BY id DESC'),
            'categories' => R::findAll('category'),
        ]);

        $response->getBody()->write($html);
        return $response;
    }

    public function storeProduct(Request $request, Response $response): Response
    {
        $data = (array) $request->getParsedBody();

        $product = R::dispense('product');

        $product->name        = trim($data['name'] ?? '');
        $product->description = trim($data['description'] ?? '');
        $product->price       = (float) ($data['price'] ?? 0);
        $product->stock       = (int) ($data['stock'] ?? 0);
        $product->category_id = (int) ($data['category_id'] ?? 0) ?: null;
        $product->image       = trim($data['image'] ?? '');
        $product->is_active   = 1;
        $product->rating      = 0;

        R::store($product);

        return $response
            ->withHeader('Location', $this->basePath . '/admin/products')
            ->withStatus(302);
    }

    public function updateProduct(Request $request, Response $response): Response
    {
        $data = (array) $request->getParsedBody();

        $product = R::load('product', (int) ($data['id'] ?? 0));

        if ($product->id) {
            $product->name        = trim($data['name'] ?? '');
            $product->description = trim($data['description'] ?? '');
            $product->price       = (float) ($data['price'] ?? 0);
            $product->stock       = (int) ($data['stock'] ?? 0);
            $product->category_id = (int) ($data['category_id'] ?? 0) ?: null;
            $product->image       = trim($data['image'] ?? '');
            $product->is_active   = isset($data['is_active']) ? 1 : 0;

            R::store($product);
        }

        return $response
            ->withHeader('Location', $this->basePath . '/admin/products')
            ->withStatus(302);
    }

    public function deleteProduct(Request $request, Response $response): Response
    {
        $data = (array) $request->getParsedBody();

        $product = R::load('product', (int) ($data['id'] ?? 0));

        if ($product->id) {
            R::trash($product);
        }

        return $response
            ->withHeader('Location', $this->basePath . '/admin/products')
            ->withStatus(302);
    }

    // ── CATEGORIES ───────────────────────────────────────────────────────────

    public function categories(Request $request, Response $response): Response
    {
        $html = $this->twig->render('admin/categories.html.twig', [
            'base_path'  => $this->basePath,
            'categories' => R::findAll('category', 'ORDER BY id DESC'),
        ]);

        $response->getBody()->write($html);
        return $response;
    }

    public function storeCategory(Request $request, Response $response): Response
    {
        $data = (array) $request->getParsedBody();

        $cat = R::dispense('category');

        $cat->name  = trim($data['name'] ?? '');
        $cat->slug  = strtolower(str_replace(' ', '-', trim($data['name'] ?? '')));
        $cat->image = trim($data['image'] ?? '');

        R::store($cat);

        return $response
            ->withHeader('Location', $this->basePath . '/admin/categories')
            ->withStatus(302);
    }

    public function updateCategory(Request $request, Response $response): Response
    {
        $data = (array) $request->getParsedBody();

        $cat = R::load('category', (int) ($data['id'] ?? 0));

        if ($cat->id) {
            $cat->name  = trim($data['name'] ?? '');
            $cat->slug  = strtolower(str_replace(' ', '-', trim($data['name'] ?? '')));
            $cat->image = trim($data['image'] ?? '');

            R::store($cat);
        }

        return $response
            ->withHeader('Location', $this->basePath . '/admin/categories')
            ->withStatus(302);
    }

    public function deleteCategory(Request $request, Response $response): Response
    {
        $data = (array) $request->getParsedBody();

        $cat = R::load('category', (int) ($data['id'] ?? 0));

        if ($cat->id) {
            R::trash($cat);
        }

        return $response
            ->withHeader('Location', $this->basePath . '/admin/categories')
            ->withStatus(302);
    }

    // ── PLUSH BASES ──────────────────────────────────────────────────────────

    public function bases(Request $request, Response $response): Response
    {
        $html = $this->twig->render('admin/bases.html.twig', [
            'base_path'  => $this->basePath,
            'bases' => R::findAll('plush_base', 'ORDER BY species, color'),
        ]);
        $response->getBody()->write($html);
        return $response;
    }

    public function storeBase(Request $request, Response $response): Response
    {
        $data             = (array) $request->getParsedBody();
        $base             = R::dispense('plush_base');
        $base->name       = trim($data['name'] ?? '');
        $base->species    = trim($data['species'] ?? '');
        $base->color      = trim($data['color'] ?? '');
        $base->image_path = trim($data['image_path'] ?? '');
        $base->base_price = (float) ($data['base_price'] ?? 0);
        $base->sort_order = (int) ($data['sort_order'] ?? 0);
        $base->is_active  = 1;
        R::store($base);

        return $response->withHeader('Location', $this->basePath . '/admin/bases')->withStatus(302);
    }

    public function updateBase(Request $request, Response $response): Response
    {
        $data = (array) $request->getParsedBody();
        $base = R::load('plush_base', (int) ($data['id'] ?? 0));
        if ($base->id) {
            $base->name       = trim($data['name'] ?? '');
            $base->species    = trim($data['species'] ?? '');
            $base->color      = trim($data['color'] ?? '');
            $base->image_path = trim($data['image_path'] ?? '');
            $base->base_price = (float) ($data['base_price'] ?? 0);
            $base->sort_order = (int) ($data['sort_order'] ?? 0);
            $base->is_active  = isset($data['is_active']) ? 1 : 0;
            R::store($base);
        }

        return $response->withHeader('Location', $this->basePath . '/admin/bases')->withStatus(302);
    }

    public function deleteBase(Request $request, Response $response): Response
    {
        $data = (array) $request->getParsedBody();
        $base = R::load('plush_base', (int) ($data['id'] ?? 0));
        if ($base->id) R::trash($base);

        return $response->withHeader('Location', $this->basePath . '/admin/bases')->withStatus(302);
    }

    // ── PLUSH ACCESSORIES ────────────────────────────────────────────────────

    public function accessories(Request $request, Response $response): Response
    {
        $html = $this->twig->render('admin/accessories.html.twig', [
            'base_path'  => $this->basePath,
            'accessories' => R::findAll('plush_accessory', 'ORDER BY category, id'),
        ]);
        $response->getBody()->write($html);
        return $response;
    }

    public function storeAccessory(Request $request, Response $response): Response
    {
        $data             = (array) $request->getParsedBody();
        $acc              = R::dispense('plush_accessory');
        $acc->name        = trim($data['name'] ?? '');
        $acc->category    = trim($data['category'] ?? '');
        $acc->image_path  = trim($data['image_path'] ?? '');
        $acc->price       = (float) ($data['price'] ?? 0);
        $acc->is_active   = 1;
        R::store($acc);

        return $response->withHeader('Location', $this->basePath . '/admin/accessories')->withStatus(302);
    }

    public function updateAccessory(Request $request, Response $response): Response
    {
        $data = (array) $request->getParsedBody();
        $acc  = R::load('plush_accessory', (int) ($data['id'] ?? 0));
        if ($acc->id) {
            $acc->name       = trim($data['name'] ?? '');
            $acc->category   = trim($data['category'] ?? '');
            $acc->image_path = trim($data['image_path'] ?? '');
            $acc->price      = (float) ($data['price'] ?? 0);
            $acc->is_active  = isset($data['is_active']) ? 1 : 0;
            R::store($acc);
        }

        return $response->withHeader('Location', $this->basePath . '/admin/accessories')->withStatus(302);
    }

    public function deleteAccessory(Request $request, Response $response): Response
    {
        $data = (array) $request->getParsedBody();
        $acc  = R::load('plush_accessory', (int) ($data['id'] ?? 0));
        if ($acc->id) R::trash($acc);

        return $response->withHeader('Location', $this->basePath . '/admin/accessories')->withStatus(302);
    }

    public function users(Request $request, Response $response): Response
    {
        $search = trim($request->getQueryParams()['search'] ?? '');
        
        if ($search) {
            $users = R::find('user', 'name LIKE ? OR email LIKE ? ORDER BY id DESC', 
                ["%$search%", "%$search%"]);
        } else {
            $users = R::findAll('user', 'ORDER BY id DESC');
        }

        $html = $this->twig->render('admin/users.html.twig', [
            'base_path' => $this->basePath,
            'users'     => $users,
            'search'    => $search,
        ]);
        $response->getBody()->write($html);
        return $response;
    }

    public function deleteUser(Request $request, Response $response): Response
    {
        $data = (array) $request->getParsedBody();
        $user = R::load('user', (int) ($data['id'] ?? 0));
        if ($user->id && $user->id !== (int) $_SESSION['user']['id']) {
            R::trash($user);
        }
        return $response->withHeader('Location', $this->basePath . '/admin/users')->withStatus(302);
    }

    public function updateUser(Request $request, Response $response): Response
    {
        $data = (array) $request->getParsedBody();

        $user = R::load('user', (int) ($data['id'] ?? 0));

        if ($user->id) {

            $user->name  = trim($data['name'] ?? '');
            $user->email = trim($data['email'] ?? '');

            $role = strtolower(trim($data['role'] ?? 'user'));

            // only allow admin or user
            $user->role = $role === 'admin' ? 'admin' : 'user';

            R::store($user);

            // update session if editing current logged-in user
            if (
                isset($_SESSION['user']) &&
                $_SESSION['user']['id'] == $user->id
            ) {
                $_SESSION['user']['name']  = $user->name;
                $_SESSION['user']['email'] = $user->email;
                $_SESSION['user']['role']  = $user->role;
            }
        }

        return $response
            ->withHeader('Location', $this->basePath . '/admin/users')
            ->withStatus(302);
    }
}