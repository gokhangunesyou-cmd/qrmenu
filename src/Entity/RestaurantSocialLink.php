<?php

namespace App\Entity;

use App\Enum\SocialPlatform;
use App\Repository\RestaurantSocialLinkRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: RestaurantSocialLinkRepository::class)]
#[ORM\Table(name: 'restaurant_social_links')]
#[ORM\UniqueConstraint(name: 'uq_restaurant_platform', columns: ['restaurant_id', 'platform'])]
#[ORM\Index(columns: ['restaurant_id'], name: 'idx_social_links_restaurant')]
class RestaurantSocialLink
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Restaurant::class, inversedBy: 'socialLinks')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Restaurant $restaurant;

    #[ORM\Column(length: 30, enumType: SocialPlatform::class)]
    private SocialPlatform $platform;

    #[ORM\Column(length: 500)]
    private string $url;

    #[ORM\Column(type: 'smallint')]
    private int $sortOrder = 0;

    public function __construct(Restaurant $restaurant, SocialPlatform $platform, string $url)
    {
        $this->restaurant = $restaurant;
        $this->platform = $platform;
        $this->url = $url;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getRestaurant(): Restaurant
    {
        return $this->restaurant;
    }

    public function getPlatform(): SocialPlatform
    {
        return $this->platform;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function setUrl(string $url): void
    {
        $this->url = $url;
    }

    public function getSortOrder(): int
    {
        return $this->sortOrder;
    }

    public function setSortOrder(int $sortOrder): void
    {
        $this->sortOrder = $sortOrder;
    }
}
