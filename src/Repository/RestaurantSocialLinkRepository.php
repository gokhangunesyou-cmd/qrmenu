<?php

namespace App\Repository;

use App\Entity\Restaurant;
use App\Entity\RestaurantSocialLink;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<RestaurantSocialLink>
 */
class RestaurantSocialLinkRepository extends ServiceEntityRepository
{
    private const PUBLIC_CACHE_TTL = 3600;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RestaurantSocialLink::class);
    }

    /**
     * Find all social links for a specific restaurant.
     *
     * @return RestaurantSocialLink[]
     */
    public function findByRestaurant(Restaurant $restaurant): array
    {
        $restaurantId = (int) $restaurant->getId();
        $query = $this->createQueryBuilder('rsl')
            ->where('rsl.restaurant = :restaurant')
            ->setParameter('restaurant', $restaurant)
            ->orderBy('rsl.sortOrder', 'ASC')
            ->addOrderBy('rsl.platform', 'ASC')
            ->getQuery();

        $query->enableResultCache(
            self::PUBLIC_CACHE_TTL,
            sprintf('public_social_links_restaurant_%d', $restaurantId)
        );

        return $query->getResult();
    }
}
