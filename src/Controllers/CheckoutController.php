<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\PlushModel;
use App\Models\ProductModel;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Twig\Environment;

/**
 * CheckoutController
 *
 * Handles the shopping cart / checkout flow for the toy store.
 */
class CheckoutController
{
    private PlushModel $plushModel;

    public function __construct(
        private Environment $twig,
        private ProductModel $model,
        private string $basePath,
    ) {
        $this->plushModel = new PlushModel();
    }

    /**
     * GET /cart
     * Show the shopping cart with plush previews.
     */
    public function showCart(Request $request, Response $response): Response
    {
        $cart = $_SESSION['cart'] ?? [];

        // Enrich custom plush items with base + accessory data for preview
        $enrichedCart = [];
        foreach ($cart as $key => $item) {
            if (($item['type'] ?? '') === 'custom_plush' && !empty($item['plush_id'])) {
                $plushDetails = $this->plushModel->getCustomPlushDetails((int) $item['plush_id']);
                if ($plushDetails) {
                    $item['plush_details'] = $plushDetails;
                }
            }
            $enrichedCart[$key] = $item;
        }

        $html = $this->twig->render('cart.html.twig', [
            'cart'               => $enrichedCart,
            'base_path'          => $this->basePath,
            'app_lang'           => $_SESSION['lang'] ?? 'en',
            'app_authenticated'  => $_SESSION['authenticated'] ?? false,
        ]);

        $response->getBody()->write($html);
        return $response;
    }

    /**
     * POST /cart/add/{id}
     * Add a product to the cart.
     */
    public function addToCart(Request $request, Response $response, array $args): Response
    {
        $productId = (int) $args['id'];
        $product   = $this->model->load($productId);

        if ($product->id) {
            $_SESSION['cart'][$productId] = [
                'id'    => $product->id,
                'name'  => $product->name,
                'price' => $product->price,
                'qty'   => ($_SESSION['cart'][$productId]['qty'] ?? 0) + 1,
            ];
        }

        return $response
            ->withHeader('Location', $this->basePath . '/cart')
            ->withStatus(302);
    }

    /**
     * POST /cart/remove/{key}
     * Remove an item from the cart by its session key.
     */
    public function removeFromCart(Request $request, Response $response, array $args): Response
    {
        $key = $args['key'] ?? '';
        if ($key !== '' && isset($_SESSION['cart'][$key])) {
            unset($_SESSION['cart'][$key]);
        }

        return $response
            ->withHeader('Location', $this->basePath . '/cart')
            ->withStatus(302);
    }

    /**
     * POST /cart/checkout
     * Process the checkout (simulated).
     */
    public function checkout(Request $request, Response $response): Response
    {
        $_SESSION['cart'] = [];
        return $response
            ->withHeader('Location', $this->basePath . '/products')
            ->withStatus(302);
    }
}