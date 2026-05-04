<?php

declare(strict_types=1);

namespace Darkwood\IaExceptionBundle\Routing;

use Darkwood\IaExceptionBundle\Controller\AiExceptionController;
use Symfony\Component\Config\Loader\Loader;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

final class RouteLoader extends Loader
{
    private bool $loaded = false;

    public function __construct(
        private readonly string $routePrefix,
        private readonly bool $asyncEnabled,
    ) {
        parent::__construct();
    }

    public function load(mixed $resource, ?string $type = null): RouteCollection
    {
        if ($this->loaded) {
            throw new \RuntimeException('Do not add the "darkwood_ia_exception_async" loader twice.');
        }
        $this->loaded = true;

        $routes = new RouteCollection();
        if (!$this->asyncEnabled) {
            return $routes;
        }

        $path = '/' . $this->routePrefix . '/{error_id}';
        $route = new Route(
            $path,
            ['_controller' => AiExceptionController::class],
            ['error_id' => '[a-f0-9]{16}'],
            [],
            '',
            [],
            ['GET']
        );
        $routes->add('darkwood_ia_exception_async', $route);

        return $routes;
    }

    public function supports(mixed $resource, ?string $type = null): bool
    {
        return $type === 'darkwood_ia_exception_async';
    }
}
