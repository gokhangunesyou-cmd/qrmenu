<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Restaurant;
use App\Entity\RestaurantPage;
use App\Enum\PageType;
use App\Repository\RestaurantPageRepository;
use App\DTO\Request\Admin\UpsertPageRequest;
use Doctrine\ORM\EntityManagerInterface;

class PageService
{
    public function __construct(
        private readonly RestaurantPageRepository $restaurantPageRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @return RestaurantPage[]
     */
    public function listByRestaurant(Restaurant $restaurant): array
    {
        return $this->restaurantPageRepository->findByRestaurant($restaurant);
    }

    /**
     * Create or update a restaurant page (one page per type per restaurant).
     */
    public function upsert(PageType $type, UpsertPageRequest $request, Restaurant $restaurant): RestaurantPage
    {
        $page = $this->restaurantPageRepository->findOneByRestaurantAndType($restaurant, $type);

        if ($page === null) {
            $page = new RestaurantPage($restaurant, $type, $request->title);
            $this->entityManager->persist($page);
        } else {
            $page->setTitle($request->title);
        }

        if ($request->body !== null) {
            $page->setBody($request->body);
        }

        if ($request->isPublished !== null) {
            $page->setIsPublished($request->isPublished);
        }

        $this->entityManager->flush();

        return $page;
    }
}
