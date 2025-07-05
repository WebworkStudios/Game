<?php
declare(strict_types=1);

namespace Framework\Core;

use ReflectionException;

class Application
{
    private Container $container;

    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    /**
     * Run the application
     * @throws ReflectionException
     */
    public function run(): void
    {
        $router = $this->container->get('router');
        $router->handle();
    }
}