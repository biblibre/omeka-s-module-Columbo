<?php

namespace Columbo\Service\Controller\Admin;

use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Columbo\Controller\Admin\IndexController;

class IndexControllerFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        $fileSystem = null;

        if (class_exists('\AnyCloud\File\Store\Flysystem')) {
            $store = $services->get('Omeka\File\Store');
            if ($store instanceof \AnyCloud\File\Store\Flysystem) {
                // AnyCloud has no getter for its private "filesystem" property.
                $fileSystemGetter = \Closure::bind(
                    fn () => $this->filesystem,
                    $store,
                    \AnyCloud\File\Store\Flysystem::class
                );
                $fileSystem = $fileSystemGetter();
            }
        }

        return new IndexController(
            $services->get('Omeka\Connection'),
            $services->get('Omeka\Site\ThemeManager'),
            $services->get('Omeka\ModuleManager'),
            $services->get('Omeka\Acl'),
            $fileSystem
        );
    }
}
