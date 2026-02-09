<?php

namespace App\Repository;

use App\Entity\Restaurant;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<User>
 */
class UserRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    /**
     * Find a user by email address.
     */
    public function findByEmail(string $email): ?User
    {
        return $this->createQueryBuilder('u')
            ->where('u.email = :email')
            ->setParameter('email', $email)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Find the first user (owner) associated with a restaurant.
     */
    public function findOneByRestaurant(Restaurant $restaurant): ?User
    {
        return $this->createQueryBuilder('u')
            ->where('u.restaurant = :restaurant')
            ->setParameter('restaurant', $restaurant)
            ->orderBy('u.createdAt', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
