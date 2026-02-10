<?php

namespace App\Entity;

use App\Repository\MediaRepository;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

#[ORM\Entity(repositoryClass: MediaRepository::class)]
#[ORM\Table(name: 'media')]
#[ORM\Index(columns: ['restaurant_id'], name: 'idx_media_restaurant')]
class Media
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: 'uuid', unique: true)]
    private UuidInterface $uuid;

    #[ORM\ManyToOne(targetEntity: Restaurant::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Restaurant $restaurant = null;

    #[ORM\Column(length: 255)]
    private string $originalFilename;

    #[ORM\Column(length: 500)]
    private string $storagePath;

    #[ORM\Column(length: 50)]
    private string $mimeType;

    #[ORM\Column]
    private int $fileSize;

    #[ORM\Column(type: 'smallint', nullable: true)]
    private ?int $width = null;

    #[ORM\Column(type: 'smallint', nullable: true)]
    private ?int $height = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $altText = null;

    #[ORM\Column(type: 'datetimetz_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetimetz_immutable', nullable: true)]
    private ?\DateTimeImmutable $deletedAt = null;

    public function __construct(
        string $originalFilename,
        string $storagePath,
        string $mimeType,
        int $fileSize,
        ?Restaurant $restaurant = null,
    ) {
        $this->uuid = Uuid::uuid7();
        $this->originalFilename = $originalFilename;
        $this->storagePath = $storagePath;
        $this->mimeType = $mimeType;
        $this->fileSize = $fileSize;
        $this->restaurant = $restaurant;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUuid(): UuidInterface
    {
        return $this->uuid;
    }

    public function getRestaurant(): ?Restaurant
    {
        return $this->restaurant;
    }

    public function getOriginalFilename(): string
    {
        return $this->originalFilename;
    }

    public function getStoragePath(): string
    {
        return $this->storagePath;
    }

    public function getMimeType(): string
    {
        return $this->mimeType;
    }

    public function getFileSize(): int
    {
        return $this->fileSize;
    }

    public function getWidth(): ?int
    {
        return $this->width;
    }

    public function setWidth(?int $width): void
    {
        $this->width = $width;
    }

    public function getHeight(): ?int
    {
        return $this->height;
    }

    public function setHeight(?int $height): void
    {
        $this->height = $height;
    }

    public function getAltText(): ?string
    {
        return $this->altText;
    }

    public function setAltText(?string $altText): void
    {
        $this->altText = $altText;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getDeletedAt(): ?\DateTimeImmutable
    {
        return $this->deletedAt;
    }

    public function setDeletedAt(?\DateTimeImmutable $deletedAt): void
    {
        $this->deletedAt = $deletedAt;
    }
}
