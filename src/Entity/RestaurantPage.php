<?php

namespace App\Entity;

use App\Enum\PageType;
use App\Repository\RestaurantPageRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: RestaurantPageRepository::class)]
#[ORM\Table(name: 'restaurant_pages')]
#[ORM\UniqueConstraint(name: 'uq_restaurant_page_type', columns: ['restaurant_id', 'type'])]
#[ORM\Index(columns: ['restaurant_id'], name: 'idx_pages_restaurant')]
class RestaurantPage
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Restaurant::class, inversedBy: 'pages')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Restaurant $restaurant;

    #[ORM\Column(length: 20, enumType: PageType::class)]
    private PageType $type;

    #[ORM\Column(length: 200)]
    private string $title;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $body = null;

    #[ORM\Column]
    private bool $isPublished = false;

    #[ORM\Column(type: 'datetimetz_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetimetz_immutable')]
    private \DateTimeImmutable $updatedAt;

    public function __construct(Restaurant $restaurant, PageType $type, string $title)
    {
        $this->restaurant = $restaurant;
        $this->type = $type;
        $this->title = $title;
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getRestaurant(): Restaurant
    {
        return $this->restaurant;
    }

    public function getType(): PageType
    {
        return $this->type;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): void
    {
        $this->title = $title;
    }

    public function getBody(): ?string
    {
        return $this->body;
    }

    public function setBody(?string $body): void
    {
        $this->body = $body;
    }

    public function isPublished(): bool
    {
        return $this->isPublished;
    }

    public function setIsPublished(bool $isPublished): void
    {
        $this->isPublished = $isPublished;
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
