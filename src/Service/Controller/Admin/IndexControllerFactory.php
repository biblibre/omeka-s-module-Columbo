<?php

namespace Columbo\Service\Controller\Admin;

use Laminas\ServiceManager\Factory\FactoryInterface;
use Psr\Container\ContainerInterface;
use Columbo\Controller\Admin\IndexController;

class IndexControllerFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, ?array $options = null)
    {
        $connection = $services->get('Omeka\Connection');
        $themeManager = $services->get('Omeka\Site\ThemeManager');

        $controller = new IndexController($connection, $themeManager);

        return $controller;
    }
}
