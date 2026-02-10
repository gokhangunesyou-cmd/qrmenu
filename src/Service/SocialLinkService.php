<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Restaurant;
use App\Entity\RestaurantSocialLink;
use App\Enum\SocialPlatform;
use App\Repository\RestaurantSocialLinkRepository;
use App\Exception\ValidationException;
use Doctrine\ORM\EntityManagerInterface;

class SocialLinkService
{
    public function __construct(
        private readonly RestaurantSocialLinkRepository $restaurantSocialLinkRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @return RestaurantSocialLink[]
     */
    public function listByRestaurant(Restaurant $restaurant): array
    {
        return $this->restaurantSocialLinkRepository->findByRestaurant($restaurant);
    }

    /**
     * Replace all social links for a restaurant atomically.
     * Removes all existing links, then creates the new set.
     *
     * @param array<array{platform: string, url: string, sortOrder?: int}> $links
     * @return RestaurantSocialLink[]
     * @throws ValidationException
     */
    public function replaceAll(array $links, Restaurant $restaurant): array
    {
        // Validate platforms upfront
        foreach ($links as $i => $linkData) {
            if (!isset($linkData['platform'], $linkData['url'])) {
                throw new ValidationException([
                    ['field' => sprintf('links[%d]', $i), 'message' => 'Each link must have "platform" and "url".'],
                ]);
            }

            $platform = SocialPlatform::tryFrom($linkData['platform']);
            if ($platform === null) {
                throw new ValidationException([
                    ['field' => sprintf('links[%d].platform', $i), 'message' => sprintf('Invalid platform "%s".', $linkData['platform'])],
                ]);
            }
        }

        // Remove all existing links
        $existing = $this->restaurantSocialLinkRepository->findByRestaurant($restaurant);
        foreach ($existing as $link) {
            $this->entityManager->remove($link);
        }

        // Create new links
        $result = [];
        foreach ($links as $i => $linkData) {
            $platform = SocialPlatform::from($linkData['platform']);
            $link = new RestaurantSocialLink($restaurant, $platform, $linkData['url']);
            $link->setSortOrder($linkData['sortOrder'] ?? $i);
            $this->entityManager->persist($link);
            $result[] = $link;
        }

        $this->entityManager->flush();

        return $result;
    }
}
