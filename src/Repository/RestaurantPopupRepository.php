<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Restaurant;
use App\Entity\RestaurantPopup;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<RestaurantPopup>
 */
class RestaurantPopupRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RestaurantPopup::class);
    }

    /**
     * @return RestaurantPopup[]
     */
    public function findByRestaurantOrdered(Restaurant $restaurant): array
    {
        return $this->createQueryBuilder('popup')
            ->leftJoin('popup.imageMedia', 'imageMedia')
            ->addSelect('imageMedia')
            ->where('popup.restaurant = :restaurant')
            ->setParameter('restaurant', $restaurant)
            ->orderBy('popup.startsAt', 'DESC')
            ->addOrderBy('popup.id', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findOneByUuidAndRestaurant(string $uuid, Restaurant $restaurant): ?RestaurantPopup
    {
        return $this->createQueryBuilder('popup')
            ->leftJoin('popup.imageMedia', 'imageMedia')
            ->addSelect('imageMedia')
            ->where('popup.uuid = :uuid')
            ->andWhere('popup.restaurant = :restaurant')
            ->setParameter('uuid', $uuid)
            ->setParameter('restaurant', $restaurant)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findActiveForRestaurantAt(Restaurant $restaurant, \DateTimeImmutable $dateTime): ?RestaurantPopup
    {
        return $this->createQueryBuilder('popup')
            ->leftJoin('popup.imageMedia', 'imageMedia')
            ->addSelect('imageMedia')
            ->where('popup.restaurant = :restaurant')
            ->andWhere('popup.isActive = true')
            ->andWhere('popup.startsAt <= :dateTime')
            ->andWhere('popup.endsAt >= :dateTime')
            ->setParameter('restaurant', $restaurant)
            ->setParameter('dateTime', $dateTime)
            ->orderBy('popup.startsAt', 'DESC')
            ->addOrderBy('popup.id', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
