<?php

namespace App\Repository;

use App\Entity\Translation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Translation>
 */
class TranslationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Translation::class);
    }

    /**
     * @return array<int, array<string, string>>
     */
    public function findFieldMapByEntityIds(string $entityType, array $entityIds, string $locale): array
    {
        if ($entityIds === []) {
            return [];
        }

        $rows = $this->createQueryBuilder('t')
            ->select('t.entityId AS entityId, t.field AS field, t.value AS value')
            ->andWhere('t.entityType = :entityType')
            ->andWhere('t.locale = :locale')
            ->andWhere('t.entityId IN (:entityIds)')
            ->setParameter('entityType', $entityType)
            ->setParameter('locale', $locale)
            ->setParameter('entityIds', $entityIds)
            ->getQuery()
            ->getArrayResult();

        $map = [];
        foreach ($rows as $row) {
            $entityId = (int) ($row['entityId'] ?? 0);
            $field = (string) ($row['field'] ?? '');
            $value = (string) ($row['value'] ?? '');

            if ($entityId <= 0 || $field === '') {
                continue;
            }

            if (!isset($map[$entityId])) {
                $map[$entityId] = [];
            }

            $map[$entityId][$field] = $value;
        }

        return $map;
    }

    public function findOneByUniqueKey(string $entityType, int $entityId, string $locale, string $field): ?Translation
    {
        return $this->findOneBy([
            'entityType' => $entityType,
            'entityId' => $entityId,
            'locale' => $locale,
            'field' => $field,
        ]);
    }
}
