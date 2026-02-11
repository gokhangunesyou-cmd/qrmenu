<?php

namespace App\Entity;

use App\Enum\QrCodeType;
use App\Repository\QrCodeRepository;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

#[ORM\Entity(repositoryClass: QrCodeRepository::class)]
#[ORM\Table(name: 'qr_codes')]
#[ORM\Index(columns: ['restaurant_id'], name: 'idx_qr_codes_restaurant')]
class QrCode
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: 'uuid', unique: true)]
    private UuidInterface $uuid;

    #[ORM\ManyToOne(targetEntity: Restaurant::class, inversedBy: 'qrCodes')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Restaurant $restaurant;

    #[ORM\Column(length: 20, enumType: QrCodeType::class)]
    private QrCodeType $type;

    #[ORM\Column(type: 'smallint', nullable: true)]
    private ?int $tableNumber = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $tableLabel = null;

    #[ORM\Column(length: 500)]
    private string $encodedUrl;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $storagePath = null;

    #[ORM\Column(length: 7, nullable: true)]
    private ?string $foregroundColor = null;

    #[ORM\Column(length: 7, nullable: true)]
    private ?string $backgroundColor = null;

    #[ORM\Column(type: 'smallint', nullable: true)]
    private ?int $borderRadius = null;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $logoStoragePath = null;

    #[ORM\Column(type: 'smallint')]
    private int $qrSize = 512;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $labelBackgroundStoragePath = null;

    #[ORM\Column]
    private bool $isActive = true;

    #[ORM\Column(type: 'datetimetz_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetimetz_immutable')]
    private \DateTimeImmutable $updatedAt;

    public function __construct(Restaurant $restaurant, QrCodeType $type, string $encodedUrl)
    {
        $this->uuid = Uuid::uuid7();
        $this->restaurant = $restaurant;
        $this->type = $type;
        $this->encodedUrl = $encodedUrl;
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUuid(): UuidInterface
    {
        return $this->uuid;
    }

    public function getRestaurant(): Restaurant
    {
        return $this->restaurant;
    }

    public function getType(): QrCodeType
    {
        return $this->type;
    }

    public function getTableNumber(): ?int
    {
        return $this->tableNumber;
    }

    public function setTableNumber(?int $tableNumber): void
    {
        $this->tableNumber = $tableNumber;
    }

    public function getTableLabel(): ?string
    {
        return $this->tableLabel;
    }

    public function setTableLabel(?string $tableLabel): void
    {
        $this->tableLabel = $tableLabel;
    }

    public function getEncodedUrl(): string
    {
        return $this->encodedUrl;
    }

    public function getStoragePath(): ?string
    {
        return $this->storagePath;
    }

    public function setStoragePath(?string $storagePath): void
    {
        $this->storagePath = $storagePath;
    }

    public function getForegroundColor(): ?string
    {
        return $this->foregroundColor;
    }

    public function setForegroundColor(?string $foregroundColor): void
    {
        $this->foregroundColor = $foregroundColor;
    }

    public function getBackgroundColor(): ?string
    {
        return $this->backgroundColor;
    }

    public function setBackgroundColor(?string $backgroundColor): void
    {
        $this->backgroundColor = $backgroundColor;
    }

    public function getBorderRadius(): ?int
    {
        return $this->borderRadius;
    }

    public function setBorderRadius(?int $borderRadius): void
    {
        $this->borderRadius = $borderRadius;
    }

    public function getLogoStoragePath(): ?string
    {
        return $this->logoStoragePath;
    }

    public function setLogoStoragePath(?string $logoStoragePath): void
    {
        $this->logoStoragePath = $logoStoragePath;
    }

    public function getQrSize(): int
    {
        return $this->qrSize;
    }

    public function setQrSize(int $qrSize): void
    {
        $this->qrSize = $qrSize;
    }

    public function getLabelBackgroundStoragePath(): ?string
    {
        return $this->labelBackgroundStoragePath;
    }

    public function setLabelBackgroundStoragePath(?string $labelBackgroundStoragePath): void
    {
        $this->labelBackgroundStoragePath = $labelBackgroundStoragePath;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): void
    {
        $this->isActive = $isActive;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeImmutable $updatedAt): void
    {
        $this->updatedAt = $updatedAt;
    }
}
