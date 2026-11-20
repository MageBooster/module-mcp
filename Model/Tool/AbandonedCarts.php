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

use Magento\Quote\Model\ResourceModel\Quote\CollectionFactory as QuoteCollectionFactory;
use Magento\Quote\Model\ResourceModel\Quote\Item\CollectionFactory as ItemCollectionFactory;
use MageBooster\Mcp\Api\ToolInterface;
use MageBooster\Mcp\Model\Masker;
use MageBooster\Mcp\Model\Role;

/**
 * Active carts with items that were not ordered, newest first. E-mails masked.
 */
class AbandonedCarts implements ToolInterface
{
    public const MAX_PAGE_SIZE = 50;
    private const MAX_LINES = 20;

    public function __construct(
        private QuoteCollectionFactory $quoteCollectionFactory,
        private ItemCollectionFactory $itemCollectionFactory,
        private Period $period,
        private Masker $masker
    ) {
    }

    public function getName(): string
    {
        return 'abandoned_carts';
    }

    public function getDescription(): string
    {
        return 'Carts with products that were not turned into an order, updated in the last 7 or 30 days. '
            . 'Totals, value, and per cart: items, value, last activity, guest or customer, masked e-mail.';
    }

    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'days' => ['type' => 'integer', 'enum' => [7, 30], 'default' => 7],
                'min_idle_minutes' => ['type' => 'integer', 'minimum' => 0, 'maximum' => 10080, 'default' => 60,
                    'description' => 'Skip carts touched more recently than this'],
                'with_email_only' => ['type' => 'boolean', 'default' => false],
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
        $days = Args::int($args, 'days', 7, 1, 30) > 7 ? 30 : 7;
        $idle = Args::int($args, 'min_idle_minutes', 60, 0, 10080);
        $page = Args::int($args, 'page', 1, 1, 100000);
        $pageSize = Args::int($args, 'page_size', 20, 1, self::MAX_PAGE_SIZE);
        $utc = new \DateTimeZone('UTC');
        $since = (new \DateTimeImmutable('now', $utc))->modify('-' . $days . ' days')->format('Y-m-d H:i:s');
        $until = (new \DateTimeImmutable('now', $utc))->modify('-' . $idle . ' minutes')->format('Y-m-d H:i:s');

        $collection = $this->quoteCollectionFactory->create();
        $collection->addFieldToFilter('is_active', 1)
            ->addFieldToFilter('items_count', ['gt' => 0])
            ->addFieldToFilter('updated_at', ['gteq' => $since])
            ->addFieldToFilter('updated_at', ['lteq' => $until]);
        if (!empty($args['with_email_only'])) {
            $collection->addFieldToFilter('customer_email', ['notnull' => true]);
        }

        // Totals over the whole result, not only this page.
        $sum = clone $collection->getSelect();
        $sum->reset(\Magento\Framework\DB\Select::COLUMNS)->reset(\Magento\Framework\DB\Select::ORDER)
            ->reset(\Magento\Framework\DB\Select::LIMIT_COUNT)->reset(\Magento\Framework\DB\Select::LIMIT_OFFSET)
            ->columns([
                'carts' => 'COUNT(*)',
                'value' => 'SUM(main_table.base_grand_total)',
                'items' => 'SUM(main_table.items_qty)',
                'with_email' => 'SUM(main_table.customer_email IS NOT NULL)',
            ]);
        $totals = $collection->getConnection()->fetchRow($sum) ?: [];
        $total = (int)($totals['carts'] ?? 0);

        $collection->setOrder('updated_at', 'DESC')->setPageSize($pageSize)->setCurPage($page);
        $carts = [];
        if (($page - 1) * $pageSize < $total) {
            foreach ($collection as $quote) {
                $carts[(int)$quote->getId()] = [
                    'id' => (int)$quote->getId(),
                    'store_id' => (int)$quote->getStoreId(),
                    'created_at' => $this->period->local($quote->getCreatedAt()),
                    'updated_at' => $this->period->local($quote->getUpdatedAt()),
                    'guest' => !(int)$quote->getCustomerId(),
                    'email' => $this->masker->email($quote->getCustomerEmail()),
                    'items_count' => (int)$quote->getItemsCount(),
                    'qty' => (float)$quote->getItemsQty(),
                    'value' => (float)$quote->getBaseGrandTotal(),
                    'currency' => $quote->getBaseCurrencyCode(),
                    'lines' => [],
                ];
            }
        }
        if ($carts) {
            $items = $this->itemCollectionFactory->create();
            $items->addFieldToFilter('quote_id', ['in' => array_keys($carts)])
                ->addFieldToFilter('parent_item_id', ['null' => true])
                ->setOrder('item_id', 'ASC');
            foreach ($items->getData() as $row) {
                $quoteId = (int)$row['quote_id'];
                if (count($carts[$quoteId]['lines']) >= self::MAX_LINES) {
                    continue;
                }
                $carts[$quoteId]['lines'][] = [
                    'sku' => $row['sku'],
                    'name' => $row['name'],
                    'qty' => (float)$row['qty'],
                    'price' => (float)$row['base_price'],
                    'row_total' => (float)$row['base_row_total'],
                ];
            }
        }
        return [
            'days' => $days,
            'summary' => [
                'carts' => $total,
                'value' => round((float)($totals['value'] ?? 0), 2),
                'items' => (float)($totals['items'] ?? 0),
                'with_email' => (int)($totals['with_email'] ?? 0),
            ],
            'items' => array_values($carts),
            'paging' => Args::paging($page, $pageSize, $total),
            'pii' => 'masked',
        ];
    }
}
