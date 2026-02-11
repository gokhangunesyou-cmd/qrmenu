<?php

namespace App\Entity;

use App\Repository\PlanRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PlanRepository::class)]
#[ORM\Table(name: 'plans')]
class Plan
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 50, unique: true)]
    private string $code;

    #[ORM\Column(length: 120)]
    private string $name;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: 'integer')]
    private int $maxRestaurants;

    #[ORM\Column(type: 'integer')]
    private int $maxUsers;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private string $yearlyPrice;

    #[ORM\Column]
    private bool $isActive = true;

    #[ORM\Column(type: 'datetimetz_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetimetz_immutable')]
    private \DateTimeImmutable $updatedAt;

    public function __construct(
        string $code,
        string $name,
        int $maxRestaurants,
        int $maxUsers,
        string $yearlyPrice,
    ) {
        $this->code = $code;
        $this->name = $name;
        $this->maxRestaurants = $maxRestaurants;
        $this->maxUsers = $maxUsers;
        $this->yearlyPrice = $yearlyPrice;
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): void
    {
        $this->description = $description;
    }

    public function getMaxRestaurants(): int
    {
        return $this->maxRestaurants;
    }

    public function setMaxRestaurants(int $maxRestaurants): void
    {
        $this->maxRestaurants = $maxRestaurants;
    }

    public function getMaxUsers(): int
    {
        return $this->maxUsers;
    }

    public function setMaxUsers(int $maxUsers): void
    {
        $this->maxUsers = $maxUsers;
    }

    public function getYearlyPrice(): string
    {
        return $this->yearlyPrice;
    }

    public function setYearlyPrice(string $yearlyPrice): void
    {
        $this->yearlyPrice = $yearlyPrice;
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
}
