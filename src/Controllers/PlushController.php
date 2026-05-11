<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\PlushModel;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Twig\Environment;

class PlushController
{
    private PlushModel $plushModel;

    public function __construct(
        private Environment $twig,
        private string $basePath,
    ) {
        $this->plushModel = new PlushModel();
    }

    // GET /build
    public function index(Request $request, Response $response): Response
    {
        $bases      = $this->plushModel->getBases();
        $accessories = $this->plushModel->getAllAccessoriesGrouped();

        $html = $this->twig->render('build.html.twig', [
            'base_path'   => $this->basePath,
            'app_lang'    => $_SESSION['lang'] ?? 'en',
            'bases'       => $this->plushModel->getBases(),
            'species'     => $this->plushModel->getSpecies(),
            'accessories' => $this->plushModel->getAllAccessoriesGrouped(),
        ]);
        $response->getBody()->write($html);
        return $response;
    }

    // POST /build
    public function save(Request $request, Response $response): Response
    {
        $data         = (array) $request->getParsedBody();
        $baseId       = (int) ($data['base_id'] ?? 0);
        $name         = trim($data['plush_name'] ?? 'My Plush');
        $accessoryIds = array_map('intval', (array) ($data['accessory_ids'] ?? []));
        $userId       = $_SESSION['user']['id'] ?? null;

        // Calculate total price
        $base       = \RedBeanPHP\R::load('plush_base', $baseId);
        $totalPrice = (float) $base->base_price;

        foreach ($accessoryIds as $accId) {
            $acc = \RedBeanPHP\R::load('plush_accessory', $accId);
            $totalPrice += (float) $acc->price;
        }

        $plushId = $this->plushModel->saveCustomPlush(
            $userId,
            $baseId,
            $name,
            $accessoryIds,
            $totalPrice
        );

        // Add to cart session for now
        $_SESSION['cart'][] = [
            'type'     => 'custom_plush',
            'plush_id' => $plushId,
            'name'     => $name,
            'price'    => $totalPrice,
        ];

        return $response
            ->withHeader('Location', $this->basePath . '/cart')
            ->withStatus(302);
    }
}