<?php

namespace App\Repository;

use App\Entity\BlogPost;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<BlogPost>
 */
class BlogPostRepository extends ServiceEntityRepository
{
    private const PUBLIC_CACHE_TTL = 3600;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, BlogPost::class);
    }

    /**
     * @return BlogPost[]
     */
    public function findLatestPublished(int $limit = 6): array
    {
        $query = $this->createQueryBuilder('b')
            ->where('b.isPublished = true')
            ->orderBy('b.publishedAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery();

        $query->enableResultCache(
            self::PUBLIC_CACHE_TTL,
            sprintf('public_blog_latest_limit_%d', max(1, $limit))
        );

        return $query->getResult();
    }

    /**
     * @return list<BlogPost>
     */
    public function searchPublished(string $query, int $limit = 30): array
    {
        $trimmed = trim($query);
        if ($trimmed === '') {
            return $this->findLatestPublished($limit);
        }

        $dbQuery = $this->createQueryBuilder('b')
            ->andWhere('b.isPublished = true')
            ->andWhere('LOWER(b.title) LIKE :query OR LOWER(b.body) LIKE :query OR LOWER(COALESCE(b.metaDescription, \'\')) LIKE :query')
            ->setParameter('query', '%' . mb_strtolower($trimmed) . '%')
            ->orderBy('b.publishedAt', 'DESC')
            ->addOrderBy('b.id', 'DESC')
            ->setMaxResults($limit)
            ->getQuery();

        $dbQuery->enableResultCache(
            self::PUBLIC_CACHE_TTL,
            sprintf('public_blog_search_limit_%d_%s', max(1, $limit), md5(mb_strtolower($trimmed)))
        );

        return $dbQuery->getResult();
    }

    public function findPublishedBySlug(string $slug): ?BlogPost
    {
        return $this->createQueryBuilder('b')
            ->andWhere('b.slug = :slug')
            ->andWhere('b.isPublished = true')
            ->setParameter('slug', $slug)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
