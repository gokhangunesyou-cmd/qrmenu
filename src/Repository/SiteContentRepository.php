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
    private const PUBLIC_CACHE_TTL = 3600;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SiteContent::class);
    }

    /**
     * @return list<SiteContent>
     */
    public function findPublishedByPrefix(string $prefix): array
    {
        $query = $this->createQueryBuilder('c')
            ->andWhere('c.keyName LIKE :prefix')
            ->andWhere('c.isPublished = true')
            ->setParameter('prefix', $prefix . '%')
            ->orderBy('c.keyName', 'ASC')
            ->getQuery();

        $query->enableResultCache(
            self::PUBLIC_CACHE_TTL,
            sprintf('public_site_content_prefix_%s', md5($prefix))
        );

        return $query->getResult();
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
