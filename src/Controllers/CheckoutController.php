<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\PlushModel;
use App\Models\ProductModel;
use App\Models\OrderModel;
use App\Models\CartModel;
use App\Models\AddressModel;

use App\Services\FlashService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Twig\Environment;

use RedBeanPHP\R;

use Stripe\Stripe;
use Stripe\Checkout\Session as StripeSession;

class CheckoutController
{
    private PlushModel $plushModel;
    private FlashService $flash;
    private OrderModel $orderModel;
    private CartModel $cartModel;
    private AddressModel $addressModel;

    public function __construct(
    private Environment $twig,
    private ProductModel $model,
    private string $basePath,
    ) {
        $this->plushModel   = new PlushModel();
        $this->flash        = new FlashService();
        $this->orderModel   = new OrderModel();
        $this->cartModel    = new CartModel();
        $this->addressModel = new AddressModel();
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

            // Persist to DB if logged in
            if (!empty($_SESSION['user']['id'])) {
                $this->cartModel->addItem((int) $_SESSION['user']['id'], $productId);
            }
        }

        $this->flash->success('flash.product_added_to_cart');
        return $response->withHeader('Location', $this->basePath . '/products')->withStatus(302);
    }

    public function removeFromCart(Request $request, Response $response, array $args): Response
    {
        $key = $args['key'] ?? '';
        if ($key !== '' && isset($_SESSION['cart'][$key])) {
            // Remove from DB if logged in
            if (!empty($_SESSION['user']['id']) && is_numeric($key)) {
                $this->cartModel->removeItem((int) $_SESSION['user']['id'], (int) $key);
            }
            unset($_SESSION['cart'][$key]);
        }

        $this->flash->warning('flash.product_removed_from_cart');
        return $response->withHeader('Location', $this->basePath . '/cart')->withStatus(302);
    }

    // POST /cart/update/{key}
    public function updateQty(Request $request, Response $response, array $args): Response
    {
        $key  = $args['key'] ?? '';
        $data = (array) $request->getParsedBody();
        $qty  = max(1, (int) ($data['qty'] ?? 1));

        if ($key !== '' && isset($_SESSION['cart'][$key])) {
            $_SESSION['cart'][$key]['qty'] = $qty;

            // Keep DB in sync for regular products (numeric keys)
            if (!empty($_SESSION['user']['id']) && is_numeric($key)) {
                $existing = \RedBeanPHP\R::findOne('cart_item', 'user_id = ? AND product_id = ?', [(int) $_SESSION['user']['id'], (int) $key]);
                if ($existing) {
                    $existing->quantity = $qty;
                    \RedBeanPHP\R::store($existing);
                }
            }
        }

        // Check if this is an AJAX request
        $isAjax = $request->getHeaderLine('X-Requested-With') === 'XMLHttpRequest' ||
                  $request->getHeaderLine('Content-Type') === 'application/json' ||
                  !empty($data['ajax']);

        if ($isAjax) {
            // Return JSON response for AJAX requests
            $cart = $_SESSION['cart'] ?? [];
            $itemTotal = 0;
            $grandTotal = 0;

            if (isset($cart[$key])) {
                $item = $cart[$key];
                $itemTotal = (float) $item['price'] * (int) ($item['qty'] ?? 1);
            }

            foreach ($cart as $cartItem) {
                $grandTotal += (float) $cartItem['price'] * (int) ($cartItem['qty'] ?? 1);
            }

            $response->getBody()->write(json_encode([
                'success' => true,
                'qty' => $qty,
                'itemTotal' => number_format($itemTotal, 2),
                'grandTotal' => number_format($grandTotal, 2),
                'key' => $key
            ]));

            return $response->withHeader('Content-Type', 'application/json');
        }

        return $response->withHeader('Location', $this->basePath . '/cart')->withStatus(302);
    }

    /**
     * GET /checkout/shipping
     * Show shipping address selection page
     */
    public function showShipping(Request $request, Response $response): Response
    {
        if (empty($_SESSION['user'])) {
            $this->flash->error('flash.login_required');
            return $response->withHeader('Location', $this->basePath . '/login')->withStatus(302);
        }

        $cart = $_SESSION['cart'] ?? [];
        if (empty($cart)) {
            $this->flash->warning('flash.cart_empty');
            return $response->withHeader('Location', $this->basePath . '/cart')->withStatus(302);
        }

        $userId = (int) $_SESSION['user']['id'];
        $addresses = $this->addressModel->findByUser($userId);

        $html = $this->twig->render('checkout/shipping.html.twig', [
            'cart'       => $cart,
            'addresses'  => $addresses,
            'base_path'  => $this->basePath,
            'app_lang'   => $_SESSION['lang'] ?? 'en',
        ]);

        $response->getBody()->write($html);
        return $response;
    }

    /**
     * POST /checkout/shipping
     * Process shipping address selection and proceed to payment
     */
    public function processShipping(Request $request, Response $response): Response
    {
        if (empty($_SESSION['user'])) {
            $this->flash->error('flash.login_required');
            return $response->withHeader('Location', $this->basePath . '/login')->withStatus(302);
        }

        $cart = $_SESSION['cart'] ?? [];
        if (empty($cart)) {
            $this->flash->warning('flash.cart_empty');
            return $response->withHeader('Location', $this->basePath . '/cart')->withStatus(302);
        }

        $data = (array) $request->getParsedBody();
        $addressId = (int) ($data['address_id'] ?? 0);

        $userId = (int) $_SESSION['user']['id'];

        // If "new address" selected, create it
            if ($addressId === 0) {
                $name = trim($data['name'] ?? '');
                $address = trim($data['address'] ?? '');
                $city = trim($data['city'] ?? '');
                $province = trim($data['province'] ?? '');
                $postalCode = trim($data['postal_code'] ?? '');
    
                if (empty($name) || empty($address) || empty($city) || empty($province) || empty($postalCode)) {
                    $this->flash->error('flash.shipping_address_required');
                    return $response->withHeader('Location', $this->basePath . '/checkout/shipping')->withStatus(302);
                }
    
                $addressId = $this->addressModel->create($userId, $name, $address, $city, $province, $postalCode);
                $this->flash->success('flash.address_saved');
            } else {
                // Verify the address belongs to the user
                $address = $this->addressModel->findByIdAndUser($addressId, $userId);
                if (!$address) {
                    $this->flash->error('flash.invalid_address');
                    return $response->withHeader('Location', $this->basePath . '/checkout/shipping')->withStatus(302);
                }
            }

        // Store selected address in session for checkout
        $_SESSION['checkout_address_id'] = $addressId;

        // Redirect to payment
        return $response->withHeader('Location', $this->basePath . '/checkout/payment')->withStatus(302);
    }

    /**
     * GET /checkout/payment
     * Show payment page (Stripe checkout)
     */
    public function showPayment(Request $request, Response $response): Response
    {
        if (empty($_SESSION['user'])) {
            $this->flash->error('flash.login_required');
            return $response->withHeader('Location', $this->basePath . '/login')->withStatus(302);
        }

        $cart = $_SESSION['cart'] ?? [];
        if (empty($cart)) {
            $this->flash->warning('flash.cart_empty');
            return $response->withHeader('Location', $this->basePath . '/cart')->withStatus(302);
        }

        $addressId = $_SESSION['checkout_address_id'] ?? null;
        if (empty($addressId)) {
            return $response->withHeader('Location', $this->basePath . '/checkout/shipping')->withStatus(302);
        }

        // Load address and ensure belongs to user
        $userId = (int) $_SESSION['user']['id'];
        $address = $this->addressModel->findByIdAndUser((int) $addressId, $userId);
        if (!$address) {
            $this->flash->error('flash.invalid_address');
            return $response->withHeader('Location', $this->basePath . '/checkout/shipping')->withStatus(302);
        }

        // Calculate total
        $total = 0;
        foreach ($cart as $item) {
            $total += (float) $item['price'] * (int) ($item['qty'] ?? 1);
        }

        $html = $this->twig->render('checkout/payment.html.twig', [
            'cart'       => $cart,
            'total'      => $total,
            'address'    => $address,
            'base_path'  => $this->basePath,
            'app_lang'   => $_SESSION['lang'] ?? 'en',
        ]);

        $response->getBody()->write($html);
        return $response;
    }

    /**
     * POST /checkout/payment
     * Process payment with Stripe
     */
    public function processPayment(Request $request, Response $response): Response
    {
        $cart = $_SESSION['cart'] ?? [];

        if (empty($_SESSION['user'])) {
            $this->flash->error('flash.login_to_checkout');
            return $response
                ->withHeader('Location', $this->basePath . '/login')
                ->withStatus(302);
        }

        if (empty($cart)) {
            $this->flash->warning('flash.cart_empty');
            return $response
                ->withHeader('Location', $this->basePath . '/cart')
                ->withStatus(302);
        }

        if (empty($_SESSION['checkout_address_id'])) {
            return $response->withHeader('Location', $this->basePath . '/checkout/shipping')->withStatus(302);
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
            'cancel_url'           => $baseUrl . '/checkout/payment',
        ]);

        return $response
            ->withHeader('Location', $session->url)
            ->withStatus(303);
    }

    /**
     * POST /cart/checkout
     * Build a Stripe Checkout session and redirect to it.
     */
    public function checkout(Request $request, Response $response): Response
    {
        $cart = $_SESSION['cart'] ?? [];

        if (empty($_SESSION['user'])) {
            $this->flash->error('flash.login_to_checkout');
            return $response
                ->withHeader('Location', $this->basePath . '/login')
                ->withStatus(302);
        }

        if (empty($cart)) {
            $this->flash->warning('flash.cart_empty');
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

        $stripeSession = null;
        if ($sessionId) {
            Stripe::setApiKey($_ENV['STRIPE_SECRET_KEY']);
            try {
                $stripeSession = StripeSession::retrieve($sessionId);
            } catch (\Exception $e) {
                // non-fatal
            }
        }

        // Save order to DB if we have a session and a logged-in user
        if ($stripeSession && !empty($_SESSION['user']['id']) && !empty($_SESSION['cart'])) {
            $userId = (int) $_SESSION['user']['id'];
            $cart   = $_SESSION['cart'];
            $total  = array_sum(array_map(fn($i) => (float)$i['price'] * (int)($i['qty'] ?? 1), $cart));
            $addressId = $_SESSION['checkout_address_id'] ?? null;

            $orderId = $this->orderModel->create($userId, $total, 'paid', $stripeSession->payment_intent, $addressId);

            foreach ($cart as $item) {
                $productId     = isset($item['type']) ? null : (int) $item['id'];
                $customPlushId = ($item['type'] ?? '') === 'customplush' ? (int) $item['plush_id'] : null;
                $qty           = (int) ($item['qty'] ?? 1);
                $price         = (float) $item['price'];
                $this->orderModel->addItem($orderId, $productId, $customPlushId, $qty, $price);
            }
        }

        // Clear the cart and checkout data
        $_SESSION['cart'] = [];
        unset($_SESSION['checkout_address_id']);

        $html = $this->twig->render('checkout/success.html.twig', [
            'base_path'      => $this->basePath,
            'stripe_session' => $stripeSession,
        ]);

        $response->getBody()->write($html);
        return $response;
    }
}