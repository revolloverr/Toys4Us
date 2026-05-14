<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\PlushModel;
use App\Models\ProductModel;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Twig\Environment;
use Stripe\Stripe;
use Stripe\Checkout\Session as StripeSession;

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

    public function showCart(Request $request, Response $response): Response
    {
        $cart = $_SESSION['cart'] ?? [];

        $enrichedCart = [];
        foreach ($cart as $key => $item) {
            if (($item['type'] ?? '') === 'customplush' && !empty($item['plush_id'])) {
                $plushDetails = $this->plushModel->getCustomPlushDetails((int) $item['plush_id']);
                if ($plushDetails) {
                    $item['plush_details'] = $plushDetails;
                }
            }
            $enrichedCart[$key] = $item;
        }

        $html = $this->twig->render('cart.html.twig', [
            'cart'              => $enrichedCart,
            'base_path'         => $this->basePath,
            'app_lang'          => $_SESSION['lang'] ?? 'en',
            'app_authenticated' => $_SESSION['authenticated'] ?? false,
        ]);

        $response->getBody()->write($html);
        return $response;
    }

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
     * Build a Stripe Checkout session and redirect to it.
     */
    public function checkout(Request $request, Response $response): Response
    {
        $cart = $_SESSION['cart'] ?? [];

        if (empty($_SESSION['user'])) {
            return $response
                ->withHeader('Location', $this->basePath . '/login')
                ->withStatus(302);
        }

        if (empty($cart)) {
            return $response
                ->withHeader('Location', $this->basePath . '/cart')
                ->withStatus(302);
        }

        Stripe::setApiKey($_ENV['STRIPE_SECRET_KEY']);

        $baseUrl = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . $this->basePath;

        // Build line items from cart
        $lineItems = [];
        foreach ($cart as $item) {
            $lineItems[] = [
                'price_data' => [
                    'currency'     => 'cad',
                    'unit_amount'  => (int) round((float) $item['price'] * 100), // cents
                    'product_data' => [
                        'name' => $item['name'],
                    ],
                ],
                'quantity' => (int) ($item['qty'] ?? 1),
            ];
        }

        $session = StripeSession::create([
            'payment_method_types' => ['card'],
            'line_items'           => $lineItems,
            'mode'                 => 'payment',
            'success_url'          => $baseUrl . '/checkout/success?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url'           => $baseUrl . '/cart',
        ]);

        return $response
            ->withHeader('Location', $session->url)
            ->withStatus(303);
    }

    /**
     * GET /checkout/success
     * Stripe redirects here after successful payment.
     */
    public function success(Request $request, Response $response): Response
    {
        $params    = $request->getQueryParams();
        $sessionId = $params['session_id'] ?? null;

        // Clear the cart
        $_SESSION['cart'] = [];

        // verify the session with Stripe
        $stripeSession = null;
        if ($sessionId) {
            Stripe::setApiKey($_ENV['STRIPE_SECRET_KEY']);
            try {
                $stripeSession = StripeSession::retrieve($sessionId);
            } catch (\Exception $e) {
                // non-fatal, just won't show order details
            }
        }

        $html = $this->twig->render('checkout/success.html.twig', [
            'base_path'      => $this->basePath,
            'stripe_session' => $stripeSession,
        ]);

        $response->getBody()->write($html);
        return $response;
    }
}