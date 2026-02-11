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

    /**
     * Find media items by restaurant with optional search and pagination.
     *
     * @return array{items: Media[], total: int}
     */
    public function findByRestaurantPaginated(
        Restaurant $restaurant,
        string $search = '',
        int $page = 1,
        int $limit = 24,
    ): array {
        $qb = $this->createQueryBuilder('m')
            ->where('m.restaurant = :restaurant')
            ->andWhere('m.deletedAt IS NULL')
            ->setParameter('restaurant', $restaurant)
            ->orderBy('m.createdAt', 'DESC');

        if ($search !== '') {
            $qb->andWhere('LOWER(m.originalFilename) LIKE LOWER(:search)')
                ->setParameter('search', '%' . $search . '%');
        }

        // Count total
        $countQb = clone $qb;
        $total = (int) $countQb
            ->resetDQLPart('orderBy')
            ->select('COUNT(m.id)')
            ->getQuery()
            ->getSingleScalarResult();

        // Paginate
        $items = $qb
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return [
            'items' => $items,
            'total' => $total,
        ];
    }
}
