<?php

namespace App\Repository;

use App\Entity\Category;
use App\Entity\Product;
use App\Entity\Restaurant;
use App\Enum\ProductStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Product>
 */
class ProductRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Product::class);
    }

    /**
     * Find products by restaurant with optional category and status filters.
     *
     * @return Product[]
     */
    public function findByRestaurantFiltered(Restaurant $restaurant, ?Category $category = null, ?ProductStatus $status = null): array
    {
        $qb = $this->createQueryBuilder('p')
            ->leftJoin('p.images', 'pi')
            ->addSelect('pi')
            ->leftJoin('pi.media', 'pm')
            ->addSelect('pm')
            ->where('p.restaurant = :restaurant')
            ->setParameter('restaurant', $restaurant);

        if ($category !== null) {
            $qb->andWhere('p.category = :category')
                ->setParameter('category', $category);
        }

        if ($status !== null) {
            $qb->andWhere('p.status = :status')
                ->setParameter('status', $status);
        }

        return $qb->orderBy('p.category', 'ASC')
            ->addOrderBy('p.sortOrder', 'ASC')
            ->addOrderBy('p.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find products by restaurant and optionally filter by category.
     *
     * @return Product[]
     */
    public function findByRestaurantAndCategory(Restaurant $restaurant, ?Category $category = null): array
    {
        $qb = $this->createQueryBuilder('p')
            ->where('p.restaurant = :restaurant')
            ->setParameter('restaurant', $restaurant);

        if ($category !== null) {
            $qb->andWhere('p.category = :category')
                ->setParameter('category', $category);
        }

        return $qb->orderBy('p.category', 'ASC')
            ->addOrderBy('p.sortOrder', 'ASC')
            ->addOrderBy('p.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find all products pending approval.
     *
     * @return Product[]
     */
    public function findPendingApproval(): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.status = :status')
            ->setParameter('status', ProductStatus::PENDING_APPROVAL)
            ->orderBy('p.submittedAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find all approved products from the global catalog (no restaurant association).
     *
     * @return Product[]
     */
    public function findApprovedCatalog(): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.status = :status')
            ->andWhere('p.restaurant IS NULL')
            ->setParameter('status', ProductStatus::APPROVED)
            ->orderBy('p.category', 'ASC')
            ->addOrderBy('p.sortOrder', 'ASC')
            ->addOrderBy('p.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find one product by UUID and restaurant.
     */
    public function findOneByUuidAndRestaurant(string $uuid, Restaurant $restaurant): ?Product
    {
        return $this->createQueryBuilder('p')
            ->where('p.uuid = :uuid')
            ->andWhere('p.restaurant = :restaurant')
            ->setParameter('uuid', $uuid)
            ->setParameter('restaurant', $restaurant)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
