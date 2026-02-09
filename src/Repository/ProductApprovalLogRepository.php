<?php

namespace App\Repository;

use App\Entity\Product;
use App\Entity\ProductApprovalLog;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ProductApprovalLog>
 */
class ProductApprovalLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ProductApprovalLog::class);
    }

    /**
     * Find all approval log entries for a specific product.
     *
     * @return ProductApprovalLog[]
     */
    public function findByProduct(Product $product): array
    {
        return $this->createQueryBuilder('pal')
            ->where('pal.product = :product')
            ->setParameter('product', $product)
            ->orderBy('pal.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
