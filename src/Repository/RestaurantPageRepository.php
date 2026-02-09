<?php

namespace App\Repository;

use App\Entity\Restaurant;
use App\Entity\RestaurantPage;
use App\Enum\PageType;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<RestaurantPage>
 */
class RestaurantPageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RestaurantPage::class);
    }

    /**
     * Find all pages for a specific restaurant.
     *
     * @return RestaurantPage[]
     */
    public function findByRestaurant(Restaurant $restaurant): array
    {
        return $this->createQueryBuilder('rp')
            ->where('rp.restaurant = :restaurant')
            ->setParameter('restaurant', $restaurant)
            ->orderBy('rp.type', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find one page by restaurant and page type.
     */
    public function findOneByRestaurantAndType(Restaurant $restaurant, PageType $type): ?RestaurantPage
    {
        return $this->createQueryBuilder('rp')
            ->where('rp.restaurant = :restaurant')
            ->andWhere('rp.type = :type')
            ->setParameter('restaurant', $restaurant)
            ->setParameter('type', $type)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
