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
        return $this->createQueryBuilder('r')
            ->leftJoin('r.theme', 't')
            ->addSelect('t')
            ->leftJoin('r.logoMedia', 'lm')
            ->addSelect('lm')
            ->leftJoin('r.coverMedia', 'cm')
            ->addSelect('cm')
            ->where('r.slug = :slug')
            ->andWhere('r.isActive = true')
            ->setParameter('slug', $slug)
            ->getQuery()
            ->getOneOrNullResult();
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
