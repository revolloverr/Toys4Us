<?php

declare(strict_types=1);

// ─── SESSIONS ────────────────────────────────────────────────────────────────
session_start();

use App\Controllers\AuthController;
use App\Controllers\CheckoutController;
use App\Controllers\ProductsController;
use App\Controllers\ProfileController;
use App\Controllers\PlushController;
use App\Controllers\AdminController;

use App\Middleware\AuthMiddleware;
use App\Middleware\MaintenanceMiddleware;
use App\Middleware\AdminMiddleware;
use App\Middleware\SecurityHeadersMiddleware;

use App\Models\ProductModel;
use App\Models\PlushModel;
use App\Models\CategoryModel;
use App\Models\UserModel;

use App\Services\OtpService;
use App\Services\FlashService;

use Dotenv\Dotenv;

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;

use RedBeanPHP\R;

use Slim\Factory\AppFactory;

use Symfony\Component\Translation\Loader\ArrayLoader;
use Symfony\Component\Translation\Translator;

use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFunction;




require __DIR__ . '/vendor/autoload.php';



// ─── 1. DATABASE ──────────────────────────────────────────────────────────────

$dotenv = \Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

R::setup(
    'mysql:host=' . $_ENV['DB_HOST'] . ';dbname=' . $_ENV['DB_NAME'],
    $_ENV['DB_USER'],
    $_ENV['DB_PASS']
);
R::freeze(true);


# Ensure required tables exist (custom plush + addresses)
$tables = R::inspect();

if (!in_array('customplush', $tables)) {
    R::exec('CREATE TABLE customplush (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT DEFAULT NULL,
        base_id INT NOT NULL,
        name VARCHAR(255) NOT NULL DEFAULT "My Plush",
        total_price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        voice_message_path VARCHAR(255) DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
}

if (!in_array('customplushaccessory', $tables)) {
    R::exec('CREATE TABLE customplushaccessory (
        id INT AUTO_INCREMENT PRIMARY KEY,
        customplush_id INT NOT NULL,
        accessory_id INT NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
}

// Address table for saved shipping addresses
if (!in_array('address', $tables)) {
    R::exec('CREATE TABLE address (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        name VARCHAR(255) NOT NULL,
        address VARCHAR(255) NOT NULL,
        city VARCHAR(100) NOT NULL,
        province VARCHAR(100) NOT NULL,
        postal_code VARCHAR(20) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
}

// Add address_id column to order table if it doesn't exist (MySQL 8+ supports IF NOT EXISTS)
try {
    R::exec('ALTER TABLE `order` ADD COLUMN IF NOT EXISTS address_id INT DEFAULT NULL');
} catch (Exception $e) {
    // ignore if ALTER fails on older MySQL versions
}

R::freeze(true);

$model = new ProductModel();
$plushModel = new PlushModel();
$categoryModel = new CategoryModel();
$userModel = new UserModel();

if (R::count('product') === 0) {
    $toys = [
        ['name' => 'LEGO Star Wars Set', 'description' => 'Build your own starship with 800+ pieces', 'price' => 59.99, 'image' => 'https://placehold.co/300x200/7c3aed/ffffff?text=LEGO'],
        ['name' => 'Remote Control Car', 'description' => 'Fast RC buggy with rechargeable battery', 'price' => 34.99, 'image' => 'https://placehold.co/300x200/7c3aed/ffffff?text=RC+Car'],
        ['name' => 'Teddy Bear', 'description' => 'Soft plush bear, 18 inches tall', 'price' => 24.99, 'image' => 'https://placehold.co/300x200/7c3aed/ffffff?text=Teddy+Bear'],
        ['name' => 'Board Game Set', 'description' => 'Classic family board game collection', 'price' => 29.99, 'image' => 'https://placehold.co/300x200/7c3aed/ffffff?text=Board+Game'],
        ['name' => 'Art & Craft Kit', 'description' => '200-piece art set for creative kids', 'price' => 19.99, 'image' => 'https://placehold.co/300x200/7c3aed/ffffff?text=Art+Kit'],
        ['name' => 'Puzzle 1000pc', 'description' => 'Beautiful landscape jigsaw puzzle', 'price' => 14.99, 'image' => 'https://placehold.co/300x200/7c3aed/ffffff?text=Puzzle'],
    ];
    foreach ($toys as $toy) {
        $model->create($toy['name'], $toy['description'], $toy['price'], $toy['image']);
    }
}

// ─── 2. TEMPLATE ENGINE ───────────────────────────────────────────────────────
$basePath = '';

$loader = new FilesystemLoader(__DIR__ . '/templates');
$twig   = new Environment($loader, [
    'cache'       => false,
    'auto_reload' => true,
]);


$twig->addGlobal('session', $_SESSION);
$twig->addGlobal('app_cart', $_SESSION['cart'] ?? []);



$twig->addGlobal('base_path', $basePath);
$twig->addFunction(new TwigFunction('flash_messages', function () {
    $flash = new FlashService();
    return $flash->getMessages();
}));

// ─── 3. I18N — symfony/translation ───────────────────────────────────────────

$translator = new Translator('en');
$translator->addLoader('array', new ArrayLoader());
$translator->addResource('array', require __DIR__ . '/translations/messages.en.php', 'en');
$translator->addResource('array', require __DIR__ . '/translations/messages.fr.php', 'fr');

$twig->addFunction(new TwigFunction('trans', function (string $key, array $params = []) use ($translator) {
    $locale = $_SESSION['lang'] ?? 'en';
    $translated = $translator->trans($key, $params, null, $locale);
    // If translator returns the key unchanged, treat as missing and return null so templates can use ?? fallback
    return $translated === $key ? null : $translated;
}));

// ─── 4. DEPENDENCY INJECTION CONTAINER ───────────────────────────────────────



$container = new \DI\Container();
$container->set(Environment::class, $twig);
$container->set(ProductModel::class, $model);
$container->set(ProductsController::class, fn() => new ProductsController($twig, $model, $basePath));
$container->set(CheckoutController::class, fn() => new CheckoutController($twig, $model, $basePath));

$container->set(AuthController::class, fn() => new AuthController($twig, $basePath));

$container->set(ProfileController::class, fn() => new ProfileController($twig, $basePath));

$container->set(PlushController::class, fn() => new PlushController($twig, $basePath));

$container->set(AdminController::class, fn() => new AdminController(
    $twig,
    $basePath,
    $plushModel,
    $model,
    $categoryModel,
    $userModel,
));

// ─── 5. APPLICATION ───────────────────────────────────────────────────────────

AppFactory::setContainer($container);
$app = AppFactory::create();

$app->setBasePath($basePath);
$app->addBodyParsingMiddleware();
$app->addRoutingMiddleware();
$errorMiddleware = $app->addErrorMiddleware(true, true, true);
$errorMiddleware->setErrorHandler(
    \Slim\Exception\HttpNotFoundException::class,
    function ($request, $exception) use ($twig, $basePath) {
        $response = new \Slim\Psr7\Response();
        $html = $twig->render('errors/404.html.twig', [
            'base_path' => $basePath,
        ]);
        $response->getBody()->write($html);
        return $response->withStatus(404);
    }
);

// ─── 6. MIDDLEWARE ────────────────────────────────────────────────────────────

$logFile = __DIR__ . '/var/app.log';

$loggerMiddleware = function (Request $request, RequestHandler $handler) use ($logFile) {
    $start  = microtime(true);
    $method = $request->getMethod();
    $path   = $request->getUri()->getPath();

    $response = $handler->handle($request);

    $status  = $response->getStatusCode();
    $elapsed = round((microtime(true) - $start) * 1000);

    $line = sprintf(
        "[%s] %-6s %-25s → %d  (%dms)\n",
        date('Y-m-d H:i:s'), $method, $path, $status, $elapsed
    );

    file_put_contents($logFile, $line, FILE_APPEND);

    return $response;
};

$app->add($loggerMiddleware);
$app->add(new MaintenanceMiddleware(
    flagFile:        __DIR__ . '/var/maintenance.flag',
    responseFactory: $app->getResponseFactory()
));
$app->add(new SecurityHeadersMiddleware());

// REST API Routes
$app->get('/api/products', [ProductsController::class, 'apiIndex']);
$app->get('/api/products/{id}', [ProductsController::class, 'apiGet']);
$app->post('/api/products', [ProductsController::class, 'apiCreate']);
$app->put('/api/products/{id}', [ProductsController::class, 'apiUpdate']);
$app->delete('/api/products/{id}', [ProductsController::class, 'apiDelete']);

// ─── 7. HTML ROUTES ───────────────────────────────────────────────────────────

// Home page
$app->get('/', function (Request $request, Response $response) use ($twig, $basePath, $model) {
    $allProducts = $model->findAll();
    $featured = array_slice($allProducts, 0, 4);
    $html = $twig->render('home.html.twig', [
        'app_lang' => $_SESSION['lang'] ?? 'en',
        'app_cart' => $_SESSION['cart'] ?? [],
        'base_path' => $basePath,
        'featured' => $featured,
    ]);
    $response->getBody()->write($html);
    return $response;
});

// Build a Toy page
$app->get('/build',           [PlushController::class, 'index']);
$app->get('/build/{plush_id}', [PlushController::class, 'edit']);
$app->post('/build',          [PlushController::class, 'save']);

// About Us page
$app->get('/about', function (Request $request, Response $response) use ($twig, $basePath) {
    $html = $twig->render('about.html.twig', [
        'app_lang' => $_SESSION['lang'] ?? 'en',
        'app_cart' => $_SESSION['cart'] ?? [],
        'base_path' => $basePath,
    ]);
    $response->getBody()->write($html);
    return $response;
});

// Public product routes (no auth required to browse)
$app->get('/products',                     [ProductsController::class, 'index']);
$app->get('/products/search-json',         [ProductsController::class, 'searchJson']);
$app->get('/products/{id}',                [ProductsController::class, 'show']);

# Cart routes (no auth required to add to cart)
$app->get('/cart',                [CheckoutController::class, 'showCart']);
$app->post('/cart/add/{id}',      [CheckoutController::class, 'addToCart']);
$app->post('/cart/remove/{key}',  [CheckoutController::class, 'removeFromCart']);
$app->post('/cart/update/{key}',  [CheckoutController::class, 'updateQty']);
$app->post('/cart/checkout',      [CheckoutController::class, 'checkout']);
$app->get('/checkout/success',    [CheckoutController::class, 'success']);

// Checkout: shipping + payment
$app->get('/checkout/shipping',   [CheckoutController::class, 'showShipping']);
$app->post('/checkout/shipping',  [CheckoutController::class, 'processShipping']);
$app->get('/checkout/payment',    [CheckoutController::class, 'showPayment']);
$app->post('/checkout/payment',   [CheckoutController::class, 'processPayment']);

// ─── 8. LANGUAGE ROUTE ────────────────────────────────────────────────────────

$app->get('/lang/{locale}', function (Request $request, Response $response, array $args) use ($basePath) {
    $allowed = ['en', 'fr'];

    if (in_array($args['locale'], $allowed)) {
        $_SESSION['lang'] = $args['locale'];
    }
    // get the old page we were on
    $referer = $_SERVER['HTTP_REFERER'] ?? '/';
    return $response->withHeader('Location', $referer)->withStatus(302);
});

// ─── 9. AUTH ROUTES ───────────────────────────────────────────────────────────

$app->get('/login',      [AuthController::class, 'showLogin']);
$app->get('/register',   [AuthController::class, 'showRegister']);
$app->post('/login',     [AuthController::class, 'login']);
$app->post('/register',  [AuthController::class, 'register']);
$app->post('/logout',    [AuthController::class, 'logout']);

// TOTP 2FA verification routes (between password and full session)
$app->get('/totp/verify',       [AuthController::class, 'showTotpVerify']);
$app->post('/totp/verify',      [AuthController::class, 'verifyTotp']);
$app->post('/totp/skip',        [AuthController::class, 'skipTotpSetup']);

// ─── 10. PROFILE ROUTES ───────────────────────────────────────────────────────────

$app->get('/profile',                  [ProfileController::class, 'index']);
$app->post('/profile/edit',            [ProfileController::class, 'update']);
$app->post('/profile/change-password', [ProfileController::class, 'changePassword']);
$app->post('/profile/delete',          [ProfileController::class, 'delete']);
$app->post('/profile/address/add',     [ProfileController::class, 'addAddress']);
$app->post('/profile/address/delete',  [ProfileController::class, 'deleteAddress']);

// TOTP 2FA management in profile
$app->post('/profile/totp/setup',      [ProfileController::class, 'setupTotp']);
$app->post('/profile/totp/confirm',    [ProfileController::class, 'confirmTotp']);
$app->post('/profile/totp/disable',    [ProfileController::class, 'disableTotp']);

// Admin routes — protected by AdminMiddleware
$app->group('/admin', function ($group) {
    $group->get('',                      [AdminController::class, 'index']);

    // Products CRUD
    $group->get('/products',             [AdminController::class, 'products']);
    $group->post('/products/store',      [AdminController::class, 'storeProduct']);
    $group->post('/products/update',     [AdminController::class, 'updateProduct']);
    $group->post('/products/delete',     [AdminController::class, 'deleteProduct']);

    // Categories CRUD
    $group->get('/categories',           [AdminController::class, 'categories']);
    $group->post('/categories/store',    [AdminController::class, 'storeCategory']);
    $group->post('/categories/update',   [AdminController::class, 'updateCategory']);
    $group->post('/categories/delete',   [AdminController::class, 'deleteCategory']);

    // Plush Bases CRUD
    $group->get('/bases',                [AdminController::class, 'bases']);
    $group->post('/bases/store',         [AdminController::class, 'storeBase']);
    $group->post('/bases/update',        [AdminController::class, 'updateBase']);
    $group->post('/bases/delete',        [AdminController::class, 'deleteBase']);

    // Plush Accessories CRUD
    $group->get('/accessories',          [AdminController::class, 'accessories']);
    $group->post('/accessories/store',   [AdminController::class, 'storeAccessory']);
    $group->post('/accessories/update',  [AdminController::class, 'updateAccessory']);
    $group->post('/accessories/delete',  [AdminController::class, 'deleteAccessory']);

    // Admin User Manager
    $group->get('/users',              [AdminController::class, 'users']);
    $group->post('/users/update',      [AdminController::class, 'updateUser']);
    $group->post('/users/delete',      [AdminController::class, 'deleteUser']);

})->add(new AdminMiddleware());

// ─── 11. RUN ──────────────────────────────────────────────────────────────────

$app->run();