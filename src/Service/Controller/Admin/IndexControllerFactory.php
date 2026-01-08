<?php

namespace Columbo\Service\Controller\Admin;

use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Columbo\Controller\Admin\IndexController;

class IndexControllerFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        $connection = $services->get('Omeka\Connection');
        $themeManager = $services->get('Omeka\Site\ThemeManager');

        $fileSystem = null;

        if (class_exists('\AnyCloud\File\Store\Flysystem'))
        {
            // hack to access private 'filesystem' attribute of AnyCloud because there is no getter.

            $flysystem = $services->get('Omeka\File\Store');
            if (isset($flysystem) && $flysystem instanceof \AnyCloud\File\Store\Flysystem) {
                $fileSystemGetter = \Closure::bind(
                    fn() => $this->filesystem,
                    $flysystem,
                    \AnyCloud\File\Store\Flysystem::class
                );
                $fileSystem = $fileSystemGetter();
            }
        }

        $controller = new IndexController($connection, $themeManager, $fileSystem);

        return $controller;
    }
}
