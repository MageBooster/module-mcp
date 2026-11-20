<?php
/*
 * Copyright (c) 2026 WebWorks 101 OÜ (MageBooster). All rights reserved.
 * Commercial licence, see LICENSE.txt. https://magebooster.com
 * Licensed per serial number for one production installation (one store domain); staging and
 * development copies of that store are allowed. Serial numbers and downloads are tied to the customer.
 * You may modify this code for your own licensed store; keep this notice.
 * Not allowed: copying to other stores, redistributing, reselling, sublicensing, publishing it publicly,
 * removing licence checks or notices, sharing serial numbers. Unauthorised use: the serial number is
 * revoked without refund, updates and support stop, damages and legal action may follow.
 * Estonian law applies; court: Harju County Court (Harju Maakohus).
 */
declare(strict_types=1);

namespace MageBooster\Mcp\Model\Tool;

use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory;
use Magento\Store\Model\Store;
use MageBooster\Mcp\Api\ToolInterface;
use MageBooster\Mcp\Model\Role;

class CategoriesTree implements ToolInterface
{
    private const MAX_CATEGORIES = 2000;

    public function __construct(
        private CollectionFactory $collectionFactory
    ) {
    }

    public function getName(): string
    {
        return 'categories_tree';
    }

    public function getDescription(): string
    {
        return 'Category tree with id, name, active, in menu, url key and product count. '
            . 'Optional root_id and depth (default 4).';
    }

    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'root_id' => ['type' => 'integer', 'description' => 'Start below this category (default: all roots)'],
                'depth' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 10, 'default' => 4],
            ],
            'additionalProperties' => false,
        ];
    }

    public function getMinRole(): string
    {
        return Role::READ;
    }

    public function execute(array $args): array
    {
        $rootId = Args::int($args, 'root_id', 1, 1, PHP_INT_MAX);
        $depth = Args::int($args, 'depth', 4, 1, 10);

        $collection = $this->collectionFactory->create();
        $collection->setStoreId(Store::DEFAULT_STORE_ID)
            ->addAttributeToSelect(['name', 'is_active', 'include_in_menu', 'url_key'])
            ->setLoadProductCount(true)
            ->addPathFilter('(^|/)' . $rootId . '(/|$)')
            ->setOrder('level', 'ASC')
            ->setOrder('position', 'ASC')
            ->setPageSize(self::MAX_CATEGORIES);

        // Collection is sorted by level, then position, so every parent comes before its children.
        $nodes = [];
        $tree = [];
        $rootLevel = null;
        $count = 0;
        foreach ($collection as $category) {
            $count++;
            $id = (int)$category->getId();
            $level = (int)$category->getLevel();
            if ($id === $rootId) {
                $rootLevel = $level;
                if ($rootId === 1) {
                    $nodes[$id] = ['children' => []];
                    continue;
                }
            }
            if ($rootLevel === null || $level - $rootLevel > $depth) {
                continue;
            }
            $nodes[$id] = [
                'id' => $id,
                'level' => $level,
                'name' => $category->getName(),
                'active' => (bool)$category->getIsActive(),
                'in_menu' => (bool)$category->getIncludeInMenu(),
                'url_key' => $category->getUrlKey(),
                'products' => (int)$category->getProductCount(),
                'children' => [],
            ];
            $parentId = (int)$category->getParentId();
            if ($id === $rootId) {
                $tree[] = &$nodes[$id];
            } elseif (isset($nodes[$parentId])) {
                $nodes[$parentId]['children'][] = &$nodes[$id];
            }
        }
        if ($rootId === 1 && isset($nodes[1])) {
            $tree = $nodes[1]['children'];
        }
        $truncated = $count >= self::MAX_CATEGORIES;
        $tree = json_decode((string)json_encode($tree), true);
        return ['items' => $tree, 'depth' => $depth, 'truncated' => $truncated];
    }
}
