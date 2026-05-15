<?php

declare(strict_types=1);

namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use RedBeanPHP\R;
use Twig\Environment;
use App\Models\PlushModel;
use App\Models\ProductModel;
use App\Models\CategoryModel;
use App\Models\UserModel;
use App\Models\OrderModel;

class AdminController
{
    public function __construct(
        private Environment   $twig,
        private string        $basePath,
        private PlushModel    $plushModel,
        private ProductModel  $productModel,
        private CategoryModel $categoryModel,
        private UserModel     $userModel,
        private OrderModel    $orderModel,
    ) {}

    // ── DASHBOARD ─────────────────────────────────────────────────────────────

    public function index(Request $request, Response $response): Response
    {
        $html = $this->twig->render('admin/index.html.twig', [
            'base_path' => $this->basePath,
            'app_lang'  => $_SESSION['lang'] ?? 'en',
            'counts'    => [
                'products'    => R::count('product'),
                'categories'  => $this->categoryModel->count(),
                'bases'       => R::count('plushbase'),
                'accessories' => R::count('plushaccessory'),
                'orders'      => R::count('order'),
                'users'       => R::count('user'),
            ],
        ]);

        $response->getBody()->write($html);
        return $response;
    }

    // ── PRODUCTS ──────────────────────────────────────────────────────────────

    public function products(Request $request, Response $response): Response
    {
        $html = $this->twig->render('admin/products.html.twig', [
            'base_path'  => $this->basePath,
            'products'   => $this->productModel->findAll(),
            'categories' => $this->categoryModel->findAll(),
        ]);

        $response->getBody()->write($html);
        return $response;
    }

    public function storeProduct(Request $request, Response $response): Response
    {
        $data = (array) $request->getParsedBody();
        $this->productModel->create(
            trim($data['name'] ?? ''),
            trim($data['description'] ?? ''),
            (float) ($data['price'] ?? 0),
            trim($data['image'] ?? ''),
            (int) ($data['stock'] ?? 0),
            (int) ($data['category_id'] ?? 0) ?: null,
        );

        return $response
            ->withHeader('Location', $this->basePath . '/admin/products')
            ->withStatus(302);
    }

    public function updateProduct(Request $request, Response $response): Response
    {
        $data    = (array) $request->getParsedBody();
        $product = $this->productModel->load((int) ($data['id'] ?? 0));

        if ($product->id) {
            $product->name        = trim($data['name'] ?? '');
            $product->description = trim($data['description'] ?? '');
            $product->price       = (float) ($data['price'] ?? 0);
            $product->stock       = (int) ($data['stock'] ?? 0);
            $product->rating      = (float) ($data['rating'] ?? 0);
            $product->category_id = (int) ($data['category_id'] ?? 0) ?: null;
            $product->image       = trim($data['image'] ?? '');
            $product->is_active   = isset($data['is_active']) ? 1 : 0;
            $this->productModel->save($product);
        }

        return $response
            ->withHeader('Location', $this->basePath . '/admin/products')
            ->withStatus(302);
    }

    public function deleteProduct(Request $request, Response $response): Response
    {
        $data    = (array) $request->getParsedBody();
        $product = $this->productModel->load((int) ($data['id'] ?? 0));
        if ($product->id) {
            $this->productModel->delete($product);
        }

        return $response
            ->withHeader('Location', $this->basePath . '/admin/products')
            ->withStatus(302);
    }

    // ── CATEGORIES ────────────────────────────────────────────────────────────

    public function categories(Request $request, Response $response): Response
    {
        $html = $this->twig->render('admin/categories.html.twig', [
            'base_path'  => $this->basePath,
            'categories' => $this->categoryModel->findAll(),
        ]);

        $response->getBody()->write($html);
        return $response;
    }

    public function storeCategory(Request $request, Response $response): Response
    {
        $data = (array) $request->getParsedBody();
        $this->categoryModel->create(
            trim($data['name'] ?? ''),
            trim($data['image'] ?? ''),
        );

        return $response
            ->withHeader('Location', $this->basePath . '/admin/categories')
            ->withStatus(302);
    }

    public function updateCategory(Request $request, Response $response): Response
    {
        $data = (array) $request->getParsedBody();
        $cat  = $this->categoryModel->load((int) ($data['id'] ?? 0));

        if ($cat->id) {
            $cat->name  = trim($data['name'] ?? '');
            $cat->slug  = strtolower(str_replace(' ', '-', trim($data['name'] ?? '')));
            $cat->image = trim($data['image'] ?? '');
            $this->categoryModel->save($cat);
        }

        return $response
            ->withHeader('Location', $this->basePath . '/admin/categories')
            ->withStatus(302);
    }

    public function deleteCategory(Request $request, Response $response): Response
    {
        $data = (array) $request->getParsedBody();
        $cat  = $this->categoryModel->load((int) ($data['id'] ?? 0));
        if ($cat->id) {
            $this->categoryModel->delete($cat);
        }

        return $response
            ->withHeader('Location', $this->basePath . '/admin/categories')
            ->withStatus(302);
    }

    // ── PLUSH BASES ───────────────────────────────────────────────────────────

    public function bases(Request $request, Response $response): Response
    {
        $html = $this->twig->render('admin/bases.html.twig', [
            'base_path' => $this->basePath,
            'bases'     => $this->plushModel->getBases(),
        ]);
        $response->getBody()->write($html);
        return $response;
    }

    public function storeBase(Request $request, Response $response): Response
    {
        $data = (array) $request->getParsedBody();
        $this->plushModel->createBase(
            trim($data['name'] ?? ''),
            trim($data['species'] ?? ''),
            trim($data['color'] ?? ''),
            trim($data['image_path'] ?? ''),
            (float) ($data['base_price'] ?? 0),
            (int) ($data['sort_order'] ?? 0),
        );

        return $response->withHeader('Location', $this->basePath . '/admin/bases')->withStatus(302);
    }

    public function updateBase(Request $request, Response $response): Response
    {
        $data = (array) $request->getParsedBody();
        $base = $this->plushModel->getBaseById((int) ($data['id'] ?? 0));
        if ($base) {
            $base->name       = trim($data['name'] ?? '');
            $base->species    = trim($data['species'] ?? '');
            $base->color      = trim($data['color'] ?? '');
            $base->image_path = trim($data['image_path'] ?? '');
            $base->base_price = (float) ($data['base_price'] ?? 0);
            $base->sort_order = (int) ($data['sort_order'] ?? 0);
            $base->is_active  = isset($data['is_active']) ? 1 : 0;
            $this->plushModel->saveBase($base);
        }

        return $response->withHeader('Location', $this->basePath . '/admin/bases')->withStatus(302);
    }

    public function deleteBase(Request $request, Response $response): Response
    {
        $data = (array) $request->getParsedBody();
        $this->plushModel->deleteBase((int) ($data['id'] ?? 0));

        return $response->withHeader('Location', $this->basePath . '/admin/bases')->withStatus(302);
    }

    // ── PLUSH ACCESSORIES ─────────────────────────────────────────────────────

    public function accessories(Request $request, Response $response): Response
    {
        $html = $this->twig->render('admin/accessories.html.twig', [
            'base_path'   => $this->basePath,
            'accessories' => $this->plushModel->getAllAccessories(),
        ]);
        $response->getBody()->write($html);
        return $response;
    }

    public function storeAccessory(Request $request, Response $response): Response
    {
        $data = (array) $request->getParsedBody();
        $this->plushModel->createAccessory(
            trim($data['name'] ?? ''),
            trim($data['category'] ?? ''),
            trim($data['image_path'] ?? ''),
            (float) ($data['price'] ?? 0),
        );

        return $response->withHeader('Location', $this->basePath . '/admin/accessories')->withStatus(302);
    }

    public function updateAccessory(Request $request, Response $response): Response
    {
        $data = (array) $request->getParsedBody();
        $acc  = $this->plushModel->getAccessoryById((int) ($data['id'] ?? 0));
        if ($acc) {
            $acc->name       = trim($data['name'] ?? '');
            $acc->category   = trim($data['category'] ?? '');
            $acc->image_path = trim($data['image_path'] ?? '');
            $acc->price      = (float) ($data['price'] ?? 0);
            $acc->is_active  = isset($data['is_active']) ? 1 : 0;
            $this->plushModel->saveAccessory($acc);
        }

        return $response->withHeader('Location', $this->basePath . '/admin/accessories')->withStatus(302);
    }

    public function deleteAccessory(Request $request, Response $response): Response
    {
        $data = (array) $request->getParsedBody();
        $this->plushModel->deleteAccessory((int) ($data['id'] ?? 0));

        return $response->withHeader('Location', $this->basePath . '/admin/accessories')->withStatus(302);
    }

    // ── USERS ─────────────────────────────────────────────────────────────────

    public function users(Request $request, Response $response): Response
    {
        $search = trim($request->getQueryParams()['search'] ?? '');
        $users  = $this->userModel->findAll($search);

        $html = $this->twig->render('admin/users.html.twig', [
            'base_path' => $this->basePath,
            'users'     => $users,
            'search'    => $search,
        ]);
        $response->getBody()->write($html);
        return $response;
    }

    public function updateUser(Request $request, Response $response): Response
    {
        $data = (array) $request->getParsedBody();
        $user = $this->userModel->load((int) ($data['id'] ?? 0));

        if ($user->id) {
            $user->name  = trim($data['name'] ?? '');
            $user->email = trim($data['email'] ?? '');
            $user->role  = strtolower(trim($data['role'] ?? 'user')) === 'admin' ? 'admin' : 'user';
            $this->userModel->save($user);

            if (isset($_SESSION['user']) && $_SESSION['user']['id'] == $user->id) {
                $_SESSION['user']['name']  = $user->name;
                $_SESSION['user']['email'] = $user->email;
                $_SESSION['user']['role']  = $user->role;
            }
        }

        return $response
            ->withHeader('Location', $this->basePath . '/admin/users')
            ->withStatus(302);
    }

    public function deleteUser(Request $request, Response $response): Response
    {
        $data = (array) $request->getParsedBody();
        $user = $this->userModel->load((int) ($data['id'] ?? 0));
        if ($user->id && $user->id !== (int) ($_SESSION['user']['id'] ?? 0)) {
            $this->userModel->delete($user);
        }

        return $response
            ->withHeader('Location', $this->basePath . '/admin/users')
            ->withStatus(302);
    }

    // ── ORDERS ────────────────────────────────────────────────────────────────

    public function orders(Request $request, Response $response): Response
    {
        $orders = $this->orderModel->findAll();

        foreach ($orders as &$order) {
            $order['items'] = $this->orderModel->getItems((int) $order['id']);

            // best-effort customer info (orders table only stores user_id)
            if (!empty($order['user_id'])) {
                $u = $this->userModel->load((int) $order['user_id']);
                if ($u && !empty($u->name)) {
                    $order['user_name'] = $u->name;
                }
                if ($u && !empty($u->email)) {
                    $order['user_email'] = $u->email;
                }
            }
        }
        unset($order);

        $html = $this->twig->render('admin/orders.html.twig', [
            'base_path' => $this->basePath,
            'orders'    => $orders,
        ]);

        $response->getBody()->write($html);
        return $response;
    }

    public function updateOrderStatus(Request $request, Response $response): Response
    {
        $data = (array) $request->getParsedBody();

        $orderId = (int) ($data['order_id'] ?? 0);
        $status  = strtolower(trim((string) ($data['status'] ?? '')));

        $allowed = ['pending', 'paid', 'shipped', 'cancelled'];
        if ($orderId > 0 && in_array($status, $allowed, true)) {
            $this->orderModel->updateStatus($orderId, $status);
        }

        return $response
            ->withHeader('Location', $this->basePath . '/admin/orders?success=1')
            ->withStatus(302);
    }
}

