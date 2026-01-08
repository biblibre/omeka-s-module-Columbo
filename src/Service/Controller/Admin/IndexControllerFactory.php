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

        if (class_exists('\AnyCloud\File\Store\AnyCloud'))
        {
            // hack to access private 'remoteFilesystem' attribute of AnyCloud because there is no getter.

            /* @var \AnyCloud\File\Store\AnyCloud $anyCloud */
            $anyCloud = $services->get('\AnyCloud\File\Store\AnyCloud');
            if (isset($anyCloud))
            {
                $remoteFileSystemGetter = \Closure::bind(
                    fn() => $this->remoteFilesystem,
                    $anyCloud,
                    \AnyCloud\File\Store\AnyCloud::class
                );
                $fileSystem = $remoteFileSystemGetter();
            }
        }

        $controller = new IndexController($connection, $themeManager, $fileSystem);

        return $controller;
    }
}
