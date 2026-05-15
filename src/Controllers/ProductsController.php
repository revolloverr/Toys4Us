<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\ProductModel;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Twig\Environment;

/**
 * ProductsController
 *
 * Handles all HTTP actions related to Products (toys).
 */
class ProductsController
{
    public function __construct(
        private Environment $twig,
        private ProductModel $model,
        private string $basePath,
    ) {}

    /**
     * GET /products
     * List all products (toys).
     */
    public function index(Request $request, Response $response): Response
    {
        $params       = $request->getQueryParams();
        $categorySlug = $params['category'] ?? null;
        $minPrice     = isset($params['min_price']) && $params['min_price'] !== '' ? (float) $params['min_price'] : null;
        $maxPrice     = isset($params['max_price']) && $params['max_price'] !== '' ? (float) $params['max_price'] : null;
        $minRating    = isset($params['min_rating']) && $params['min_rating'] !== '' ? (float) $params['min_rating'] : null;
        $search       = isset($params['search']) && $params['search'] !== '' ? $params['search'] : null;

        $categoryId = null;

        if ($categorySlug) {
            $cat = \RedBeanPHP\R::findOne('category', 'slug = ?', [$categorySlug]);

            if ($cat) {
                $categoryId = (int) $cat->id;
            }
        }

        $products   = $this->model->findFiltered($categoryId, $minPrice, $maxPrice, $minRating, $search);
        $categories = \RedBeanPHP\R::findAll('category');

        $html = $this->twig->render('products.html.twig', [
            'base_path'  => $this->basePath,
            'app_lang'   => $_SESSION['lang'] ?? 'en',
            'products'   => $products,
            'categories' => $categories,
            'filters'    => [
                'category'   => $categorySlug,
                'min_price'  => $minPrice,
                'max_price'  => $maxPrice,
                'min_rating' => $minRating,
                'search'     => $search,
            ],
        ]);

        $response->getBody()->write($html);

        return $response;
    }

    /**
     * GET /products/search-json
     * AJAX live search — returns JSON.
     */
    public function searchJson(Request $request, Response $response): Response
    {
        $params = $request->getQueryParams();
        $search = isset($params['q']) && $params['q'] !== '' ? $params['q'] : '';

        $products = [];

        if ($search !== '') {
            $rows = $this->model->findFiltered(null, null, null, null, $search);

            foreach ($rows as $p) {
                $products[] = [
                    'id'          => (int) $p->id,
                    'name'        => $p->name,
                    'description' => $p->description,
                    'price'       => (float) $p->price,
                    'image'       => $p->image ?? '',
                    'rating'      => (float) ($p->rating ?? 0),
                    'slug'        => $p->slug ?? '',
                ];
            }
        }

        $payload = json_encode($products, JSON_UNESCAPED_UNICODE);

        $response->getBody()->write($payload);

        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * GET /products/{id}
     * Show a single product detail page.
     */
    public function show(Request $request, Response $response, array $args): Response
    {
        $product = $this->model->load((int) $args['id']);

        $html = $this->twig->render('product.html.twig', [
            'product'           => $product,
            'base_path'         => $this->basePath,
            'app_lang'          => $_SESSION['lang'] ?? 'en',
            'app_authenticated' => $_SESSION['authenticated'] ?? false,
        ]);

        $response->getBody()->write($html);

        return $response;
    }

    /**
     * =========================
     * REST API METHODS
     * =========================
     */

    /**
     * GET /api/products
     */
    public function apiIndex(Request $request, Response $response): Response
    {
        $products = $this->model->getAll();

        $productsArray = [];

        foreach ($products as $product) {
            $productsArray[] = [
                'id'          => (int) $product->id,
                'name'        => $product->name,
                'description' => $product->description,
                'price'       => (float) $product->price,
                'image'       => $product->image ?? '',
                'rating'      => (float) ($product->rating ?? 0),
                'slug'        => $product->slug ?? '',
                'stock'       => (int) ($product->stock ?? 0),
            ];
        }

        $response = $response->withHeader('Content-Type', 'application/json');

        $response->getBody()->write(json_encode([
            'success' => true,
            'products' => $productsArray
        ]));

        return $response;
    }

    /**
     * GET /api/products/{id}
     */
    public function apiGet(Request $request, Response $response, array $args): Response
    {
        $id = (int) $args['id'];

        $product = $this->model->load($id);

        if (!$product || !$product->id) {
            $response = $response->withStatus(404);

            $response->getBody()->write(json_encode([
                'success' => false,
                'error' => 'Product not found'
            ]));

            return $response->withHeader('Content-Type', 'application/json');
        }

        $response = $response->withHeader('Content-Type', 'application/json');

        $response->getBody()->write(json_encode([
            'success' => true,
            'product' => [
                'id'          => (int) $product->id,
                'name'        => $product->name,
                'description' => $product->description,
                'price'       => (float) $product->price,
                'image'       => $product->image ?? '',
                'rating'      => (float) ($product->rating ?? 0),
                'slug'        => $product->slug ?? '',
                'stock'       => (int) ($product->stock ?? 0),
            ]
        ]));

        return $response;
    }

    /**
     * POST /api/products
     */
    public function apiCreate(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();

        if (
            !isset($data['name']) ||
            !isset($data['description']) ||
            !isset($data['price'])
        ) {
            $response = $response->withStatus(400);

            $response->getBody()->write(json_encode([
                'success' => false,
                'error' => 'Missing required fields: name, description, price'
            ]));

            return $response->withHeader('Content-Type', 'application/json');
        }

        $product = $this->model->create($data);

        $response = $response
            ->withStatus(201)
            ->withHeader('Content-Type', 'application/json');

        $response->getBody()->write(json_encode([
            'success' => true,
            'product' => [
                'id'          => (int) $product->id,
                'name'        => $product->name,
                'description' => $product->description,
                'price'       => (float) $product->price,
                'image'       => $product->image ?? '',
                'rating'      => (float) ($product->rating ?? 0),
                'slug'        => $product->slug ?? '',
                'stock'       => (int) ($product->stock ?? 0),
            ]
        ]));

        return $response;
    }

    /**
     * PUT /api/products/{id}
     */
    public function apiUpdate(Request $request, Response $response, array $args): Response
    {
        $id = (int) $args['id'];

        $data = $request->getParsedBody();

        $product = $this->model->update($id, $data);

        if (!$product || !$product->id) {
            $response = $response->withStatus(404);

            $response->getBody()->write(json_encode([
                'success' => false,
                'error' => 'Product not found'
            ]));

            return $response->withHeader('Content-Type', 'application/json');
        }

        $response = $response->withHeader('Content-Type', 'application/json');

        $response->getBody()->write(json_encode([
            'success' => true,
            'product' => [
                'id'          => (int) $product->id,
                'name'        => $product->name,
                'description' => $product->description,
                'price'       => (float) $product->price,
                'image'       => $product->image ?? '',
                'rating'      => (float) ($product->rating ?? 0),
                'slug'        => $product->slug ?? '',
                'stock'       => (int) ($product->stock ?? 0),
            ]
        ]));

        return $response;
    }

    /**
     * DELETE /api/products/{id}
     */
    public function apiDelete(Request $request, Response $response, array $args): Response
    {
        $id = (int) $args['id'];

        $success = $this->model->delete($id);

        if (!$success) {
            $response = $response->withStatus(404);

            $response->getBody()->write(json_encode([
                'success' => false,
                'error' => 'Product not found'
            ]));

            return $response->withHeader('Content-Type', 'application/json');
        }

        $response = $response->withHeader('Content-Type', 'application/json');

        $response->getBody()->write(json_encode([
            'success' => true,
            'message' => 'Product deleted successfully'
        ]));

        return $response;
    }
}