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
        $bases       = $this->plushModel->getBases();
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

    // GET /build/{plush_id} — Edit an existing plush from cart
    public function edit(Request $request, Response $response, array $args): Response
    {
        $plushId = (int) ($args['plush_id'] ?? 0);
        $plush = $this->plushModel->getCustomPlushDetails($plushId);

        if (!$plush) {
            return $response
                ->withHeader('Location', $this->basePath . '/cart')
                ->withStatus(302);
        }

        $html = $this->twig->render('build.html.twig', [
            'base_path'      => $this->basePath,
            'app_lang'       => $_SESSION['lang'] ?? 'en',
            'bases'          => $this->plushModel->getBases(),
            'species'        => $this->plushModel->getSpecies(),
            'accessories'    => $this->plushModel->getAllAccessoriesGrouped(),
            'edit_plush_id'  => $plushId,
            'edit_plush'     => $plush,
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
        $editPlushId  = (int) ($data['edit_plush_id'] ?? 0);
        $userId       = $_SESSION['user']['id'] ?? null;

        // Calculate total price
        $base       = \RedBeanPHP\R::load('plush_base', $baseId);
        $totalPrice = (float) $base->base_price;

        foreach ($accessoryIds as $accId) {
            if ($accId > 0) {
                $acc = \RedBeanPHP\R::load('plush_accessory', $accId);
                $totalPrice += (float) $acc->price;
            }
        }

        // If editing existing plush, update it and update cart reference
        if ($editPlushId > 0) {
            $plush = \RedBeanPHP\R::load('custom_plush', $editPlushId);
            if ($plush->id) {
                $plush->base_id     = $baseId;
                $plush->name        = $name;
                $plush->total_price = $totalPrice;
                \RedBeanPHP\R::store($plush);

                // Re-save accessories
                \RedBeanPHP\R::exec('DELETE FROM custom_plush_accessory WHERE custom_plush_id = ?', [$editPlushId]);
                foreach ($accessoryIds as $accId) {
                    if ($accId > 0) {
                        $junction = \RedBeanPHP\R::dispense('custom_plush_accessory');
                        $junction->custom_plush_id = $editPlushId;
                        $junction->accessory_id    = $accId;
                        \RedBeanPHP\R::store($junction);
                    }
                }

                // Update cart session reference
                foreach ($_SESSION['cart'] as &$cartItem) {
                    if (($cartItem['type'] ?? '') === 'custom_plush' && ($cartItem['plush_id'] ?? 0) === $editPlushId) {
                        $cartItem['name']  = $name;
                        $cartItem['price'] = $totalPrice;
                        break;
                    }
                }
                unset($cartItem);

                return $response
                    ->withHeader('Location', $this->basePath . '/cart')
                    ->withStatus(302);
            }
        }

        // New plush
        $plushId = $this->plushModel->saveCustomPlush(
            $userId,
            $baseId,
            $name,
            $accessoryIds,
            $totalPrice
        );

        // Add to cart
        $_SESSION['cart'][] = [
            'type'     => 'custom_plush',
            'plush_id' => $plushId,
            'name'     => $name,
            'price'    => $totalPrice,
            'qty'      => 1,
        ];

        return $response
            ->withHeader('Location', $this->basePath . '/cart')
            ->withStatus(302);
    }
}