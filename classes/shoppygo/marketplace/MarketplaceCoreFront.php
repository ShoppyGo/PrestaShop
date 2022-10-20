<?php

use Doctrine\Bundle\DoctrineBundle\Registry;
use ShoppyGo\MarketplaceBundle\Entity\MarketplaceSellerShipping;
use ShoppyGo\MarketplaceBundle\Repository\MarketplaceSellerProductRepository;

class MarketplaceCoreFront
{

    public function __construct(private Registry $registry, private \Context $context)
    {
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
                    return $total_product + $p['total'];
                },
                $filtered_product
            );
            $seller_total[$sp['id_seller']] = array_pop($total_product);
        }

        return $seller_total;
    }

    /**
     * @param int $id
     * @return string
     */
    protected function getSellerName(int $id): string
    {
        $supplier = new Supplier($id);

        return $supplier->name;
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

        $repo = new MarketplaceSellerProductRepository(
            $this->registry->getConnection(), _DB_PREFIX_
        );

        return $repo->qbProductSupplier($ids)
            ->select('ps.id_supplier as id_seller, ps.id_product')
            ->distinct('ps.id_product')
            ->execute()
            ->fetchAllAssociative()
        ;
    }

    public function getTotalShippingBySeller(array $products): array
    {
        $total_by_seller = $this->getProductTotalBySeller($products);

        $shipping_cost_by_seller = [];

        foreach ($total_by_seller as $seller => $total_products) {
            $repo = $this->registry->getRepository(MarketplaceSellerShipping::class);
            $shipping_cost = $repo->findRange($seller, $total_products);

            $shipping_cost_by_seller[$seller] = $shipping_cost ? (float)$shipping_cost->getCost() : 0;
        }

        return $shipping_cost_by_seller;
    }

}
