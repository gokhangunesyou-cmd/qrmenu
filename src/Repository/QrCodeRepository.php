<?php

namespace App\Repository;

use App\Entity\QrCode;
use App\Entity\Restaurant;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<QrCode>
 */
class QrCodeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, QrCode::class);
    }

    /**
     * Find all QR codes for a specific restaurant.
     *
     * @return QrCode[]
     */
    public function findByRestaurant(Restaurant $restaurant): array
    {
        return $this->createQueryBuilder('q')
            ->where('q.restaurant = :restaurant')
            ->setParameter('restaurant', $restaurant)
            ->orderBy('q.tableNumber', 'ASC')
            ->addOrderBy('q.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find one QR code by UUID and restaurant.
     */
    public function findOneByUuidAndRestaurant(string $uuid, Restaurant $restaurant): ?QrCode
    {
        return $this->createQueryBuilder('q')
            ->where('q.uuid = :uuid')
            ->andWhere('q.restaurant = :restaurant')
            ->setParameter('uuid', $uuid)
            ->setParameter('restaurant', $restaurant)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
