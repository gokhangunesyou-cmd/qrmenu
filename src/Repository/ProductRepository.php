<?php

namespace App\Repository;

use App\Entity\Category;
use App\Entity\Product;
use App\Entity\Restaurant;
use App\Enum\ProductStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Product>
 */
class ProductRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Product::class);
    }

    /**
     * Find products by restaurant with optional category and status filters.
     *
     * @return Product[]
     */
    public function findByRestaurantFiltered(Restaurant $restaurant, ?Category $category = null, ?ProductStatus $status = null): array
    {
        $qb = $this->createQueryBuilder('p')
            ->leftJoin('p.images', 'pi')
            ->addSelect('pi')
            ->leftJoin('pi.media', 'pm')
            ->addSelect('pm')
            ->where('p.restaurant = :restaurant')
            ->setParameter('restaurant', $restaurant);

        if ($category !== null) {
            $qb->andWhere('p.category = :category')
                ->setParameter('category', $category);
        }

        if ($status !== null) {
            $qb->andWhere('p.status = :status')
                ->setParameter('status', $status);
        }

        return $qb->orderBy('p.category', 'ASC')
            ->addOrderBy('p.sortOrder', 'ASC')
            ->addOrderBy('p.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find products by restaurant and optionally filter by category.
     *
     * @return Product[]
     */
    public function findByRestaurantAndCategory(Restaurant $restaurant, ?Category $category = null): array
    {
        $qb = $this->createQueryBuilder('p')
            ->where('p.restaurant = :restaurant')
            ->setParameter('restaurant', $restaurant);

        if ($category !== null) {
            $qb->andWhere('p.category = :category')
                ->setParameter('category', $category);
        }

        return $qb->orderBy('p.category', 'ASC')
            ->addOrderBy('p.sortOrder', 'ASC')
            ->addOrderBy('p.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find all products pending approval.
     *
     * @return Product[]
     */
    public function findPendingApproval(): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.status = :status')
            ->setParameter('status', ProductStatus::PENDING_APPROVAL)
            ->orderBy('p.submittedAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find all approved products from the global catalog (no restaurant association).
     *
     * @return Product[]
     */
    public function findApprovedCatalog(): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.status = :status')
            ->andWhere('p.restaurant IS NULL')
            ->setParameter('status', ProductStatus::APPROVED)
            ->orderBy('p.category', 'ASC')
            ->addOrderBy('p.sortOrder', 'ASC')
            ->addOrderBy('p.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find products by restaurant with optional category/search filters and pagination.
     *
     * @return array{items: Product[], total: int}
     */
    public function findByRestaurantPaginated(
        Restaurant $restaurant,
        ?Category $category = null,
        ?string $search = null,
        int $page = 1,
        int $limit = 20,
    ): array {
        $qb = $this->createQueryBuilder('p')
            ->leftJoin('p.images', 'pi')
            ->addSelect('pi')
            ->leftJoin('pi.media', 'pm')
            ->addSelect('pm')
            ->leftJoin('p.category', 'c')
            ->addSelect('c')
            ->where('p.restaurant = :restaurant')
            ->setParameter('restaurant', $restaurant);

        if ($category !== null) {
            $qb->andWhere('p.category = :category')
                ->setParameter('category', $category);
        }

        if ($search !== null && $search !== '') {
            $qb->andWhere('LOWER(p.name) LIKE LOWER(:search)')
                ->setParameter('search', '%' . $search . '%');
        }

        // Count total
        $countQb = clone $qb;
        $total = (int) $countQb->select('COUNT(DISTINCT p.id)')->getQuery()->getSingleScalarResult();

        // Paginate
        $items = $qb
            ->orderBy('c.sortOrder', 'ASC')
            ->addOrderBy('p.sortOrder', 'ASC')
            ->addOrderBy('p.name', 'ASC')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return [
            'items' => $items,
            'total' => $total,
        ];
    }

    /**
     * Find one product by UUID and restaurant.
     */
    public function findOneByUuidAndRestaurant(string $uuid, Restaurant $restaurant): ?Product
    {
        return $this->createQueryBuilder('p')
            ->where('p.uuid = :uuid')
            ->andWhere('p.restaurant = :restaurant')
            ->setParameter('uuid', $uuid)
            ->setParameter('restaurant', $restaurant)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Find one active product for public menu detail by UUID and restaurant.
     */
    public function findActiveByUuidForMenu(Restaurant $restaurant, string $uuid): ?Product
    {
        return $this->createQueryBuilder('p')
            ->leftJoin('p.images', 'pi')
            ->addSelect('pi')
            ->leftJoin('pi.media', 'pm')
            ->addSelect('pm')
            ->leftJoin('p.category', 'c')
            ->addSelect('c')
            ->where('p.restaurant = :restaurant')
            ->andWhere('p.uuid = :uuid')
            ->andWhere('p.isActive = true')
            ->andWhere('p.deletedAt IS NULL')
            ->setParameter('restaurant', $restaurant)
            ->setParameter('uuid', $uuid)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @param int[] $productIds
     */
    public function incrementMenuViewCountsByIds(array $productIds): void
    {
        $ids = array_values(array_unique(array_filter(
            array_map(static fn (mixed $value): int => (int) $value, $productIds),
            static fn (int $id): bool => $id > 0
        )));

        if ($ids === []) {
            return;
        }

        $this->createQueryBuilder('p')
            ->update()
            ->set('p.menuViewCount', 'p.menuViewCount + 1')
            ->where('p.id IN (:ids)')
            ->setParameter('ids', $ids)
            ->getQuery()
            ->execute();
    }

    public function incrementDetailViewCount(Product $product): void
    {
        if ($product->getId() === null) {
            return;
        }

        $this->createQueryBuilder('p')
            ->update()
            ->set('p.detailViewCount', 'p.detailViewCount + 1')
            ->where('p.id = :id')
            ->setParameter('id', $product->getId())
            ->getQuery()
            ->execute();
    }

    /**
     * @return Product[]
     */
    public function findMostViewedByRestaurant(Restaurant $restaurant, int $limit = 5): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.restaurant = :restaurant')
            ->andWhere('p.deletedAt IS NULL')
            ->andWhere('p.menuViewCount > 0 OR p.detailViewCount > 0')
            ->setParameter('restaurant', $restaurant)
            ->orderBy('p.menuViewCount', 'DESC')
            ->addOrderBy('p.detailViewCount', 'DESC')
            ->addOrderBy('p.name', 'ASC')
            ->setMaxResults(max(1, $limit))
            ->getQuery()
            ->getResult();
    }

    /**
     * @return Product[]
     */
    public function findMostDetailViewedByRestaurant(Restaurant $restaurant, int $limit = 5): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.restaurant = :restaurant')
            ->andWhere('p.deletedAt IS NULL')
            ->andWhere('p.detailViewCount > 0')
            ->setParameter('restaurant', $restaurant)
            ->orderBy('p.detailViewCount', 'DESC')
            ->addOrderBy('p.name', 'ASC')
            ->setMaxResults(max(1, $limit))
            ->getQuery()
            ->getResult();
    }
}
