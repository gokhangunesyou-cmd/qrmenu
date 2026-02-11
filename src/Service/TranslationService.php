<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Translation;
use App\Repository\TranslationRepository;
use Doctrine\ORM\EntityManagerInterface;

class TranslationService
{
    public function __construct(
        private readonly TranslationRepository $translationRepository,
        private readonly EntityManagerInterface $em,
    ) {
    }

    /**
     * @param list<int> $entityIds
     * @return array<int, array<string, string>>
     */
    public function getFieldMap(string $entityType, array $entityIds, string $locale): array
    {
        return $this->translationRepository->findFieldMapByEntityIds($entityType, $entityIds, $locale);
    }

    /**
     * @param list<int> $entityIds
     * @return array<int, array<string, string>>
     */
    public function getFieldMapWithFallback(
        string $entityType,
        array $entityIds,
        string $locale,
        string $fallbackLocale = 'en',
    ): array {
        $primary = $this->getFieldMap($entityType, $entityIds, $locale);
        if ($fallbackLocale === '' || $fallbackLocale === $locale) {
            return $primary;
        }

        $fallback = $this->getFieldMap($entityType, $entityIds, $fallbackLocale);
        foreach ($primary as $entityId => $fields) {
            foreach ($fields as $field => $value) {
                if (!isset($fallback[$entityId])) {
                    $fallback[$entityId] = [];
                }
                $fallback[$entityId][$field] = $value;
            }
        }

        return $fallback;
    }

    public function upsert(string $entityType, int $entityId, string $locale, string $field, ?string $value): void
    {
        if ($entityId <= 0) {
            return;
        }

        $normalized = trim((string) $value);
        $existing = $this->translationRepository->findOneByUniqueKey($entityType, $entityId, $locale, $field);

        if ($normalized === '') {
            if ($existing instanceof Translation) {
                $this->em->remove($existing);
            }

            return;
        }

        if (!$existing instanceof Translation) {
            $existing = new Translation($entityType, $entityId, $locale, $field, $normalized);
            $this->em->persist($existing);

            return;
        }

        $existing->setValue($normalized);
    }

    public function resolve(array $fieldMap, int $entityId, string $field, ?string $fallback = null): ?string
    {
        $value = $fieldMap[$entityId][$field] ?? null;
        if (!is_string($value)) {
            return $fallback;
        }

        $trimmed = trim($value);

        return $trimmed !== '' ? $trimmed : $fallback;
    }
}
