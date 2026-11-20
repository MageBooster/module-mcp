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

use Magento\Sales\Model\Order;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory;
use MageBooster\Mcp\Api\ToolInterface;
use MageBooster\Mcp\Model\Role;

/**
 * Sales totals in the base currency. Canceled orders are counted apart and left out of revenue and AOV.
 */
class SalesSummary implements ToolInterface
{
    private const MAX_ROWS = 50000;

    public function __construct(
        private CollectionFactory $collectionFactory,
        private Period $period
    ) {
    }

    public function getName(): string
    {
        return 'sales_summary';
    }

    public function getDescription(): string
    {
        return 'Sales for a period (store timezone, default last 30 days, max 366): revenue, order count, '
            . 'average order value, refunds, orders by status and a per-day breakdown. Base currency; canceled orders excluded from revenue.';
    }

    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'from' => ['type' => 'string', 'format' => 'date', 'description' => 'YYYY-MM-DD'],
                'to' => ['type' => 'string', 'format' => 'date', 'description' => 'YYYY-MM-DD, inclusive'],
                'by_day' => ['type' => 'boolean', 'default' => true],
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
        $range = $this->period->resolve($args, 30, 366);
        $byDay = !array_key_exists('by_day', $args) || (bool)$args['by_day'];
        $zone = $this->period->zone();
        $utc = new \DateTimeZone('UTC');

        $collection = $this->collectionFactory->create();
        $collection->addFieldToSelect(['created_at', 'state', 'status', 'base_grand_total', 'base_total_refunded', 'total_qty_ordered', 'base_currency_code'])
            ->addFieldToFilter('created_at', ['gteq' => $range['utc_from']])
            ->addFieldToFilter('created_at', ['lt' => $range['utc_to']])
            ->setPageSize(self::MAX_ROWS)->setCurPage(1);
        $rows = $collection->getConnection()->fetchAll($collection->getSelect());

        $days = [];
        if ($byDay) {
            for ($d = $range['from']; $d <= $range['to']; $d = $d->modify('+1 day')) {
                $days[$d->format('Y-m-d')] = ['date' => $d->format('Y-m-d'), 'orders' => 0, 'revenue' => 0.0];
            }
        }
        $orders = 0;
        $revenue = 0.0;
        $refunded = 0.0;
        $qty = 0.0;
        $canceled = 0;
        $byStatus = [];
        $currency = null;
        foreach ($rows as $row) {
            $currency ??= $row['base_currency_code'];
            $byStatus[$row['status']] = ($byStatus[$row['status']] ?? 0) + 1;
            if ($row['state'] === Order::STATE_CANCELED) {
                $canceled++;
                continue;
            }
            $orders++;
            $revenue += (float)$row['base_grand_total'];
            $refunded += (float)$row['base_total_refunded'];
            $qty += (float)$row['total_qty_ordered'];
            if ($byDay) {
                $day = (new \DateTimeImmutable($row['created_at'], $utc))->setTimezone($zone)->format('Y-m-d');
                if (isset($days[$day])) {
                    $days[$day]['orders']++;
                    $days[$day]['revenue'] += (float)$row['base_grand_total'];
                }
            }
        }
        foreach ($days as &$day) {
            $day['revenue'] = round($day['revenue'], 2);
        }
        unset($day);
        arsort($byStatus);

        return [
            'period' => ['from' => $range['from']->format('Y-m-d'), 'to' => $range['to']->format('Y-m-d'), 'days' => $range['days'], 'timezone' => $zone->getName()],
            'currency' => $currency,
            'orders' => $orders,
            'revenue' => round($revenue, 2),
            'aov' => $orders ? round($revenue / $orders, 2) : 0.0,
            'refunded' => round($refunded, 2),
            'net_revenue' => round($revenue - $refunded, 2),
            'items_sold' => $qty,
            'canceled_orders' => $canceled,
            'by_status' => $byStatus,
            'items' => array_values($days),
            'truncated' => count($rows) >= self::MAX_ROWS,
        ];
    }
}
