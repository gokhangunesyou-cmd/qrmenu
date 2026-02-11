<?php

namespace App\Repository;

use App\Entity\SiteContent;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SiteContent>
 */
class SiteContentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SiteContent::class);
    }

    /**
     * @return list<SiteContent>
     */
    public function findPublishedByPrefix(string $prefix): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.keyName LIKE :prefix')
            ->andWhere('c.isPublished = true')
            ->setParameter('prefix', $prefix . '%')
            ->orderBy('c.keyName', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return list<SiteContent>
     */
    public function findByPrefix(string $prefix): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.keyName LIKE :prefix')
            ->setParameter('prefix', $prefix . '%')
            ->orderBy('c.keyName', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
