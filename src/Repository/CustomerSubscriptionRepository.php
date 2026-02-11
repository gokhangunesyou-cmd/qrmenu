<?php

namespace App\Repository;

use App\Entity\CustomerAccount;
use App\Entity\CustomerSubscription;
use Doctrine\DBAL\Types\Types;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CustomerSubscription>
 */
class CustomerSubscriptionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CustomerSubscription::class);
    }

    public function findActiveForAccount(CustomerAccount $account, ?\DateTimeImmutable $at = null): ?CustomerSubscription
    {
        $at ??= new \DateTimeImmutable('today');

        return $this->createQueryBuilder('s')
            ->where('s.customerAccount = :account')
            ->andWhere('s.isActive = true')
            ->andWhere('s.startsAt <= :at')
            ->andWhere('s.endsAt >= :at')
            ->setParameter('account', $account)
            ->setParameter('at', $at, Types::DATE_IMMUTABLE)
            ->orderBy('s.endsAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findLatestForAccount(CustomerAccount $account): ?CustomerSubscription
    {
        return $this->createQueryBuilder('s')
            ->where('s.customerAccount = :account')
            ->setParameter('account', $account)
            ->orderBy('s.endsAt', 'DESC')
            ->addOrderBy('s.id', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findLatestActiveForAccount(CustomerAccount $account): ?CustomerSubscription
    {
        return $this->createQueryBuilder('s')
            ->where('s.customerAccount = :account')
            ->andWhere('s.isActive = true')
            ->setParameter('account', $account)
            ->orderBy('s.endsAt', 'DESC')
            ->addOrderBy('s.id', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
