<?php
declare(strict_types=1);

namespace Framework\Core;

class Application
{
    private Container $container;

    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    /**
     * Run the application
     */
    public function run(): void
    {
        $router = $this->container->get('router');
        $router->handle();
    }
}