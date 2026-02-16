<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\RestaurantPopupRepository;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

#[ORM\Entity(repositoryClass: RestaurantPopupRepository::class)]
#[ORM\Table(name: 'restaurant_popups')]
#[ORM\Index(columns: ['restaurant_id'], name: 'idx_restaurant_popups_restaurant')]
#[ORM\Index(columns: ['is_active', 'starts_at', 'ends_at'], name: 'idx_restaurant_popups_active_window')]
class RestaurantPopup
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: 'uuid', unique: true)]
    private UuidInterface $uuid;

    #[ORM\ManyToOne(targetEntity: Restaurant::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Restaurant $restaurant;

    #[ORM\ManyToOne(targetEntity: Media::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Media $imageMedia = null;

    #[ORM\Column(length: 160)]
    private string $title;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $contentHtml = null;

    #[ORM\Column(length: 80, nullable: true)]
    private ?string $buttonLabel = null;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $buttonUrl = null;

    #[ORM\Column(type: 'datetimetz_immutable')]
    private \DateTimeImmutable $startsAt;

    #[ORM\Column(type: 'datetimetz_immutable')]
    private \DateTimeImmutable $endsAt;

    #[ORM\Column(options: ['default' => true])]
    private bool $isActive = true;

    #[ORM\Column(type: 'datetimetz_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetimetz_immutable')]
    private \DateTimeImmutable $updatedAt;

    public function __construct(Restaurant $restaurant, string $title, \DateTimeImmutable $startsAt, \DateTimeImmutable $endsAt)
    {
        $this->uuid = Uuid::uuid7();
        $this->restaurant = $restaurant;
        $this->title = $title;
        $this->startsAt = $startsAt;
        $this->endsAt = $endsAt;
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

    public function setRestaurant(Restaurant $restaurant): void
    {
        $this->restaurant = $restaurant;
    }

    public function getImageMedia(): ?Media
    {
        return $this->imageMedia;
    }

    public function setImageMedia(?Media $imageMedia): void
    {
        $this->imageMedia = $imageMedia;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): void
    {
        $this->title = $title;
    }

    public function getContentHtml(): ?string
    {
        return $this->contentHtml;
    }

    public function setContentHtml(?string $contentHtml): void
    {
        $this->contentHtml = $contentHtml;
    }

    public function getButtonLabel(): ?string
    {
        return $this->buttonLabel;
    }

    public function setButtonLabel(?string $buttonLabel): void
    {
        $this->buttonLabel = $buttonLabel;
    }

    public function getButtonUrl(): ?string
    {
        return $this->buttonUrl;
    }

    public function setButtonUrl(?string $buttonUrl): void
    {
        $this->buttonUrl = $buttonUrl;
    }

    public function getStartsAt(): \DateTimeImmutable
    {
        return $this->startsAt;
    }

    public function setStartsAt(\DateTimeImmutable $startsAt): void
    {
        $this->startsAt = $startsAt;
    }

    public function getEndsAt(): \DateTimeImmutable
    {
        return $this->endsAt;
    }

    public function setEndsAt(\DateTimeImmutable $endsAt): void
    {
        $this->endsAt = $endsAt;
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

    public function setCreatedAt(\DateTimeImmutable $createdAt): void
    {
        $this->createdAt = $createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeImmutable $updatedAt): void
    {
        $this->updatedAt = $updatedAt;
    }

    public function isVisibleAt(\DateTimeImmutable $dateTime): bool
    {
        if (!$this->isActive) {
            return false;
        }

        return $dateTime >= $this->startsAt && $dateTime <= $this->endsAt;
    }
}
