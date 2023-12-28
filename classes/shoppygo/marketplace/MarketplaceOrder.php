<?php

/**
 * Copyright since 2022 Bwlab of Luigi Massa and Contributors
 * Bwlab of Luigi Massa is an Italy Company
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.md.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/OSL-3.0
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@shoppygo.io so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade ShoppyGo to newer
 * versions in the future. If you wish to customize ShoppyGo for your
 * needs please refer to https://docs.shoppygo.io/ for more information.
 *
 * @author    Bwlab and Contributors <contact@shoppygo.io>
 * @copyright Since 2022 Bwlab of Luigi Massa and Contributors
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */
class MarketplaceOrderCore extends OrderCore
{
    public static function getCustomerOrders($id_customer, $show_hidden_status = false, Context $context = null)
    {
        $customer_orders = parent::getCustomerOrders(
            $id_customer,
            $show_hidden_status,
            $context
        );
        $order_ids = array_map(static function (array $record) {
            return $record['id_order'];
        }, $customer_orders);

        //devo identificare quali main non ancora splittati e gli splitting

        $marketplace_order_ids = self::extract_seller_orders($order_ids);
        if (0 === count($marketplace_order_ids)) {
            return $customer_orders;
        }

        $all_main_orders = array_map(static function (array $record) {
            return (int)$record['id_order_main'];
        }, $marketplace_order_ids);
        $main_orders = array_diff($order_ids, $all_main_orders);
        $seller_order_ids = array_map(static function (array $record) {
            return (int)$record['id_order'];
        }, $marketplace_order_ids);

        $order_to_filter_ids = array_merge($seller_order_ids, $main_orders);

        $sal = array_filter($customer_orders, static function ($order) use ($order_to_filter_ids) {
            return in_array((int)$order['id_order'], $order_to_filter_ids);
        });

        return $sal;
    }

    protected static function extract_seller_orders(array $customer_order_ids): array
    {
        $sql =
            'select id_order, id_order_main from ' . _DB_PREFIX_ . 'marketplace_seller_order ' . ' where id_order_main in ' .
            sprintf('(%s)', implode(',', $customer_order_ids));

        $seller_order_ids = Db::getInstance()
            ->executeS($sql);
        if (0 === count($seller_order_ids)) {
            return [];
        }

        return $seller_order_ids;
    }
}
