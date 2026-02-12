<?php

namespace App\Repository;

use App\Entity\SiteSetting;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SiteSetting>
 */
class SiteSettingRepository extends ServiceEntityRepository
{
    private const PUBLIC_CACHE_TTL = 3600;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SiteSetting::class);
    }

    public function findOneByKey(string $keyName): ?SiteSetting
    {
        $query = $this->createQueryBuilder('s')
            ->andWhere('s.keyName = :keyName')
            ->setParameter('keyName', $keyName)
            ->setMaxResults(1)
            ->getQuery();

        $query->enableResultCache(self::PUBLIC_CACHE_TTL, sprintf('public_site_setting_key_%s', $keyName));

        return $query->getOneOrNullResult();
    }
}
