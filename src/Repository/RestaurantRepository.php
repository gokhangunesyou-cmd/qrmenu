<?php

namespace App\Repository;

use App\Entity\Restaurant;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Restaurant>
 */
class RestaurantRepository extends ServiceEntityRepository
{
    private const PUBLIC_CACHE_TTL = 3600;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Restaurant::class);
    }

    /**
     * Find a restaurant by its slug.
     */
    public function findBySlug(string $slug): ?Restaurant
    {
        return $this->createQueryBuilder('r')
            ->where('r.slug = :slug')
            ->setParameter('slug', $slug)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Find an active restaurant by slug with theme, logo and cover eagerly loaded.
     * Optimized for the public menu hot path.
     */
    public function findActiveBySlugForMenu(string $slug): ?Restaurant
    {
        $query = $this->createQueryBuilder('r')
            ->leftJoin('r.theme', 't')
            ->addSelect('t')
            ->leftJoin('r.logoMedia', 'lm')
            ->addSelect('lm')
            ->leftJoin('r.coverMedia', 'cm')
            ->addSelect('cm')
            ->where('r.slug = :slug')
            ->andWhere('r.isActive = true')
            ->andWhere('r.deletedAt IS NULL')
            ->setParameter('slug', $slug)
            ->getQuery();

        $query->enableResultCache(self::PUBLIC_CACHE_TTL, sprintf('public_restaurant_menu_slug_%s', $slug));

        return $query->getOneOrNullResult();
    }

    /**
     * Find an active restaurant by id with theme, logo and cover eagerly loaded.
     */
    public function findActiveByIdForMenu(int $id): ?Restaurant
    {
        $query = $this->createQueryBuilder('r')
            ->leftJoin('r.theme', 't')
            ->addSelect('t')
            ->leftJoin('r.logoMedia', 'lm')
            ->addSelect('lm')
            ->leftJoin('r.coverMedia', 'cm')
            ->addSelect('cm')
            ->where('r.id = :id')
            ->andWhere('r.isActive = true')
            ->andWhere('r.deletedAt IS NULL')
            ->setParameter('id', $id)
            ->getQuery();

        $query->enableResultCache(self::PUBLIC_CACHE_TTL, sprintf('public_restaurant_menu_id_%d', $id));

        return $query->getOneOrNullResult();
    }

    /**
     * Find a restaurant by its UUID.
     */
    public function findByUuid(string $uuid): ?Restaurant
    {
        return $this->createQueryBuilder('r')
            ->where('r.uuid = :uuid')
            ->setParameter('uuid', $uuid)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
