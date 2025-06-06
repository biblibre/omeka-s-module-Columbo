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

    public function __construct(Connection $connection, ThemeManager $themeManager)
    {
        $this->connection = $connection;
        $this->themeManager = $themeManager;
    }

    public function indexAction()
    {
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

        $itemsWithMediaCount = $this->api()->search('items', ['has_media' => false, 'limit' => 0])->getTotalResults();
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

        $sitesCounts = $conn->fetchAllAssociative(<<<SQL
            select
                site.title,
                count(distinct item_site.item_id) itemCount,
                count(distinct site_item_set.item_set_id) itemSetCount,
                count(distinct site_page.id) pageCount,
                count(distinct site_viewer.id) viewerCount,
                count(distinct site_editor.id) editorCount,
                count(distinct site_admin.id) adminCount
            from site
            left join item_site on (site.id = item_site.site_id)
            left join site_item_set on (site.id = site_item_set.site_id)
            left join site_page on (site.id = site_page.site_id)
            left join site_permission site_viewer on (site.id = site_viewer.site_id and site_viewer.role = 'viewer')
            left join site_permission site_editor on (site.id = site_editor.site_id and site_editor.role = 'editor')
            left join site_permission site_admin on (site.id = site_admin.site_id and site_admin.role = 'admin')
            group by site.id
        SQL);

        $view->setVariable('sitesCounts', $sitesCounts);

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

        $properties = $conn->fetchAllAssociative(<<<SQL
            select
                concat(vocabulary.prefix, ':', property.local_name) term,
                count(distinct item_set_resource.id) itemSetCount,
                count(distinct item_resource.id) itemCount,
                count(distinct media_resource.id) mediaCount
            from property
                inner join vocabulary on (vocabulary.id = property.vocabulary_id)
                inner join value on (value.property_id = property.id)
                left join resource item_set_resource on (item_set_resource.id = value.resource_id and item_set_resource.resource_type = ?)
                left join resource item_resource on (item_resource.id = value.resource_id and item_resource.resource_type = ?)
                left join resource media_resource on (media_resource.id = value.resource_id and media_resource.resource_type = ?)
            group by term
            order by term
        SQL, [ItemSet::class, Item::class, Media::class]);
        $view->setVariable('properties', $properties);

        $classes = $conn->fetchAllAssociative(<<<SQL
            select
                concat(vocabulary.prefix, ':', resource_class.local_name) term,
                count(distinct item_set_resource.id) itemSetCount,
                count(distinct item_resource.id) itemCount,
                count(distinct media_resource.id) mediaCount
            from resource_class
                inner join vocabulary on (vocabulary.id = resource_class.vocabulary_id)
                left join resource item_set_resource on (item_set_resource.resource_class_id = resource_class.id and item_set_resource.resource_type = ?)
                left join resource item_resource on (item_resource.resource_class_id = resource_class.id and item_resource.resource_type = ?)
                left join resource media_resource on (media_resource.resource_class_id = resource_class.id and media_resource.resource_type = ?)
            group by term
            having itemSetCount + itemCount + mediaCount > 0
            order by term
        SQL, [ItemSet::class, Item::class, Media::class]);
        $view->setVariable('classes', $classes);

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

        $roles = $conn->fetchAllAssociative(<<<SQL
            select
                role.role,
                count(distinct inactive_user.id) inactiveCount,
                count(distinct active_user.id) activeCount
            from (select distinct role from user) role
                left join user inactive_user on (inactive_user.role = role.role and inactive_user.is_active = 0)
                left join user active_user on (active_user.role = role.role and active_user.is_active = 1)
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
