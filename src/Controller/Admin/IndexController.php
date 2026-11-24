<?php

namespace Columbo\Controller\Admin;

use Doctrine\DBAL\Connection;
use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\ViewModel;
use Omeka\Entity\Item;
use Omeka\Entity\ItemSet;
use Omeka\Entity\Media;
use Omeka\Entity\ValueAnnotation;
use Omeka\Module\Manager as ModuleManager;
use Omeka\Permissions\Acl;
use Omeka\Site\Theme\Manager as ThemeManager;

class IndexController extends AbstractActionController
{
    /**
     * Tabs, in display order. "disk" is labelled "Storage information" but
     * keeps its id so that the URLs of the former pages remain valid.
     */
    public const SECTIONS = ['sites', 'resources', 'metadata', 'users', 'modules', 'disk'];

    protected Connection $connection;
    protected ThemeManager $themeManager;
    protected ModuleManager $moduleManager;
    protected Acl $acl;

    /**
     * File store in use: type (local, anycloud or unsupported), name, and
     * path (local) or fileSystem (anycloud).
     */
    protected array $storage;

    /**
     * Top directories of the file store: originals, thumbnails, assets.
     */
    protected array $storageDirectories;

    public function __construct(
        Connection $connection,
        ThemeManager $themeManager,
        ModuleManager $moduleManager,
        Acl $acl,
        array $storage,
        array $thumbnailTypes
    ) {
        $this->connection = $connection;
        $this->themeManager = $themeManager;
        $this->moduleManager = $moduleManager;
        $this->acl = $acl;
        $this->storage = $storage;
        $this->storageDirectories = array_values(array_unique(array_merge(['original'], $thumbnailTypes, ['asset'])));
    }

    /**
     * Tab bar and empty panels, filled by asset/js/columbo.js from contentAction().
     */
    public function indexAction()
    {
        $section = $this->params()->fromRoute('section', self::SECTIONS[0]);
        if (!in_array($section, self::SECTIONS, true)) {
            $section = self::SECTIONS[0];
        }

        $view = new ViewModel;
        $view->setVariable('sections', self::SECTIONS);
        $view->setVariable('activeSection', $section);
        return $view;
    }

    /**
     * HTML fragment of a tab, without layout.
     */
    public function contentAction()
    {
        $section = $this->params()->fromRoute('section');
        if (!in_array($section, self::SECTIONS, true)) {
            return $this->notFoundAction();
        }

        // getSitesData(), getResourcesData()…
        $data = $this->{'get' . ucfirst($section) . 'Data'}();

        $view = new ViewModel($data);
        $view->setTemplate('columbo/admin/index/section/' . $section);
        $view->setTerminal(true);

        $this->getResponse()->getHeaders()->addHeaderLine('Cache-Control', 'no-store');

        return $view;
    }

    /**
     * Directory or bucket size as JSON, requested separately because it is slow.
     */
    public function sizeAction()
    {
        $target = $this->params()->fromRoute('target');

        $response = $this->getResponse();
        $response->getHeaders()
            ->addHeaderLine('Content-Type', 'application/json')
            ->addHeaderLine('Cache-Control', 'no-store');

        try {
            $bytes = $target === 'omeka'
                ? $this->getDirectorySize(OMEKA_PATH)
                : (in_array($target, $this->storageDirectories, true) ? $this->getStorageSize($target) : null);
        } catch (\Throwable $e) {
            $this->logger()->err(sprintf('Columbo: cannot calculate the size of "%s": %s', $target, $e->getMessage()));
            $response->setStatusCode(500);
            $response->setContent(json_encode(['error' => $e->getMessage()]));
            return $response;
        }

        if ($bytes === null) {
            $response->setStatusCode(404);
            $response->setContent(json_encode(['error' => 'Unavailable']));
            return $response;
        }

        $response->setContent(json_encode([
            'bytes' => $bytes,
            'readable' => $this->bytesToReadable($bytes),
        ]));
        return $response;
    }

    /**
     * Export of every statistic in a single CSV row (format kept from 0.1.x).
     */
    public function downloadAction()
    {
        $sites = $this->getSitesData();
        $resources = $this->getResourcesData();
        $metadata = $this->getMetadataData();
        $users = $this->getUsersData();
        $disk = $this->getDiskData();

        $export = [];

        $labels = [ItemSet::class => 'itemSets', Item::class => 'items', Media::class => 'media'];
        foreach ($labels as $type => $prefix) {
            $counts = $resources['resourceCounts'][$type];
            $export['private' . ucfirst($prefix) . 'Count'] = $counts['private'];
            $export['public' . ucfirst($prefix) . 'Count'] = $counts['public'];
            $export[$prefix . 'WithoutTemplateCount'] = $counts['withoutTemplate'];
            $export[$prefix . 'WithoutClassCount'] = $counts['withoutClass'];
            $export[$prefix . 'Count'] = $counts['total'];
        }

        foreach ($resources['itemStats'] as $key => $value) {
            $export[$key] = $value;
        }

        $i = 0;
        foreach ($resources['fileTypes'] as $fileType) {
            $export['perFileTypeMediaCount' . $i . '_fileType'] = $fileType['fileType'];
            $export['perFileTypeMediaCount' . $i . '_total'] = $fileType['total'];
            ++$i;
        }

        $siteColumns = ['title', 'itemCount', 'itemSetCount', 'pageCount', 'viewerCount', 'editorCount', 'adminCount'];
        foreach ($sites['sites'] as $index => $site) {
            foreach ($siteColumns as $column) {
                $export['site' . $index . '_' . $column] = $site[$column];
            }
            $export['site' . $index . '_mediaSize'] = $this->bytesToReadable($site['mediaSize']);
        }

        $filesSize = 0;
        $assetsSize = 0;
        foreach ($this->storageDirectories as $directory) {
            $size = (int) $this->getStorageSize($directory);
            $filesSize += $size;
            if ($directory === 'asset') {
                $assetsSize = $size;
            }
        }
        $export['totalMedia'] = $this->bytesToReadable($disk['mediaSize']);
        $export['totalAssets'] = $this->bytesToReadable($assetsSize);
        $export['totalFiles'] = $this->bytesToReadable($filesSize);
        $export['total'] = $this->bytesToReadable($disk['mediaSize'] + $filesSize);
        $export['omeka'] = $this->bytesToReadable($this->getDirectorySize(OMEKA_PATH));

        foreach ($sites['themes'] as $index => $theme) {
            $export['theme' . $index . '_themeName'] = $theme['name'];
            $export['theme' . $index . '_themeVersion'] = $theme['version'];
            $export['theme' . $index . '_themeSiteCount'] = $theme['siteCount'];
        }

        $usageColumns = ['itemSetCount', 'itemCount', 'mediaCount'];
        foreach (['property' => $metadata['properties'], 'class' => $metadata['classes']] as $prefix => $rows) {
            foreach ($rows as $index => $row) {
                $export[$prefix . $index . '_term'] = $row['term'];
                foreach ($usageColumns as $column) {
                    $export[$prefix . $index . '_' . $column] = $row[$column];
                }
            }
        }

        foreach ($resources['resourceTemplates'] as $index => $row) {
            $export['resourceTemplate' . $index . '_label'] = $row['label'];
            foreach ($usageColumns as $column) {
                $export['resourceTemplate' . $index . '_' . $column] = $row[$column];
            }
        }

        foreach ($resources['vocabularies'] as $index => $row) {
            $export['vocabulary' . $index . '_prefix'] = $row['prefix'];
            $export['vocabulary' . $index . '_classCount'] = $row['classCount'];
            $export['vocabulary' . $index . '_propertyCount'] = $row['propertyCount'];
        }

        foreach ($metadata['valueResourceTypes'] as $index => $row) {
            $export['valueResourceType' . $index . '_resource_type'] = $row['resource_type'];
            $export['valueResourceType' . $index . '_count'] = $row['count'];
        }

        $export['valueAnnotationCount'] = $metadata['valueAnnotationCount'];

        foreach ($users['roles'] as $index => $row) {
            $export['role' . $index . '_role'] = $row['role'];
            $export['role' . $index . '_inactiveCount'] = $row['inactiveCount'];
            $export['role' . $index . '_activeCount'] = $row['activeCount'];
        }

        $fp = fopen('php://temp', 'r+');
        fputcsv($fp, array_keys($export));
        fputcsv($fp, array_values($export));
        rewind($fp);
        $csv = stream_get_contents($fp);
        fclose($fp);

        $response = $this->getResponse();
        $response->setContent($csv);
        $response->getHeaders()
            ->addHeaderLine('Content-Type', 'text/csv; charset=utf-8')
            ->addHeaderLine('Content-Disposition', 'attachment; filename="columbo.csv"');

        return $response;
    }

    protected function getSitesData(): array
    {
        $conn = $this->connection;

        // One correlated subquery per counter: avoids the cartesian product
        // produced by chaining LEFT JOINs + COUNT(DISTINCT).
        $sites = $conn->fetchAllAssociative(<<<'SQL'
            SELECT
                site.id,
                site.slug,
                site.title,
                (SELECT COUNT(*) FROM item_site WHERE item_site.site_id = site.id) itemCount,
                (SELECT COUNT(*) FROM site_item_set WHERE site_item_set.site_id = site.id) itemSetCount,
                (SELECT COUNT(*) FROM site_page WHERE site_page.site_id = site.id) pageCount,
                (SELECT COUNT(*) FROM site_permission sp WHERE sp.site_id = site.id AND sp.role = 'viewer') viewerCount,
                (SELECT COUNT(*) FROM site_permission sp WHERE sp.site_id = site.id AND sp.role = 'editor') editorCount,
                (SELECT COUNT(*) FROM site_permission sp WHERE sp.site_id = site.id AND sp.role = 'admin') adminCount,
                (SELECT COALESCE(SUM(media.size), 0)
                    FROM item_site INNER JOIN media ON media.item_id = item_site.item_id
                    WHERE item_site.site_id = site.id) mediaSize
            FROM site
            ORDER BY site.title
            SQL);

        foreach ($sites as &$site) {
            $site['mediaSizeReadable'] = $this->bytesToReadable($site['mediaSize']);
        }
        unset($site);

        $siteCountByTheme = $conn->fetchAllKeyValue('SELECT theme, COUNT(*) FROM site GROUP BY theme');
        $themes = [];
        foreach ($this->themeManager->getThemes() as $theme) {
            $themes[] = [
                'name' => $theme->getName(),
                'version' => $theme->getIni('version'),
                'siteCount' => (int) ($siteCountByTheme[$theme->getId()] ?? 0),
            ];
        }

        return [
            'sites' => $sites,
            'themes' => $themes,
        ];
    }

    protected function getResourcesData(): array
    {
        $conn = $this->connection;
        $types = [ItemSet::class, Item::class, Media::class];

        // All visibility / template / class counters in a single query.
        $rows = $conn->fetchAllAssociativeIndexed(<<<'SQL'
            SELECT
                resource_type,
                COUNT(*) total,
                SUM(is_public = 1) public,
                SUM(is_public = 0) private,
                SUM(resource_template_id IS NULL) withoutTemplate,
                SUM(resource_class_id IS NULL) withoutClass
            FROM resource
            WHERE resource_type IN (?, ?, ?)
            GROUP BY resource_type
            SQL, $types);

        $resourceCounts = [];
        foreach ($types as $type) {
            $resourceCounts[$type] = array_map('intval', $rows[$type] ?? [
                'total' => 0, 'public' => 0, 'private' => 0, 'withoutTemplate' => 0, 'withoutClass' => 0,
            ]);
        }

        $itemsCount = $resourceCounts[Item::class]['total'];
        $itemsWithMediaCount = (int) $conn->fetchOne('SELECT COUNT(DISTINCT item_id) FROM media');
        $itemsWithItemSetCount = (int) $conn->fetchOne('SELECT COUNT(DISTINCT item_id) FROM item_item_set');
        [$min, $max, $avg] = $conn->fetchNumeric(<<<'SQL'
            SELECT MIN(cnt), MAX(cnt), AVG(cnt)
            FROM (SELECT COUNT(*) cnt FROM media GROUP BY media.item_id) s
            SQL);

        $itemStats = [
            'itemsWithMediaCount' => $itemsWithMediaCount,
            'itemsWithoutMediaCount' => $itemsCount - $itemsWithMediaCount,
            'itemsWithoutItemSetCount' => $itemsCount - $itemsWithItemSetCount,
            'itemsWithItemSetCount' => $itemsWithItemSetCount,
            'mediaCountByItemMin' => (int) $min,
            'mediaCountByItemMax' => (int) $max,
            'mediaCountByItemAvg' => (float) $avg,
        ];

        $itemSets = $conn->fetchAllAssociative(<<<'SQL'
            SELECT
                resource.id,
                resource.title,
                resource.is_public,
                item_set.is_open,
                (SELECT COUNT(*) FROM item_item_set iis WHERE iis.item_set_id = resource.id) itemCount,
                (SELECT COUNT(*) FROM item_item_set iis INNER JOIN media ON media.item_id = iis.item_id
                    WHERE iis.item_set_id = resource.id) mediaCount,
                (SELECT COALESCE(SUM(media.size), 0) FROM item_item_set iis INNER JOIN media ON media.item_id = iis.item_id
                    WHERE iis.item_set_id = resource.id) mediaSize,
                (SELECT COUNT(*) FROM site_item_set sis WHERE sis.item_set_id = resource.id) siteCount
            FROM item_set
                INNER JOIN resource ON resource.id = item_set.id
            ORDER BY resource.title
            SQL);
        foreach ($itemSets as &$itemSet) {
            $itemSet['mediaSizeReadable'] = $this->bytesToReadable($itemSet['mediaSize']);
        }
        unset($itemSet);

        $fileTypes = $conn->fetchAllAssociative(<<<'SQL'
            SELECT COALESCE(media_type, ingester) fileType, COUNT(*) total, COALESCE(SUM(size), 0) size
            FROM media
            GROUP BY fileType
            ORDER BY total DESC, fileType
            SQL);
        foreach ($fileTypes as &$fileType) {
            $fileType['sizeReadable'] = $this->bytesToReadable($fileType['size']);
        }
        unset($fileType);

        $resourceTemplates = $conn->fetchAllAssociative(<<<'SQL'
            SELECT
                resource_template.label,
                SUM(resource.resource_type = ?) itemSetCount,
                SUM(resource.resource_type = ?) itemCount,
                SUM(resource.resource_type = ?) mediaCount
            FROM resource_template
                LEFT JOIN resource ON resource.resource_template_id = resource_template.id
            GROUP BY resource_template.id, resource_template.label
            ORDER BY resource_template.label
            SQL, $types);

        $vocabularies = $conn->fetchAllAssociative(<<<'SQL'
            SELECT
                vocabulary.prefix,
                vocabulary.label,
                (SELECT COUNT(*) FROM resource_class WHERE resource_class.vocabulary_id = vocabulary.id) classCount,
                (SELECT COUNT(*) FROM property WHERE property.vocabulary_id = vocabulary.id) propertyCount
            FROM vocabulary
            ORDER BY vocabulary.prefix
            SQL);

        return [
            'resourceCounts' => $resourceCounts,
            'itemStats' => $itemStats,
            'itemSets' => $itemSets,
            'fileTypes' => $fileTypes,
            'resourceTemplates' => $resourceTemplates,
            'vocabularies' => $vocabularies,
        ];
    }

    protected function getMetadataData(): array
    {
        $conn = $this->connection;
        $types = [ItemSet::class, Item::class, Media::class];

        $properties = $conn->fetchAllAssociative(<<<'SQL'
            SELECT
                CONCAT(vocabulary.prefix, ':', property.local_name) term,
                property.label,
                SUM(resource.resource_type = ?) itemSetCount,
                SUM(resource.resource_type = ?) itemCount,
                SUM(resource.resource_type = ?) mediaCount
            FROM property
                INNER JOIN vocabulary ON vocabulary.id = property.vocabulary_id
                INNER JOIN value ON value.property_id = property.id
                INNER JOIN resource ON resource.id = value.resource_id
            GROUP BY property.id, vocabulary.prefix, property.local_name, property.label
            ORDER BY term
            SQL, $types);

        $classes = $conn->fetchAllAssociative(<<<'SQL'
            SELECT
                CONCAT(vocabulary.prefix, ':', resource_class.local_name) term,
                resource_class.label,
                SUM(resource.resource_type = ?) itemSetCount,
                SUM(resource.resource_type = ?) itemCount,
                SUM(resource.resource_type = ?) mediaCount
            FROM resource_class
                INNER JOIN vocabulary ON vocabulary.id = resource_class.vocabulary_id
                INNER JOIN resource ON resource.resource_class_id = resource_class.id
            GROUP BY resource_class.id, vocabulary.prefix, resource_class.local_name, resource_class.label
            ORDER BY term
            SQL, $types);

        $valueResourceTypes = $conn->fetchAllAssociative(<<<'SQL'
            SELECT resource.resource_type, COUNT(*) `count`
            FROM value
                INNER JOIN resource ON resource.id = value.value_resource_id
            GROUP BY resource.resource_type
            ORDER BY resource.resource_type
            SQL);

        return [
            'properties' => $properties,
            'classes' => $classes,
            'valueResourceTypes' => $valueResourceTypes,
            'valueAnnotationCount' => $this->getResourceCount(ValueAnnotation::class),
        ];
    }

    protected function getUsersData(): array
    {
        $roles = $this->connection->fetchAllAssociative(<<<'SQL'
            SELECT
                role,
                SUM(is_active = 0) inactiveCount,
                SUM(is_active = 1) activeCount,
                COUNT(*) total
            FROM user
            GROUP BY role
            ORDER BY role
            SQL);

        return [
            'roles' => $roles,
            'roleLabels' => $this->acl->getRoleLabels(),
        ];
    }

    protected function getModulesData(): array
    {
        $modules = [];
        foreach ($this->moduleManager->getModules() as $module) {
            $modules[] = [
                'id' => $module->getId(),
                'name' => $module->getName() ?: $module->getId(),
                'version' => $module->getIni('version'),
                'dbVersion' => $module->getDb('version'),
                'state' => $module->getState(),
                'author' => $module->getIni('author'),
                'link' => $module->getIni('module_link'),
            ];
        }
        usort($modules, fn ($a, $b) => strnatcasecmp($a['name'], $b['name']));

        return ['modules' => $modules];
    }

    protected function getDiskData(): array
    {
        $mediaSize = (int) $this->connection->fetchOne('SELECT COALESCE(SUM(size), 0) FROM media');
        return [
            'mediaSize' => $mediaSize,
            'mediaSizeReadable' => $this->bytesToReadable($mediaSize),
            // Media ingested before Omeka S 1.1 (no size column yet) or by
            // ingesters that do not set it.
            'mediaWithoutSizeCount' => (int) $this->connection->fetchOne('SELECT COUNT(*) FROM media WHERE has_original = 1 AND size IS NULL'),
            'storage' => [
                'type' => $this->storage['type'],
                'name' => $this->storage['name'],
                'path' => $this->storage['path'] ?? null,
            ],
            'storageDirectories' => $this->storageDirectories,
        ];
    }

    /**
     * Size of a top directory of the file store, or null when the store is
     * not supported.
     */
    protected function getStorageSize(string $directory): ?int
    {
        switch ($this->storage['type']) {
            case 'local':
                return $this->getDirectorySize($this->storage['path'] . '/' . $directory);
            case 'anycloud':
                return $this->getBucketSize($this->storage['fileSystem'], $directory);
            default:
                return null;
        }
    }

    protected function getResourceCount(string $resourceType): int
    {
        return (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM resource WHERE resource_type = ?',
            [$resourceType]
        );
    }

    // from https://stackoverflow.com/questions/478121/how-to-get-directory-size-in-php
    protected function getDirectorySize(string $dir): int
    {
        $dir = rtrim(str_replace('\\', '/', $dir), '/');

        if (is_file($dir)) {
            return (int) filesize($dir);
        }
        if (!is_dir($dir)) {
            return 0;
        }
        if (!is_readable($dir)) {
            throw new \RuntimeException(sprintf('Directory "%s" is not readable.', $dir));
        }

        $isWindows = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';

        if (!$isWindows && function_exists('popen')) {
            // -H: follow the directory itself when it is a symlink (docker
            // volumes, shared "files" directory…), not the links inside it.
            $io = popen('/usr/bin/du -sbH ' . escapeshellarg($dir) . ' 2>/dev/null', 'r');
            if ($io !== false) {
                $size = intval(fgets($io, 80));
                pclose($io);
                if ($size > 0) {
                    return $size;
                }
            }
        }

        if ($isWindows && extension_loaded('com_dotnet')) {
            $obj = new \COM('scripting.filesystemobject');
            if (is_object($obj)) {
                return (int) $obj->getfolder($dir)->size;
            }
        }

        $totalSize = 0;
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::LEAVES_ONLY,
            \RecursiveIteratorIterator::CATCH_GET_CHILD
        );
        foreach ($files as $file) {
            try {
                $totalSize += $file->getSize();
            } catch (\RuntimeException $e) {
                // Skip unreadable file.
            }
        }
        return $totalSize;
    }

    /**
     * Sum of the file sizes under a prefix of a remote storage. Errors on the
     * listing itself are thrown, so that a failure is not displayed as 0.
     *
     * @see https://flysystem.thephpleague.com/docs/usage/filesystem-api/
     */
    protected function getBucketSize(\League\Flysystem\Filesystem $filesystem, string $prefix): int
    {
        $sum = 0;
        /** @var \League\Flysystem\StorageAttributes $item */
        foreach ($filesystem->listContents($prefix, true) as $item) {
            if (!$item instanceof \League\Flysystem\FileAttributes) {
                continue;
            }
            try {
                $sum += $item->fileSize() ?? $filesystem->fileSize($item->path());
            } catch (\League\Flysystem\FilesystemException $exception) {
                // Skip this file.
            }
        }
        return $sum;
    }

    protected function bytesToReadable($nBytes): string
    {
        $nBytes = (float) $nBytes;
        $units = [
            'B', // @translate
            'KB', // @translate
            'MB', // @translate
            'GB', // @translate
            'TB', // @translate
        ];
        $i = 0;
        for (; $i < 4 && $nBytes > 1000.0; $i++) {
            $nBytes /= 1000.0;
        }
        return round($nBytes, 1) . ' ' . $this->translate($units[$i]);
    }
}
