<?php

namespace App\Repository;

use App\Entity\Locale;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Locale>
 */
class LocaleRepository extends ServiceEntityRepository
{
    private const PUBLIC_CACHE_TTL = 3600;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Locale::class);
    }

    /**
     * Find all active locales.
     *
     * @return Locale[]
     */
    public function findAllActive(): array
    {
        $query = $this->createQueryBuilder('l')
            ->where('l.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('l.name', 'ASC')
            ->getQuery();

        $query->enableResultCache(self::PUBLIC_CACHE_TTL, 'public_active_locales');

        return $query->getResult();
    }
}
