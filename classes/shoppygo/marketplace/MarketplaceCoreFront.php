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

use Doctrine\Bundle\DoctrineBundle\Registry;
use ShoppyGo\MarketplaceBundle\Entity\MarketplaceSeller;
use ShoppyGo\MarketplaceBundle\Entity\MarketplaceSellerShipping;
use ShoppyGo\MarketplaceBundle\Repository\MarketplaceSellerProductRepository;
use ShoppyGo\MarketplaceBundle\Repository\MarketplaceSellerRepository;

class MarketplaceCoreFront
{

    public function __construct(private Registry $registry, private \Context $context)
    {
    }

    public function findSellerByProduct($id_product): array
    {
        return $this->getSellerProductRepository()
            ->findSellersByProducts([$id_product]);
    }

    private function getSellerProductRepository(): MarketplaceSellerProductRepository
    {
        return new MarketplaceSellerProductRepository(
            $this->registry->getConnection(), _DB_PREFIX_
        );
    }

    public function getMarketplaceSellerData(int $id): MarketplaceSeller
    {
        return $this->getMarketplaceSellerRepository()
            ->find($id) ?? new MarketplaceSeller();
    }

    private function getMarketplaceSellerRepository(): MarketplaceSellerRepository
    {
        return $this->registry->getRepository(MarketplaceSeller::class);
    }

    /**
     * @param int $id
     * @return string
     */
    public function getSellerName(int $id): string
    {
        return (new Supplier($id))->name;
    }

    public function getTotalShippingBySeller(array $products): array
    {

        $shippingRepository = $this->registry->getRepository(MarketplaceSellerShipping::class);
        $productsTotalBySeller = $this->getProductTotalBySeller($products);
        $shopAddress = $this->context->shop->getAddress();

        $shippingCostsBySeller = [];

        foreach ($productsTotalBySeller as $sellerId => $totalProducts) {
            $shippingCost = $shippingRepository->findRange($sellerId, $totalProducts);

            if (!$shippingCost) continue;

            $carrierName = $shippingCost->getCarrierName();

            // Initialize seller and carrierName in shippingCostsBySeller array
            if (!isset($shippingCostsBySeller[$sellerId][$carrierName])) {
                $shippingCostsBySeller[$sellerId][$carrierName] = 0;
            }


            $taxCalculator = TaxManagerFactory::getManager($shopAddress, $shippingCost->getIdTaxRulesGroup())
                ->getTaxCalculator();
            $taxRate = 1 + ((float)$taxCalculator->getTotalRate() / 100);

            $shippingCostsBySeller[$sellerId][$carrierName] += (float)$shippingCost->getCost() * $taxRate;
        }

        return $shippingCostsBySeller;
    }

    public function getProductTotalBySeller(array $products)
    {
        $seller_product = $this->getSellersProduct($products);
        $seller_total = [];
        foreach ($seller_product as $sp) {
            $filtered_product = array_filter(
                $products, function ($p) use ($sp) {
                return (int)$sp['id_product'] === (int)$p['id_product'];
            }
            );
            $total_product = 0;
            $total_product = array_map(
                function ($p) use (&$total_product) {
                    //TODO $total = $p['total'] ?? $p['total_price_tax_incl'];  is not correct:
                    //      $p['total'] when customer insert order
                    //      $p['total_price_tax_incl'] when splitting orde. This is an error
                    $total = $p['total'] ?? $p['total_price_tax_incl'];

                    return $total_product + $total;
                },
                $filtered_product
            );
            $seller_total[$sp['id_seller']] = array_pop($total_product);
        }

        return $seller_total;
    }

    /**
     * @param array $products
     * @return array|mixed[][]
     * @throws \Doctrine\DBAL\Driver\Exception
     * @throws \Doctrine\DBAL\Exception
     */
    public function getSellersProduct(array $products): array
    {
        $ids = array_map(
            static function ($product) {
                return (int)$product['id_product'];
            },
            $products
        );

        $repo = $this->getSellerProductRepository();

        return $repo->qbProductSupplier($ids)
            ->select('ps.id_supplier as id_seller, ps.id_product')
            ->distinct('ps.id_product')
            ->execute()
            ->fetchAllAssociative();
    }

    public function isMainOrder(int $id_order): bool
    {
        $order = $this->registry->getConnection()
            ->executeQuery(
                'SELECT id_order FROM ' . _DB_PREFIX_ .
                'marketplace_seller_order WHERE id_order <> :id_order and id_order_main = :id_order',
                ['id_order' => $id_order]
            );

        return $order->rowCount() > 0;
    }

}
