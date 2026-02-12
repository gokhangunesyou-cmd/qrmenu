<?php

namespace App\Repository;

use App\Entity\BlogComment;
use App\Entity\BlogPost;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<BlogComment>
 */
class BlogCommentRepository extends ServiceEntityRepository
{
    private const PUBLIC_CACHE_TTL = 3600;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, BlogComment::class);
    }

    /**
     * @return list<BlogComment>
     */
    public function findPublishedForPost(BlogPost $post): array
    {
        $postId = (int) $post->getId();
        $query = $this->createQueryBuilder('c')
            ->andWhere('c.post = :post')
            ->andWhere('c.isPublished = true')
            ->setParameter('post', $post)
            ->orderBy('c.createdAt', 'DESC')
            ->getQuery();

        $query->enableResultCache(
            self::PUBLIC_CACHE_TTL,
            sprintf('public_blog_comments_post_%d', $postId)
        );

        return $query->getResult();
    }

    /**
     * @return list<BlogComment>
     */
    public function findAllForModeration(): array
    {
        return $this->createQueryBuilder('c')
            ->leftJoin('c.post', 'p')
            ->addSelect('p')
            ->orderBy('c.isPublished', 'ASC')
            ->addOrderBy('c.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function countPending(): int
    {
        return (int) $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->andWhere('c.isPublished = false')
            ->getQuery()
            ->getSingleScalarResult();
    }
}
