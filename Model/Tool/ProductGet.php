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

use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\Product\Visibility;
use Magento\CatalogInventory\Api\StockRegistryInterface;
use Magento\Eav\Api\AttributeSetRepositoryInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\Store;
use MageBooster\Mcp\Api\ToolInterface;
use MageBooster\Mcp\Model\Role;
use MageBooster\Mcp\Model\ToolException;

class ProductGet implements ToolInterface
{
    private const MAX_CHILDREN = 100;

    public function __construct(
        private ProductRepositoryInterface $productRepository,
        private CategoryRepositoryInterface $categoryRepository,
        private StockRegistryInterface $stockRegistry,
        private AttributeSetRepositoryInterface $attributeSetRepository
    ) {
    }

    public function getName(): string
    {
        return 'product_get';
    }

    public function getDescription(): string
    {
        return 'One product by sku or id: prices, stock, status, visibility, categories, websites, '
            . 'short description, image count and, for configurable products, the child SKUs.';
    }

    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'sku' => ['type' => 'string'],
                'id' => ['type' => 'integer'],
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
        $sku = Args::str($args, 'sku', 64);
        $id = Args::int($args, 'id', 0, 0, PHP_INT_MAX);
        if ($sku === null && $id === 0) {
            throw new ToolException('Give sku or id.');
        }
        try {
            $product = $sku !== null
                ? $this->productRepository->get($sku, false, Store::DEFAULT_STORE_ID)
                : $this->productRepository->getById($id, false, Store::DEFAULT_STORE_ID);
        } catch (NoSuchEntityException) {
            throw new ToolException('Product not found.', 'not_found', 404);
        }

        $stock = $this->stockRegistry->getStockItem((int)$product->getId());
        $categories = [];
        foreach (array_slice((array)$product->getCategoryIds(), 0, 50) as $categoryId) {
            try {
                $category = $this->categoryRepository->get((int)$categoryId, Store::DEFAULT_STORE_ID);
                $categories[] = ['id' => (int)$categoryId, 'name' => $category->getName()];
            } catch (NoSuchEntityException) {
                $categories[] = ['id' => (int)$categoryId, 'name' => null];
            }
        }
        try {
            $attributeSet = $this->attributeSetRepository->get((int)$product->getAttributeSetId())->getAttributeSetName();
        } catch (NoSuchEntityException) {
            $attributeSet = null;
        }
        $children = [];
        if ($product->getTypeId() === 'configurable') {
            $linked = $product->getExtensionAttributes()?->getConfigurableProductLinks() ?? [];
            foreach (array_slice($linked, 0, self::MAX_CHILDREN) as $childId) {
                try {
                    $child = $this->productRepository->getById((int)$childId, false, Store::DEFAULT_STORE_ID);
                    $childStock = $this->stockRegistry->getStockItem((int)$childId);
                    $children[] = [
                        'id' => (int)$childId,
                        'sku' => $child->getSku(),
                        'name' => $child->getName(),
                        'price' => (float)$child->getPrice(),
                        'qty' => (float)$childStock->getQty(),
                        'in_stock' => (bool)$childStock->getIsInStock(),
                        'status' => (int)$child->getStatus() === Status::STATUS_ENABLED ? 'enabled' : 'disabled',
                    ];
                } catch (NoSuchEntityException) {
                    continue;
                }
            }
        }
        $visibility = Visibility::getOptionArray();
        $short = trim(html_entity_decode(strip_tags((string)$product->getData('short_description')), ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8'));

        return [
            'id' => (int)$product->getId(),
            'sku' => $product->getSku(),
            'name' => $product->getName(),
            'type' => $product->getTypeId(),
            'attribute_set' => $attributeSet,
            'status' => (int)$product->getStatus() === Status::STATUS_ENABLED ? 'enabled' : 'disabled',
            'visibility' => isset($visibility[(int)$product->getVisibility()]) ? (string)$visibility[(int)$product->getVisibility()] : null,
            'price' => $product->getPrice() !== null ? (float)$product->getPrice() : null,
            'special_price' => $product->getData('special_price') !== null ? (float)$product->getData('special_price') : null,
            'special_from' => $product->getData('special_from_date'),
            'special_to' => $product->getData('special_to_date'),
            'cost' => $product->getData('cost') !== null ? (float)$product->getData('cost') : null,
            'weight' => $product->getWeight() !== null ? (float)$product->getWeight() : null,
            'qty' => (float)$stock->getQty(),
            'in_stock' => (bool)$stock->getIsInStock(),
            'manage_stock' => (bool)$stock->getManageStock(),
            'url_key' => $product->getData('url_key'),
            'website_ids' => array_map('intval', (array)$product->getWebsiteIds()),
            'categories' => $categories,
            'short_description' => mb_substr($short, 0, 500),
            'meta_title' => $product->getData('meta_title'),
            'images' => count((array)$product->getMediaGalleryEntries()),
            'children' => $children,
            'created_at' => $product->getCreatedAt(),
            'updated_at' => $product->getUpdatedAt(),
        ];
    }
}
