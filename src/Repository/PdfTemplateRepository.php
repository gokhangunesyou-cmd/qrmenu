<?php

namespace App\Repository;

use App\Entity\PdfTemplate;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PdfTemplate>
 */
class PdfTemplateRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PdfTemplate::class);
    }

    /**
     * Find all active PDF templates.
     *
     * @return PdfTemplate[]
     */
    public function findAllActive(): array
    {
        return $this->createQueryBuilder('pt')
            ->where('pt.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('pt.name', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
