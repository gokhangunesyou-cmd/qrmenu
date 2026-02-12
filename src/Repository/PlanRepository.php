<?php

namespace App\Repository;

use App\Entity\Plan;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Plan>
 */
class PlanRepository extends ServiceEntityRepository
{
    private const PUBLIC_CACHE_TTL = 3600;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Plan::class);
    }

    /**
     * @return Plan[]
     */
    public function findActiveOrdered(): array
    {
        $query = $this->createQueryBuilder('p')
            ->where('p.isActive = true')
            ->orderBy('p.yearlyPrice', 'ASC')
            ->addOrderBy('p.maxRestaurants', 'ASC')
            ->addOrderBy('p.maxUsers', 'ASC')
            ->addOrderBy('p.id', 'ASC')
            ->getQuery();

        $query->enableResultCache(self::PUBLIC_CACHE_TTL, 'public_site_active_plans');

        return $query->getResult();
    }
}
