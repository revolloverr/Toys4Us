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
        throw new \Slim\Exception\HttpNotFoundException($request);
    }
}