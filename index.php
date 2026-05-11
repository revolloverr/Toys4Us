<?php

declare(strict_types=1);

// ─── SESSIONS ────────────────────────────────────────────────────────────────
session_start();

use App\Controllers\AuthController;
use App\Controllers\CheckoutController;
use App\Controllers\ProductsController;
use App\Controllers\ProfileController;
use App\Controllers\PlushController;
use App\Middleware\AuthMiddleware;
use App\Middleware\MaintenanceMiddleware;
use App\Middleware\SecurityHeadersMiddleware;
use App\Models\ProductModel;
use App\Services\OtpService;



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

$model = new ProductModel();

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

$loader = new FilesystemLoader(__DIR__ . '/templates');
$twig   = new Environment($loader, [
    'cache'       => false,
    'auto_reload' => true,
]);

$twig->addGlobal('session', $_SESSION);
$twig->addGlobal('app_cart', $_SESSION['cart'] ?? []);

// ─── 3. I18N — symfony/translation ───────────────────────────────────────────

$translator = new Translator('en');
$translator->addLoader('array', new ArrayLoader());
$translator->addResource('array', require __DIR__ . '/translations/messages.en.php', 'en');
$translator->addResource('array', require __DIR__ . '/translations/messages.fr.php', 'fr');

$twig->addFunction(new TwigFunction('trans', function (string $key, array $params = []) use ($translator) {
    $locale = $_SESSION['lang'] ?? 'en';
    return $translator->trans($key, $params, null, $locale);
}));

// ─── 4. DEPENDENCY INJECTION CONTAINER ───────────────────────────────────────

$basePath = '/Toys4Us';

$container = new \DI\Container();
$container->set(Environment::class, $twig);
$container->set(ProductModel::class, $model);
$container->set(ProductsController::class, fn() => new ProductsController($twig, $model, $basePath));
$container->set(CheckoutController::class, fn() => new CheckoutController($twig, $model, $basePath));

$container->set(AuthController::class, fn() => new AuthController($twig, $basePath));

$container->set(ProfileController::class, fn() => new ProfileController($twig, $basePath));

$container->set(PlushController::class, fn() => new PlushController($twig, $basePath));

// ─── 5. APPLICATION ───────────────────────────────────────────────────────────

AppFactory::setContainer($container);
$app = AppFactory::create();

$app->setBasePath($basePath);
$app->addBodyParsingMiddleware();
$app->addRoutingMiddleware();
$app->addErrorMiddleware(true, true, true);

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
$app->get('/build',  [PlushController::class, 'index']);
$app->post('/build', [PlushController::class, 'save']);

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
$app->get('/products',            [ProductsController::class, 'index']);
$app->get('/products/{id}',       [ProductsController::class, 'show']);

// Cart routes (no auth required to add to cart)
$app->get('/cart',                [CheckoutController::class, 'showCart']);
$app->post('/cart/add/{id}',      [CheckoutController::class, 'addToCart']);
$app->post('/cart/checkout',      [CheckoutController::class, 'checkout']);

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

// ─── 10. PROFILE ROUTES ───────────────────────────────────────────────────────────

$app->get('/profile',                  [ProfileController::class, 'index']);
$app->post('/profile/edit',            [ProfileController::class, 'update']);
$app->post('/profile/change-password', [ProfileController::class, 'changePassword']);
$app->post('/profile/delete',          [ProfileController::class, 'delete']);


// ─── 11. RUN ──────────────────────────────────────────────────────────────────

$app->run();