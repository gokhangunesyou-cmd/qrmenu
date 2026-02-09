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
        return $this->createQueryBuilder('l')
            ->where('l.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('l.name', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
