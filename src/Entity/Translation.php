<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'translations')]
#[ORM\UniqueConstraint(name: 'uq_translation_entity_locale_field', columns: ['entity_type', 'entity_id', 'locale', 'field'])]
#[ORM\Index(columns: ['entity_type', 'entity_id', 'locale'], name: 'idx_translations_lookup')]
class Translation
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 50)]
    private string $entityType;

    #[ORM\Column]
    private int $entityId;

    #[ORM\Column(length: 5)]
    private string $locale;

    #[ORM\Column(length: 50)]
    private string $field;

    #[ORM\Column(type: 'text')]
    private string $value;

    #[ORM\Column(type: 'datetimetz_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetimetz_immutable')]
    private \DateTimeImmutable $updatedAt;

    public function __construct(string $entityType, int $entityId, string $locale, string $field, string $value)
    {
        $this->entityType = $entityType;
        $this->entityId = $entityId;
        $this->locale = $locale;
        $this->field = $field;
        $this->value = $value;
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEntityType(): string
    {
        return $this->entityType;
    }

    public function getEntityId(): int
    {
        return $this->entityId;
    }

    public function getLocale(): string
    {
        return $this->locale;
    }

    public function getField(): string
    {
        return $this->field;
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function setValue(string $value): void
    {
        $this->value = $value;
        $this->updatedAt = new \DateTimeImmutable();
    }
}
