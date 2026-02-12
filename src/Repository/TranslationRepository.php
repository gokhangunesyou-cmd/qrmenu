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
    private const PUBLIC_CACHE_TTL = 3600;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Translation::class);
    }

    /**
     * @return array<int, array<string, string>>
     */
    public function findFieldMapByEntityIds(string $entityType, array $entityIds, string $locale): array
    {
        $normalizedEntityIds = array_values(array_unique(array_filter(
            array_map(static fn (mixed $value): int => (int) $value, $entityIds),
            static fn (int $id): bool => $id > 0
        )));
        sort($normalizedEntityIds);

        if ($normalizedEntityIds === []) {
            return [];
        }

        $query = $this->createQueryBuilder('t')
            ->select('t.entityId AS entityId, t.field AS field, t.value AS value')
            ->andWhere('t.entityType = :entityType')
            ->andWhere('t.locale = :locale')
            ->andWhere('t.entityId IN (:entityIds)')
            ->setParameter('entityType', $entityType)
            ->setParameter('locale', $locale)
            ->setParameter('entityIds', $normalizedEntityIds)
            ->getQuery();

        $query->enableResultCache(
            self::PUBLIC_CACHE_TTL,
            sprintf(
                'public_translations_%s_%s_%s',
                $entityType,
                $locale,
                md5(implode(',', $normalizedEntityIds))
            )
        );

        $rows = $query->getArrayResult();

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
