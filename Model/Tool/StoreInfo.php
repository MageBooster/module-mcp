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

use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory as CategoryCollectionFactory;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory as ProductCollectionFactory;
use Magento\Customer\Model\ResourceModel\Customer\CollectionFactory as CustomerCollectionFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\ProductMetadataInterface;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory as OrderCollectionFactory;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use MageBooster\Mcp\Api\ToolInterface;
use MageBooster\Mcp\Model\Role;

class StoreInfo implements ToolInterface
{
    public function __construct(
        private StoreManagerInterface $storeManager,
        private ScopeConfigInterface $scopeConfig,
        private ProductMetadataInterface $productMetadata,
        private ProductCollectionFactory $productCollectionFactory,
        private CategoryCollectionFactory $categoryCollectionFactory,
        private OrderCollectionFactory $orderCollectionFactory,
        private CustomerCollectionFactory $customerCollectionFactory
    ) {
    }

    public function getName(): string
    {
        return 'store_info';
    }

    public function getDescription(): string
    {
        return 'Store overview: name, websites and store views with URLs, locale, currencies, timezone, '
            . 'Magento/Mage-OS version and counts of products, categories, orders and customers.';
    }

    public function getInputSchema(): array
    {
        return ['type' => 'object', 'properties' => new \stdClass(), 'additionalProperties' => false];
    }

    public function getMinRole(): string
    {
        return Role::READ;
    }

    public function execute(array $args): array
    {
        $views = [];
        foreach ($this->storeManager->getStores() as $store) {
            $code = $store->getCode();
            $views[] = [
                'id' => (int)$store->getId(),
                'code' => $code,
                'name' => $store->getName(),
                'website_id' => (int)$store->getWebsiteId(),
                'active' => (bool)$store->getIsActive(),
                'base_url' => $store->getBaseUrl(),
                'locale' => $this->value('general/locale/code', $code),
                'currency' => $this->value('currency/options/default', $code),
                'theme_id' => $this->value('design/theme/theme_id', $code),
            ];
        }
        $websites = [];
        foreach ($this->storeManager->getWebsites() as $website) {
            $websites[] = ['id' => (int)$website->getId(), 'code' => $website->getCode(), 'name' => $website->getName()];
        }
        $metadata = $this->productMetadata;
        return [
            'name' => $this->value('general/store_information/name') ?: $this->storeManager->getDefaultStoreView()?->getName(),
            'country' => $this->value('general/country/default'),
            'timezone' => $this->value('general/locale/timezone'),
            'base_currency' => $this->value('currency/options/base'),
            'platform' => [
                'name' => method_exists($metadata, 'getDistributionName') ? $metadata->getDistributionName() : $metadata->getName(),
                'version' => method_exists($metadata, 'getDistributionVersion') ? $metadata->getDistributionVersion() : $metadata->getVersion(),
                'magento_version' => $metadata->getVersion(),
                'edition' => $metadata->getEdition(),
            ],
            'websites' => $websites,
            'store_views' => $views,
            'counts' => [
                'products' => $this->productCollectionFactory->create()->getSize(),
                'categories' => $this->categoryCollectionFactory->create()->addAttributeToFilter('level', ['gt' => 1])->getSize(),
                'orders' => $this->orderCollectionFactory->create()->getSize(),
                'customers' => $this->customerCollectionFactory->create()->getSize(),
            ],
        ];
    }

    private function value(string $path, ?string $store = null): ?string
    {
        $value = $store === null
            ? $this->scopeConfig->getValue($path)
            : $this->scopeConfig->getValue($path, ScopeInterface::SCOPE_STORE, $store);
        return $value === null ? null : (string)$value;
    }
}
