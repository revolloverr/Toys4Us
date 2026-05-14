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
        $plushName    = trim($data['plush_name'] ?? 'My Plush');
        $accessoryIds = array_map('intval', (array) ($data['accessory_ids'] ?? []));
        $editPlushId  = (int) ($data['edit_plush_id'] ?? 0);
        $userId = isset($_SESSION['user']['id']) ? (int) $_SESSION['user']['id'] : null;

        // Calculate total price
        $base = $this->plushModel->getBaseById($baseId);
        if (!$base) {
            return $response
                ->withHeader('Location', $this->basePath . '/build')
                ->withStatus(302);
        }

        $totalPrice = (float) $base->base_price;
        foreach ($accessoryIds as $accId) {
            if ($accId > 0) {
                $acc = $this->plushModel->getAccessoryById($accId);
                if ($acc) {
                    $totalPrice += (float) $acc->price;
                }
            }
        }

        // Save or update the custom plush
        if ($editPlushId > 0) {
            // Updating existing — remove old cart entry and replace
            $plushId = $editPlushId;
            $this->plushModel->updateCustomPlush($plushId, $baseId, $plushName, $accessoryIds, $totalPrice);

            // Update cart entry in session
            $cartKey = 'plush_' . $plushId;
            $_SESSION['cart'][$cartKey] = [
                'type'     => 'customplush',
                'plush_id' => $plushId,
                'name'     => $plushName,
                'price'    => $totalPrice,
                'qty'      => 1,
            ];
        } else {
            // New plush
            $plushId = $this->plushModel->saveCustomPlush(
                $userId,
                $baseId,
                $plushName,
                $accessoryIds,
                $totalPrice,
            );

            $cartKey = 'plush_' . $plushId;
            $_SESSION['cart'][$cartKey] = [
                'type'     => 'customplush',
                'plush_id' => $plushId,
                'name'     => $plushName,
                'price'    => $totalPrice,
                'qty'      => 1,
            ];
        }

        return $response
            ->withHeader('Location', $this->basePath . '/cart')
            ->withStatus(302);
    }
}