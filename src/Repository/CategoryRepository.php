<?php

namespace App\Repository;

use App\Entity\Category;
use App\Entity\Restaurant;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Category>
 */
class CategoryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Category::class);
    }

    /**
     * Find all categories for a specific restaurant.
     *
     * @return Category[]
     */
    public function findByRestaurant(Restaurant $restaurant): array
    {
        return $this->createQueryBuilder('c')
            ->where('c.restaurant = :restaurant')
            ->setParameter('restaurant', $restaurant)
            ->orderBy('c.sortOrder', 'ASC')
            ->addOrderBy('c.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find all global default categories (not associated with any restaurant).
     *
     * @return Category[]
     */
    public function findGlobalDefaults(): array
    {
        return $this->createQueryBuilder('c')
            ->where('c.restaurant IS NULL')
            ->orderBy('c.sortOrder', 'ASC')
            ->addOrderBy('c.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find active categories for a restaurant with active products and images eagerly loaded.
     * Single query — avoids N+1 for the public menu hot path.
     *
     * @return Category[]
     */
    public function findActiveWithProductsForMenu(Restaurant $restaurant): array
    {
        return $this->createQueryBuilder('c')
            ->leftJoin('c.products', 'p', 'WITH', 'p.isActive = true AND p.deletedAt IS NULL')
            ->addSelect('p')
            ->leftJoin('p.images', 'pi')
            ->addSelect('pi')
            ->leftJoin('pi.media', 'pm')
            ->addSelect('pm')
            ->leftJoin('c.imageMedia', 'cm')
            ->addSelect('cm')
            ->where('c.restaurant = :restaurant')
            ->andWhere('c.isActive = true')
            ->setParameter('restaurant', $restaurant)
            ->orderBy('c.sortOrder', 'ASC')
            ->addOrderBy('p.sortOrder', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find one category by UUID and restaurant.
     */
    public function findOneByUuidAndRestaurant(string $uuid, Restaurant $restaurant): ?Category
    {
        return $this->createQueryBuilder('c')
            ->where('c.uuid = :uuid')
            ->andWhere('c.restaurant = :restaurant')
            ->setParameter('uuid', $uuid)
            ->setParameter('restaurant', $restaurant)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
