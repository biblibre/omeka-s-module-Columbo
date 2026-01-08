<?php

namespace Columbo\Controller\Admin;

use Doctrine\DBAL\Connection;
use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\ViewModel;
use Omeka\Entity\ItemSet;
use Omeka\Entity\Item;
use Omeka\Entity\Media;
use Omeka\Entity\ValueAnnotation;
use Omeka\Site\Theme\Manager as ThemeManager;

class IndexController extends AbstractActionController
{
    protected Connection $connection;
    protected ThemeManager $themeManager;
    protected ?\League\Flysystem\FileSystem $fileSystem;

    public function __construct(Connection $connection, ThemeManager $themeManager, ?\League\Flysystem\FileSystem $fileSystem = null)
    {
        $this->connection = $connection;
        $this->themeManager = $themeManager;
        $this->fileSystem = $fileSystem;
    }

    // from https://stackoverflow.com/questions/478121/how-to-get-directory-size-in-php
    function getDirectorySize($dir)
    {
        $dir = rtrim(str_replace('\\', '/', $dir), '/');

        if (is_dir($dir) === true) {
            $totalSize = 0;
            $os        = strtoupper(substr(PHP_OS, 0, 3));
            // If on a Unix Host (Linux, Mac OS)
            if ($os !== 'WIN') {
                $io = popen('/usr/bin/du -sb ' . $dir, 'r');
                if ($io !== false) {
                    $a = fgets($io, 80);
                    $totalSize = intval($a);
                    pclose($io);
                    return $totalSize;
                }
            }
            // If on a Windows Host (WIN32, WINNT, Windows)
            if ($os === 'WIN' && extension_loaded('com_dotnet')) {
                $obj = new \COM('scripting.filesystemobject');
                if (is_object($obj)) {
                    $ref       = $obj->getfolder($dir);
                    $totalSize = $ref->size;
                    $obj       = null;
                    return $totalSize;
                }
            }
            // If System calls did't work, use slower PHP 5
            $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir));
            foreach ($files as $file) {
                $totalSize += $file->getSize();
            }
            return $totalSize;
        } else if (is_file($dir) === true) {
            return filesize($dir);
        }
        return 0;
    }

    private function bytesToReadable($nBytes)
    {
        $i = 0;
        for (; $i < 5 && $nBytes > 1000.0; $i++) {
            $nBytes = $nBytes / 1000.0;
        }
        $sign = "B"; // @translate
        switch ($i) {
        case 0:
            {
                break;
            }
        case 1:
            {
                $sign = "KB"; // @translate
                break;
            }
        case 2:
            {
                $sign = "MB"; // @translate
                break;
            }
        case 3:
            {
                $sign = "GB"; // @translate
                break;
            }
        case 4:
            {
                $sign = "TB"; // @translate
                break;
            }
        default:
            {
                $sign = "";
            }
        }

        return strval(round($nBytes, 1)) . ' ' . $sign;
    }


    private function getBucketSize($filesystem): int
    {
        $sum = 0;
        // from https://flysystem.thephpleague.com/docs/usage/filesystem-api/
        try {
            $listing = $filesystem->listContents('', /* recursive = */ true);

            /** @var \League\Flysystem\StorageAttributes $item */
            foreach ($listing as $item) {
                $path = $item->path();

                if ($item instanceof \League\Flysystem\FileAttributes) {
                    try {
                        $fileSize = $filesystem->fileSize($path);
                        $sum += $fileSize;
                    } catch (\League\Flysystem\FilesystemException | \League\Flysystem\UnableToRetrieveMetadata $exception) {
                        // do nothing, skip this file
                    }
                } // else it's a directory we don't care
            }
        } catch (\League\Flysystem\FilesystemException $exception) {
            return 0;
        }   
    }

    public function indexAction()
    {
        $conn = $this->connection;

        $view = new ViewModel;

        return $view;
    }

    public function downloadAction()
    {
        $conn = $this->connection;

        $arrayToExport = [];

        $privateItemSetsCount = $this->getPrivateResourceCount(ItemSet::class);
        $publicItemSetsCount = $this->getPublicResourceCount(ItemSet::class);
        $itemSetsWithoutTemplateCount = $this->getWithoutTemplateResourceCount(ItemSet::class);
        $itemSetsWithoutClassCount = $this->getWithoutClassResourceCount(ItemSet::class);
        $itemSetsCount = $this->getResourceCount(ItemSet::class);
        $arrayToExport = array_merge($arrayToExport, [
            'privateItemSetsCount' => $privateItemSetsCount,
            'publicItemSetsCount' => $publicItemSetsCount,
            'itemSetsWithoutTemplateCount' => $itemSetsWithoutTemplateCount,
            'itemSetsWithoutClassCount' => $itemSetsWithoutClassCount,
            'itemSetsCount' => $itemSetsCount,
        ]);

        $privateItemsCount = $this->getPrivateResourceCount(Item::class);
        $publicItemsCount = $this->getPublicResourceCount(Item::class);
        $itemsWithoutTemplateCount = $this->getWithoutTemplateResourceCount(Item::class);
        $itemsWithoutClassCount = $this->getWithoutClassResourceCount(Item::class);
        $itemsCount = $this->getResourceCount(Item::class);
        $arrayToExport = array_merge($arrayToExport, [
            'privateItemsCount' => $privateItemsCount,
            'publicItemsCount' => $publicItemsCount,
            'itemsWithoutTemplateCount' => $itemsWithoutTemplateCount,
            'itemsWithoutClassCount' => $itemsWithoutClassCount,
            'itemsCount' => $itemsCount,
        ]);

        $privateMediaCount = $this->getPrivateResourceCount(Media::class);
        $publicMediaCount = $this->getPublicResourceCount(Media::class);
        $mediaWithoutTemplateCount = $this->getWithoutTemplateResourceCount(Media::class);
        $mediaWithoutClassCount = $this->getWithoutClassResourceCount(Media::class);
        $mediaCount = $this->getResourceCount(Media::class);
        $arrayToExport = array_merge($arrayToExport, [
            'privateMediaCount' => $privateMediaCount,
            'publicMediaCount' => $publicMediaCount,
            'mediaWithoutTemplateCount' => $mediaWithoutTemplateCount,
            'mediaWithoutClassCount' => $mediaWithoutClassCount,
            'mediaCount' => $mediaCount,
        ]);

        $itemsWithMediaCount = $this->api()->search('items', ['has_media' => true, 'limit' => 0])->getTotalResults();
        $itemsWithoutMediaCount = $itemsCount - $itemsWithMediaCount;
        $arrayToExport = array_merge($arrayToExport, [
            'itemsWithMediaCount' => $itemsWithMediaCount,
            'itemsWithoutMediaCount' => $itemsWithoutMediaCount,
        ]);

        $itemsWithoutItemSetCount = $conn->fetchOne(<<<SQL
            select count(distinct item.id) from item left join item_item_set ON (item.id = item_item_set.item_id)
            where item_item_set.item_set_id is null
        SQL);
        $itemsWithItemSetCount = $conn->fetchOne(<<<SQL
            select count(distinct item.id) from item left join item_item_set ON (item.id = item_item_set.item_id)
            where item_item_set.item_set_id is not null
        SQL);
        $arrayToExport = array_merge($arrayToExport, [
            'itemsWithoutItemSetCount' => $itemsWithoutItemSetCount,
            'itemsWithItemSetCount' => $itemsWithItemSetCount,
        ]);

        [$mediaCountByItemMin, $mediaCountByItemMax, $mediaCountByItemAvg] = $conn->fetchNumeric(<<<SQL
            select min(cnt), max(cnt), avg(cnt) from (
                select media.item_id, count(*) cnt from media
                group by media.item_id
            ) s
        SQL);
        $arrayToExport = array_merge($arrayToExport, [
            'mediaCountByItemMin' => $mediaCountByItemMin,
            'mediaCountByItemMax' => $mediaCountByItemMax,
            'mediaCountByItemAvg' => $mediaCountByItemAvg,
        ]);

        $perFileTypeMediaCounts = $conn->fetchAllKeyValue(<<<SQL
            select coalesce(media_type, ingester) file_type, count(*) cnt from media
            group by media_type
            order by cnt desc, file_type
        SQL);

        {
            $i = 0;
            foreach($perFileTypeMediaCounts as $name => $perFileTypeMediaCount)
            {
                $arrayToExport["perFileTypeMediaCount" . $i . "_" . "fileType"] = $name;
                $arrayToExport["perFileTypeMediaCount" . $i . "_" . "total"] = $perFileTypeMediaCount;
                $i += 1;
            }
        }

        $sitesCounts = $conn->fetchAllAssociative(<<<SQL
            select
                site.title,
                count(distinct item_site.item_id) itemCount,
                count(distinct site_item_set.item_set_id) itemSetCount,
                count(distinct site_page.id) pageCount,
                count(distinct site_viewer.id) viewerCount,
                count(distinct site_editor.id) editorCount,
                count(distinct site_admin.id) adminCount,
                coalesce(mediaSize, 0) mediaSize
            from site
            left join item_site on (site.id = item_site.site_id)
            left join site_item_set on (site.id = site_item_set.site_id)
            left join site_page on (site.id = site_page.site_id)
            left join site_permission site_viewer on (site.id = site_viewer.site_id and site_viewer.role = 'viewer')
            left join site_permission site_editor on (site.id = site_editor.site_id and site_editor.role = 'editor')
            left join site_permission site_admin on (site.id = site_admin.site_id and site_admin.role = 'admin')
            left join (
                select
                    t1.site_id as site_id,
                    coalesce(sum(t1.media_size), 0) as mediaSize
                from (
                    select
                        site.id as site_id,
                        media.size as media_size
                    from site
                    left join item_site on (site.id = item_site.site_id)
                    left join media on (media.item_id = item_site.item_id)
                    group by media.id, site.id
                ) as t1 group by t1.site_id
            ) as t on t.site_id = site.id
            group by site.id      
        SQL);

        $mediaSizeRequest = $conn->fetchAllAssociative(<<<SQL
            select
                coalesce(sum(media.size), 0) as mediaSize
            from
                media
        SQL);

        $totalMediaInstallSize = 0;
        foreach ($sitesCounts as $index => $site) {
            $sitesCounts[$index]['mediaSize'] = $this->bytesToReadable($site['mediaSize']);
            foreach ($sitesCounts[$index] as $key => $value)
            {
                $arrayToExport["site" . $index . "_" . $key] = $value;
            }
        }

        $totalMediaInstallSize = intval($mediaSizeRequest[0]['mediaSize']);
        $installSize = [];
        $installSize['totalMedia'] = $this->bytesToReadable($totalMediaInstallSize);
        $totalAssetsSize = $this->getDirectorySize(OMEKA_PATH . '/files/asset');
        $installSize['totalAssets'] = $this->bytesToReadable($totalAssetsSize);
        $totalFileSize = $this->getDirectorySize(OMEKA_PATH . '/files');
        $installSize['totalFiles'] = $this->bytesToReadable($totalFileSize);
        $installSize['total'] = $this->bytesToReadable($totalMediaInstallSize + $totalFileSize);
        $installSize['omeka'] = $this->bytesToReadable($this->getDirectorySize(OMEKA_PATH));

        $arrayToExport = array_merge($arrayToExport, $installSize);

        $themes = $this->themeManager->getThemes();
        $themesData = [];
        {
            $i = 0;
            foreach ($themes as $index => $theme) {
                $siteCount = $conn->fetchOne('select count(*) from site where theme = ?', [$theme->getId()]);
                $themeData = [                 
                    'themeName' => $theme->getName(),
                    'themeVersion' => $theme->getIni('version'),
                    'themeSiteCount' => $siteCount,
                ];
                $themesData[] = $themeData;
                foreach ($themeData as $key => $value)
                {
                    $arrayToExport["theme" . $i . "_" . $key] = $value;
                }
                $i += 1;
            }
        }

        $properties = $conn->fetchAllAssociative(<<<SQL
            select
                concat(vocabulary.prefix, ':', property.local_name) term,
                sum(if(resource.resource_type = ?, 1, 0)) itemSetCount,
                sum(if(resource.resource_type = ?, 1, 0)) itemCount,
                sum(if(resource.resource_type = ?, 1, 0)) mediaCount
            from property
                inner join vocabulary on (vocabulary.id = property.vocabulary_id)
                inner join value on (value.property_id = property.id)
                inner join resource on (resource.id = value.resource_id)
            group by term
            order by term
        SQL, [ItemSet::class, Item::class, Media::class]);

        foreach($properties as $index => $property)
        {
            foreach ($property as $key => $value)
            {
                $arrayToExport["property" . $index . "_" . $key] = $value;
            }
        }

        $classes = $conn->fetchAllAssociative(<<<SQL
            select
                concat(vocabulary.prefix, ':', resource_class.local_name) term,
                sum(if(resource.resource_type = ?, 1, 0)) itemSetCount,
                sum(if(resource.resource_type = ?, 1, 0)) itemCount,
                sum(if(resource.resource_type = ?, 1, 0)) mediaCount
            from resource_class
                inner join vocabulary on (vocabulary.id = resource_class.vocabulary_id)
                inner join resource on (resource.resource_class_id = resource_class.id)
            group by term
            having itemSetCount + itemCount + mediaCount > 0
            order by term
        SQL, [ItemSet::class, Item::class, Media::class]);
        
        foreach($classes as $index => $class)
        {
            foreach ($class as $key => $value)
            {
                $arrayToExport["class" . $index . "_" . $key] = $value;
            }
        }

        $resourceTemplates = $conn->fetchAllAssociative(<<<SQL
            select
                resource_template.label,
                sum(if(resource.resource_type = ?, 1, 0)) itemSetCount,
                sum(if(resource.resource_type = ?, 1, 0)) itemCount,
                sum(if(resource.resource_type = ?, 1, 0)) mediaCount
            from resource_template
                left join resource on (resource.resource_template_id = resource_template.id)
            group by label
            order by label
        SQL, [ItemSet::class, Item::class, Media::class]);
        
        foreach($resourceTemplates as $index => $resourceTemplate)
        {
            foreach ($resourceTemplate as $key => $value)
            {
                $arrayToExport["resourceTemplate" . $index . "_" . $key] = $value;
            }
        }

        $vocabularies = $conn->fetchAllAssociative(<<<SQL
            select
                vocabulary.prefix,
                count(distinct resource_class.id) classCount,
                count(distinct property.id) propertyCount
            from vocabulary
                left join resource_class on (resource_class.vocabulary_id = vocabulary.id)
                left join property on (property.vocabulary_id = vocabulary.id)
            group by prefix
            order by prefix
        SQL);
        
        foreach($vocabularies as $index => $vocabulary)
        {
            foreach ($vocabulary as $key => $value)
            {
                $arrayToExport["vocabulary" . $index . "_" . $key] = $value;
            }
        }

        $valueResourceTypes = $conn->fetchAllAssociative(<<<SQL
            select resource.resource_type, count(*) `count`
                from value
                    inner join resource on (value.value_resource_id = resource.id)
                group by resource.resource_type
                order by resource.resource_type
        SQL);
        
        foreach($valueResourceTypes as $index => $valueResourceType)
        {
            foreach ($valueResourceType as $key => $value)
            {
                $arrayToExport["valueResourceType" . $index . "_" . $key] = $value;
            }
        }

        $valueAnnotationCount = $this->getResourceCount(ValueAnnotation::class);
        
        $arrayToExport["valueAnnotationCount"] = $valueAnnotationCount;

        $roles = $conn->fetchAllAssociative(<<<SQL
            select
                role.role,
                sum(if(user.is_active, 0, 1)) inactiveCount,
                sum(if(user.is_active, 1, 0)) activeCount
            from (select distinct role from user) role
                left join user on (user.role = role.role)
            group by role.role
            order by role.role
        SQL);

        foreach($roles as $index => $role)
        {
            foreach ($role as $key => $value)
            {
                $arrayToExport["role" . $index . "_" . $key] = $value;
            }
        }
        
        $fp = fopen('php://temp', 'r+');

        fputcsv($fp, array_keys($arrayToExport));
        fputcsv($fp, array_values($arrayToExport));

        // Rewind and capture contents
        rewind($fp);
        $rows = stream_get_contents($fp);
        fclose($fp);

        $response = $this->getResponse();
        $response->setContent($rows);
        $response->getHeaders()->addHeaderLine('Content-type', 'text/csv');
        $response->getHeaders()->addHeaderLine('Content-Disposition', 'attachment; filename="columbo.csv"');

        return $response;
    }

    public function diskAction() {
        $conn = $this->connection;

        $view = new ViewModel;

        $mediaSizeRequest = $conn->fetchAllAssociative(<<<SQL
            select
                coalesce(sum(media.size), 0) as mediaSize
            from
                media
        SQL);

        $totalMediaInstallSize = intval($mediaSizeRequest[0]['mediaSize']);
        $installSize = [];
        $installSize['totalMedia'] = $this->bytesToReadable($totalMediaInstallSize);
        $totalAssetsSize = $this->getDirectorySize(OMEKA_PATH . '/files/asset');
        $installSize['totalAssets'] = $this->bytesToReadable($totalAssetsSize);
        $totalFileSize = $this->getDirectorySize(OMEKA_PATH . '/files');
        $installSize['totalFiles'] = $this->bytesToReadable($totalFileSize);

        if (!empty($this->fileSystem)) {
            $installSize['bucket'] = $this->bytesToReadable($this->getBucketSize($this->fileSystem));
            var_dump($installSize['bucket']);
        }

        $installSize['total'] = $this->bytesToReadable($totalMediaInstallSize + $totalFileSize);
        $installSize['omeka'] = $this->bytesToReadable($this->getDirectorySize(OMEKA_PATH));

        $view->setVariable('installSize', $installSize);

        return $view;
    }

    public function metadataAction() {
        $conn = $this->connection;

        $view = new ViewModel;

        $properties = $conn->fetchAllAssociative(<<<SQL
            select
                concat(vocabulary.prefix, ':', property.local_name) term,
                sum(if(resource.resource_type = ?, 1, 0)) itemSetCount,
                sum(if(resource.resource_type = ?, 1, 0)) itemCount,
                sum(if(resource.resource_type = ?, 1, 0)) mediaCount
            from property
                inner join vocabulary on (vocabulary.id = property.vocabulary_id)
                inner join value on (value.property_id = property.id)
                inner join resource on (resource.id = value.resource_id)
            group by term
            order by term
        SQL, [ItemSet::class, Item::class, Media::class]);
        $view->setVariable('properties', $properties);

        $classes = $conn->fetchAllAssociative(<<<SQL
            select
                concat(vocabulary.prefix, ':', resource_class.local_name) term,
                sum(if(resource.resource_type = ?, 1, 0)) itemSetCount,
                sum(if(resource.resource_type = ?, 1, 0)) itemCount,
                sum(if(resource.resource_type = ?, 1, 0)) mediaCount
            from resource_class
                inner join vocabulary on (vocabulary.id = resource_class.vocabulary_id)
                inner join resource on (resource.resource_class_id = resource_class.id)
            group by term
            having itemSetCount + itemCount + mediaCount > 0
            order by term
        SQL, [ItemSet::class, Item::class, Media::class]);
        $view->setVariable('classes', $classes);

        $valueResourceTypes = $conn->fetchAllAssociative(<<<SQL
            select resource.resource_type, count(*) `count`
                from value
                    inner join resource on (value.value_resource_id = resource.id)
                group by resource.resource_type
                order by resource.resource_type
        SQL);
        $view->setVariable('valueResourceTypes', $valueResourceTypes);

        $valueAnnotationCount = $this->getResourceCount(ValueAnnotation::class);
        $view->setVariable('valueAnnotationCount', $valueAnnotationCount);

        return $view;
    }

    public function modulesAction() {
        $conn = $this->connection;

        $view = new ViewModel;

        return $view;
    }

    public function resourcesAction() {
        $conn = $this->connection;

        $view = new ViewModel;

        $privateItemSetsCount = $this->getPrivateResourceCount(ItemSet::class);
        $publicItemSetsCount = $this->getPublicResourceCount(ItemSet::class);
        $itemSetsWithoutTemplateCount = $this->getWithoutTemplateResourceCount(ItemSet::class);
        $itemSetsWithoutClassCount = $this->getWithoutClassResourceCount(ItemSet::class);
        $itemSetsCount = $this->getResourceCount(ItemSet::class);
        $view->setVariables([
            'privateItemSetsCount' => $privateItemSetsCount,
            'publicItemSetsCount' => $publicItemSetsCount,
            'itemSetsWithoutTemplateCount' => $itemSetsWithoutTemplateCount,
            'itemSetsWithoutClassCount' => $itemSetsWithoutClassCount,
            'itemSetsCount' => $itemSetsCount,
        ]);

        $privateItemsCount = $this->getPrivateResourceCount(Item::class);
        $publicItemsCount = $this->getPublicResourceCount(Item::class);
        $itemsWithoutTemplateCount = $this->getWithoutTemplateResourceCount(Item::class);
        $itemsWithoutClassCount = $this->getWithoutClassResourceCount(Item::class);
        $itemsCount = $this->getResourceCount(Item::class);
        $view->setVariables([
            'privateItemsCount' => $privateItemsCount,
            'publicItemsCount' => $publicItemsCount,
            'itemsWithoutTemplateCount' => $itemsWithoutTemplateCount,
            'itemsWithoutClassCount' => $itemsWithoutClassCount,
            'itemsCount' => $itemsCount,
        ]);

        $privateMediaCount = $this->getPrivateResourceCount(Media::class);
        $publicMediaCount = $this->getPublicResourceCount(Media::class);
        $mediaWithoutTemplateCount = $this->getWithoutTemplateResourceCount(Media::class);
        $mediaWithoutClassCount = $this->getWithoutClassResourceCount(Media::class);
        $mediaCount = $this->getResourceCount(Media::class);
        $view->setVariables([
            'privateMediaCount' => $privateMediaCount,
            'publicMediaCount' => $publicMediaCount,
            'mediaWithoutTemplateCount' => $mediaWithoutTemplateCount,
            'mediaWithoutClassCount' => $mediaWithoutClassCount,
            'mediaCount' => $mediaCount,
        ]);

        $itemsWithMediaCount = $this->api()->search('items', ['has_media' => true, 'limit' => 0])->getTotalResults();
        $itemsWithoutMediaCount = $itemsCount - $itemsWithMediaCount;
        $view->setVariables([
            'itemsWithMediaCount' => $itemsWithMediaCount,
            'itemsWithoutMediaCount' => $itemsWithoutMediaCount,
        ]);

        $itemsWithoutItemSetCount = $conn->fetchOne(<<<SQL
            select count(distinct item.id) from item left join item_item_set ON (item.id = item_item_set.item_id)
            where item_item_set.item_set_id is null
        SQL);
        $itemsWithItemSetCount = $conn->fetchOne(<<<SQL
            select count(distinct item.id) from item left join item_item_set ON (item.id = item_item_set.item_id)
            where item_item_set.item_set_id is not null
        SQL);
        $view->setVariables([
            'itemsWithoutItemSetCount' => $itemsWithoutItemSetCount,
            'itemsWithItemSetCount' => $itemsWithItemSetCount,
        ]);

        [$mediaCountByItemMin, $mediaCountByItemMax, $mediaCountByItemAvg] = $conn->fetchNumeric(<<<SQL
            select min(cnt), max(cnt), avg(cnt) from (
                select media.item_id, count(*) cnt from media
                group by media.item_id
            ) s
        SQL);
        $view->setVariables([
            'mediaCountByItemMin' => $mediaCountByItemMin,
            'mediaCountByItemMax' => $mediaCountByItemMax,
            'mediaCountByItemAvg' => $mediaCountByItemAvg,
        ]);

        $perFileTypeMediaCounts = $conn->fetchAllKeyValue(<<<SQL
            select coalesce(media_type, ingester) file_type, count(*) cnt from media
            group by media_type
            order by cnt desc, file_type
        SQL);
        $view->setVariable('perFileTypeMediaCounts', $perFileTypeMediaCounts);

        $resourceTemplates = $conn->fetchAllAssociative(<<<SQL
            select
                resource_template.label,
                sum(if(resource.resource_type = ?, 1, 0)) itemSetCount,
                sum(if(resource.resource_type = ?, 1, 0)) itemCount,
                sum(if(resource.resource_type = ?, 1, 0)) mediaCount
            from resource_template
                left join resource on (resource.resource_template_id = resource_template.id)
            group by label
            order by label
        SQL, [ItemSet::class, Item::class, Media::class]);
        $view->setVariable('resourceTemplates', $resourceTemplates);

        $vocabularies = $conn->fetchAllAssociative(<<<SQL
            select
                vocabulary.prefix,
                count(distinct resource_class.id) classCount,
                count(distinct property.id) propertyCount
            from vocabulary
                left join resource_class on (resource_class.vocabulary_id = vocabulary.id)
                left join property on (property.vocabulary_id = vocabulary.id)
            group by prefix
            order by prefix
        SQL);
        $view->setVariable('vocabularies', $vocabularies);

        return $view;
    }

    public function sitesAction() {
        $conn = $this->connection;

        $view = new ViewModel;

        $themes = $this->themeManager->getThemes();
        $themesData = [];
        foreach ($themes as $theme) {
            $siteCount = $conn->fetchOne('select count(*) from site where theme = ?', [$theme->getId()]);
            $themesData[] = [
                'name' => $theme->getName(),
                'version' => $theme->getIni('version'),
                'siteCount' => $siteCount,
            ];
        }
        $view->setVariable('themes', $themesData);

        $sitesCounts = $conn->fetchAllAssociative(<<<SQL
            select
                site.title,
                count(distinct item_site.item_id) itemCount,
                count(distinct site_item_set.item_set_id) itemSetCount,
                count(distinct site_page.id) pageCount,
                count(distinct site_viewer.id) viewerCount,
                count(distinct site_editor.id) editorCount,
                count(distinct site_admin.id) adminCount,
                coalesce(mediaSize, 0) mediaSize
            from site
            left join item_site on (site.id = item_site.site_id)
            left join site_item_set on (site.id = site_item_set.site_id)
            left join site_page on (site.id = site_page.site_id)
            left join site_permission site_viewer on (site.id = site_viewer.site_id and site_viewer.role = 'viewer')
            left join site_permission site_editor on (site.id = site_editor.site_id and site_editor.role = 'editor')
            left join site_permission site_admin on (site.id = site_admin.site_id and site_admin.role = 'admin')
            left join (
                select
                    t1.site_id as site_id,
                    coalesce(sum(t1.media_size), 0) as mediaSize
                from (
                    select
                        site.id as site_id,
                        media.size as media_size
                    from site
                    left join item_site on (site.id = item_site.site_id)
                    left join media on (media.item_id = item_site.item_id)
                    group by media.id, site.id
                ) as t1 group by t1.site_id
            ) as t on t.site_id = site.id
            group by site.id      
        SQL);

        $totalMediaInstallSize = 0;
        foreach ($sitesCounts as $index => $site) {
            $sitesCounts[$index]['mediaSize'] = $this->bytesToReadable($site['mediaSize']);
        }

        $view->setVariable('sitesCounts', $sitesCounts);

        return $view;
    }

    public function usersAction() {
        $conn = $this->connection;

        $view = new ViewModel;

        $roles = $conn->fetchAllAssociative(<<<SQL
            select
                role.role,
                sum(if(user.is_active, 0, 1)) inactiveCount,
                sum(if(user.is_active, 1, 0)) activeCount
            from (select distinct role from user) role
                left join user on (user.role = role.role)
            group by role.role
            order by role.role
        SQL);
        $view->setVariable('roles', $roles);

        return $view;
    }

    protected function getResourceCount(string $resourceType): int
    {
        $sql = 'select count(*) from resource where resource_type = ?';
        return $this->connection->fetchOne($sql, [$resourceType]);
    }

    protected function getPublicResourceCount(string $resourceType): int
    {
        $sql = 'select count(*) from resource where resource_type = ? AND is_public = 1';
        return $this->connection->fetchOne($sql, [$resourceType]);
    }

    protected function getPrivateResourceCount(string $resourceType): int
    {
        $sql = 'select count(*) from resource where resource_type = ? AND is_public = 0';
        return $this->connection->fetchOne($sql, [$resourceType]);
    }

    protected function getWithoutTemplateResourceCount(string $resourceType): int
    {
        $sql = 'select count(*) from resource where resource_type = ? AND resource_template_id is null';
        return $this->connection->fetchOne($sql, [$resourceType]);
    }

    protected function getWithoutClassResourceCount(string $resourceType): int
    {
        $sql = 'select count(*) from resource where resource_type = ? AND resource_class_id is null';
        return $this->connection->fetchOne($sql, [$resourceType]);
    }
}
