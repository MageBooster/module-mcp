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

use Magento\Sales\Model\ResourceModel\Order\CollectionFactory;
use MageBooster\Mcp\Api\ToolInterface;
use MageBooster\Mcp\Model\Masker;
use MageBooster\Mcp\Model\Role;

/**
 * Orders with customer data masked (v1 has no way to unmask).
 */
class OrdersList implements ToolInterface
{
    public const MAX_PAGE_SIZE = 50;

    public function __construct(
        private CollectionFactory $collectionFactory,
        private Period $period,
        private Masker $masker
    ) {
    }

    public function getName(): string
    {
        return 'orders_list';
    }

    public function getDescription(): string
    {
        return 'Orders in a date range (store timezone, default last 30 days, max 366), optionally by status. '
            . 'Customer name, e-mail and phone are masked; only the country and a masked city of the address are shown.';
    }

    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'from' => ['type' => 'string', 'format' => 'date', 'description' => 'YYYY-MM-DD'],
                'to' => ['type' => 'string', 'format' => 'date', 'description' => 'YYYY-MM-DD, inclusive'],
                'status' => ['type' => 'string', 'description' => 'pending, processing, complete, canceled, closed, holded, ...'],
                'increment_id' => ['type' => 'string', 'description' => 'Order number'],
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
        $status = Args::str($args, 'status', 32);
        $incrementId = Args::str($args, 'increment_id', 50);
        $range = $this->period->resolve($args, 30, 366);

        $collection = $this->collectionFactory->create();
        $collection->addFieldToSelect([
            'entity_id', 'increment_id', 'created_at', 'status', 'state', 'store_id', 'grand_total', 'base_grand_total',
            'order_currency_code', 'base_currency_code', 'total_qty_ordered', 'total_item_count', 'customer_is_guest',
            'customer_email', 'customer_firstname', 'customer_lastname', 'shipping_description', 'total_refunded',
        ]);
        if ($incrementId !== null) {
            $collection->addFieldToFilter('increment_id', $incrementId);
        } else {
            $collection->addFieldToFilter('created_at', ['gteq' => $range['utc_from']])
                ->addFieldToFilter('created_at', ['lt' => $range['utc_to']]);
        }
        if ($status !== null) {
            $collection->addFieldToFilter('status', $status);
        }
        $collection->setOrder('created_at', 'DESC')->setPageSize($pageSize)->setCurPage($page);

        $total = $collection->getSize();
        $items = [];
        if (($page - 1) * $pageSize < $total) {
            foreach ($collection as $order) {
                $billing = $order->getBillingAddress();
                $payment = $order->getPayment();
                $items[] = [
                    'id' => (int)$order->getId(),
                    'number' => $order->getIncrementId(),
                    'created_at' => $this->period->local($order->getCreatedAt()),
                    'status' => $order->getStatus(),
                    'state' => $order->getState(),
                    'store_id' => (int)$order->getStoreId(),
                    'grand_total' => (float)$order->getGrandTotal(),
                    'currency' => $order->getOrderCurrencyCode(),
                    'base_grand_total' => (float)$order->getBaseGrandTotal(),
                    'base_currency' => $order->getBaseCurrencyCode(),
                    'refunded' => (float)$order->getTotalRefunded(),
                    'items' => (int)$order->getTotalItemCount(),
                    'qty' => (float)$order->getTotalQtyOrdered(),
                    'payment' => $payment ? $payment->getMethod() : null,
                    'shipping' => $order->getShippingDescription(),
                    'customer' => [
                        'guest' => (bool)$order->getCustomerIsGuest(),
                        'name' => $this->masker->name(trim($order->getCustomerFirstname() . ' ' . $order->getCustomerLastname())),
                        'email' => $this->masker->email($order->getCustomerEmail()),
                        'phone' => $billing ? $this->masker->phone($billing->getTelephone()) : null,
                        'country' => $billing ? $billing->getCountryId() : null,
                        'city' => $billing ? $this->masker->text($billing->getCity()) : null,
                    ],
                ];
            }
        }
        return [
            'period' => ['from' => $range['from']->format('Y-m-d'), 'to' => $range['to']->format('Y-m-d')],
            'items' => $items,
            'paging' => Args::paging($page, $pageSize, $total),
            'pii' => 'masked',
        ];
    }
}
