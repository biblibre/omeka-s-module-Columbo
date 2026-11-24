<?php

namespace Columbo\Service\Controller\Admin;

use Laminas\ServiceManager\Factory\FactoryInterface;
use Psr\Container\ContainerInterface;
use Columbo\Controller\Admin\IndexController;

class IndexControllerFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, ?array $options = null)
    {
        $config = $services->get('Config');

        return new IndexController(
            $services->get('Omeka\Connection'),
            $services->get('Omeka\Site\ThemeManager'),
            $services->get('Omeka\ModuleManager'),
            $services->get('Omeka\Acl'),
            $this->getStorage($services),
            array_keys($config['thumbnails']['types'] ?? [])
        );
    }

    /**
     * Describes the file store in use: Omeka\File\Store is the local store by
     * default, or the store of AnyCloud, set from its settings or from the
     * service aliases of config/local.config.php.
     */
    protected function getStorage(ContainerInterface $services): array
    {
        $store = $services->get('Omeka\File\Store');

        if ($store instanceof \Omeka\File\Store\Local) {
            return [
                'type' => 'local',
                'name' => 'Local',
                'path' => rtrim($store->getLocalPath(''), '/'),
            ];
        }

        if (class_exists('AnyCloud\File\Store\Flysystem') && $store instanceof \AnyCloud\File\Store\Flysystem) {
            // AnyCloud has no getter for its protected "filesystem" property.
            $fileSystem = \Closure::bind(
                fn () => $this->filesystem,
                $store,
                \AnyCloud\File\Store\Flysystem::class
            )();
            $adapter = $services->get('Omeka\Settings')->get('anycloud_adapter');
            $provider = is_array($adapter) && !empty($adapter['adapter']) && $adapter['adapter'] !== 'default'
                ? $adapter['adapter']
                : (new \ReflectionClass($store))->getShortName();
            return [
                'type' => 'anycloud',
                'name' => 'AnyCloud (' . $provider . ')',
                'fileSystem' => $fileSystem,
            ];
        }

        return [
            'type' => 'unsupported',
            'name' => get_class($store),
        ];
    }
}
