<?php

namespace App\Repository;

use App\Entity\Product;
use App\Entity\ProductImage;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ProductImage>
 */
class ProductImageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ProductImage::class);
    }

    /**
     * Find all images for a specific product, ordered by sort order.
     *
     * @return ProductImage[]
     */
    public function findByProduct(Product $product): array
    {
        return $this->createQueryBuilder('pi')
            ->where('pi.product = :product')
            ->setParameter('product', $product)
            ->orderBy('pi.sortOrder', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
