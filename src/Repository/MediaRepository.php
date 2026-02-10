<?php

namespace App\Repository;

use App\Entity\Media;
use App\Entity\Restaurant;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Media>
 */
class MediaRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Media::class);
    }

    /**
     * Find one media item by UUID and restaurant.
     */
    public function findOneByUuidAndRestaurant(string $uuid, Restaurant $restaurant): ?Media
    {
        return $this->createQueryBuilder('m')
            ->where('m.uuid = :uuid')
            ->andWhere('m.restaurant = :restaurant')
            ->setParameter('uuid', $uuid)
            ->setParameter('restaurant', $restaurant)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
