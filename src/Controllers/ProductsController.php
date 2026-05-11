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
 * Each public method maps to one route defined in index.php.
 *
 * This is the Controller layer of MVC:
 *   - It receives the HTTP Request
 *   - Delegates data work to the Model (ProductModel)
 *   - Passes data to the View (Twig templates)
 *   - Returns an HTTP Response
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
        $products = $this->model->findAll();

        $html = $this->twig->render('products.html.twig', [
            'products'           => $products,
            'base_path'          => $this->basePath,
            'app_lang'           => $_SESSION['lang'] ?? 'en',
            'app_authenticated'  => $_SESSION['authenticated'] ?? false,
        ]);

        $response->getBody()->write($html);

        return $response;
    }

    /**
     * GET /products/{id}
     * Show a single product detail page.
     */
    public function show(Request $request, Response $response, array $args): Response
    {
        $product = $this->model->load((int) $args['id']);

        $html = $this->twig->render('product.html.twig', [
            'product'            => $product,
            'base_path'          => $this->basePath,
            'app_lang'           => $_SESSION['lang'] ?? 'en',
            'app_authenticated'  => $_SESSION['authenticated'] ?? false,
        ]);

        $response->getBody()->write($html);

        return $response;
    }
}