<?php

namespace App\Repository;

use App\Entity\SiteWidget;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SiteWidget>
 */
class SiteWidgetRepository extends ServiceEntityRepository
{
    private const PUBLIC_CACHE_TTL = 3600;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SiteWidget::class);
    }

    /**
     * @return SiteWidget[]
     */
    public function findActiveOrdered(): array
    {
        $query = $this->createQueryBuilder('w')
            ->where('w.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('w.sortOrder', 'ASC')
            ->addOrderBy('w.id', 'ASC')
            ->getQuery();

        $query->enableResultCache(self::PUBLIC_CACHE_TTL, 'public_site_widgets_active');

        return $query->getResult();
    }

    /**
     * @return SiteWidget[]
     */
    public function findAllOrdered(): array
    {
        return $this->createQueryBuilder('w')
            ->orderBy('w.sortOrder', 'ASC')
            ->addOrderBy('w.id', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
