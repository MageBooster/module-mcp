<?php
declare(strict_types=1);

namespace MageBooster\Mcp\Model\Tool;

use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\Store\Model\Store;
use MageBooster\Mcp\Api\ToolInterface;
use MageBooster\Mcp\Model\Role;

class ProductsSearch implements ToolInterface
{
    public const MAX_PAGE_SIZE = 50;

    public function __construct(
        private CollectionFactory $collectionFactory
    ) {
    }

    public function getName(): string
    {
        return 'products_search';
    }

    public function getDescription(): string
    {
        return 'Find products by name or SKU (part of the text is enough). Filters: status, type, stock. '
            . 'Returns id, sku, name, type, status, price, qty and stock state, up to 50 per page.';
    }

    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'query' => ['type' => 'string', 'description' => 'Text in the name or SKU'],
                'status' => ['type' => 'string', 'enum' => ['enabled', 'disabled']],
                'type' => ['type' => 'string', 'description' => 'simple, configurable, bundle, grouped, virtual, downloadable'],
                'in_stock' => ['type' => 'boolean'],
                'sort' => ['type' => 'string', 'enum' => ['name', 'sku', 'price', 'qty', 'updated_at', 'created_at'], 'default' => 'name'],
                'direction' => ['type' => 'string', 'enum' => ['asc', 'desc'], 'default' => 'asc'],
                'page' => ['type' => 'integer', 'minimum' => 1, 'default' => 1],
                'page_size' => ['type' => 'integer', 'minimum' => 1, 'maximum' => self::MAX_PAGE_SIZE, 'default' => 20],
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
        $page = Args::int($args, 'page', 1, 1, 100000);
        $pageSize = Args::int($args, 'page_size', 20, 1, self::MAX_PAGE_SIZE);
        $query = Args::str($args, 'query', 100);
        $status = Args::oneOf($args, 'status', ['enabled', 'disabled']);
        $type = Args::str($args, 'type', 32);
        $sort = Args::oneOf($args, 'sort', ['name', 'sku', 'price', 'qty', 'updated_at', 'created_at'], 'name');
        $direction = Args::oneOf($args, 'direction', ['asc', 'desc'], 'asc');

        $collection = $this->collectionFactory->create();
        $collection->setStoreId(Store::DEFAULT_STORE_ID)
            ->addAttributeToSelect(['name', 'price', 'status', 'special_price'])
            ->joinField('qty', 'cataloginventory_stock_item', 'qty', 'product_id=entity_id', '{{table}}.stock_id=1', 'left')
            ->joinField('is_in_stock', 'cataloginventory_stock_item', 'is_in_stock', 'product_id=entity_id', '{{table}}.stock_id=1', 'left');
        if ($query !== null) {
            $like = '%' . addcslashes($query, '%_\\') . '%';
            $collection->addAttributeToFilter([
                ['attribute' => 'name', 'like' => $like],
                ['attribute' => 'sku', 'like' => $like],
            ], null, 'left');
        }
        if ($status !== null) {
            $collection->addAttributeToFilter('status', $status === 'enabled' ? Status::STATUS_ENABLED : Status::STATUS_DISABLED);
        }
        if ($type !== null) {
            $collection->addAttributeToFilter('type_id', $type);
        }
        if (array_key_exists('in_stock', $args) && $args['in_stock'] !== null) {
            $collection->addFieldToFilter('is_in_stock', $args['in_stock'] ? 1 : 0);
        }
        $collection->setOrder($sort, strtoupper((string)$direction));
        $collection->setPageSize($pageSize)->setCurPage($page);

        $total = $collection->getSize();
        $items = [];
        if (($page - 1) * $pageSize < $total) {
            foreach ($collection as $product) {
                $items[] = [
                    'id' => (int)$product->getId(),
                    'sku' => $product->getSku(),
                    'name' => $product->getName(),
                    'type' => $product->getTypeId(),
                    'status' => (int)$product->getStatus() === Status::STATUS_ENABLED ? 'enabled' : 'disabled',
                    'price' => $product->getPrice() !== null ? (float)$product->getPrice() : null,
                    'special_price' => $product->getSpecialPrice() !== null ? (float)$product->getSpecialPrice() : null,
                    'qty' => $product->getData('qty') !== null ? (float)$product->getData('qty') : null,
                    'in_stock' => $product->getData('is_in_stock') !== null ? (bool)$product->getData('is_in_stock') : null,
                ];
            }
        }
        return ['items' => $items, 'paging' => Args::paging($page, $pageSize, $total)];
    }
}
