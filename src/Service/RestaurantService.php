<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Category;
use App\Entity\Restaurant;
use App\Entity\Role;
use App\Entity\User;
use App\Repository\CategoryRepository;
use App\Repository\MediaRepository;
use App\Repository\RestaurantRepository;
use App\Repository\ThemeRepository;
use App\Repository\UserRepository;
use App\DTO\Request\Admin\UpdateRestaurantRequest as AdminUpdateRequest;
use App\DTO\Request\SuperAdmin\CreateRestaurantRequest;
use App\DTO\Request\SuperAdmin\UpdateRestaurantRequest as SuperAdminUpdateRequest;
use App\Exception\ConflictException;
use App\Exception\EntityNotFoundException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class RestaurantService
{
    public function __construct(
        private readonly RestaurantRepository $restaurantRepository,
        private readonly ThemeRepository $themeRepository,
        private readonly CategoryRepository $categoryRepository,
        private readonly MediaRepository $mediaRepository,
        private readonly UserRepository $userRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    /**
     * Return the restaurant entity (already loaded via auth context).
     */
    public function getProfile(Restaurant $restaurant): Restaurant
    {
        return $restaurant;
    }

    /**
     * Update the admin-facing restaurant profile fields.
     *
     * @throws EntityNotFoundException
     */
    public function updateProfile(Restaurant $restaurant, AdminUpdateRequest $request): Restaurant
    {
        if ($request->name !== null) {
            $restaurant->setName($request->name);
        }

        if ($request->description !== null) {
            $restaurant->setDescription($request->description);
        }

        if ($request->phone !== null) {
            $restaurant->setPhone($request->phone);
        }

        if ($request->email !== null) {
            $restaurant->setEmail($request->email);
        }

        if ($request->address !== null) {
            $restaurant->setAddress($request->address);
        }

        if ($request->city !== null) {
            $restaurant->setCity($request->city);
        }

        if ($request->countryCode !== null) {
            $restaurant->setCountryCode($request->countryCode);
        }

        if ($request->themeId !== null) {
            $theme = $this->themeRepository->find($request->themeId);
            if ($theme === null) {
                throw new EntityNotFoundException('Theme', (string) $request->themeId);
            }
            $restaurant->setTheme($theme);
        }

        if ($request->defaultLocale !== null) {
            $restaurant->setDefaultLocale($request->defaultLocale);
        }

        if ($request->currencyCode !== null) {
            $restaurant->setCurrencyCode($request->currencyCode);
        }

        if ($request->colorOverrides !== null) {
            $restaurant->setColorOverrides($request->colorOverrides);
        }

        if ($request->metaTitle !== null) {
            $restaurant->setMetaTitle($request->metaTitle);
        }

        if ($request->metaDescription !== null) {
            $restaurant->setMetaDescription($request->metaDescription);
        }

        if ($request->logoMediaUuid !== null) {
            $media = $this->mediaRepository->findOneByUuidAndRestaurant($request->logoMediaUuid, $restaurant);
            if ($media === null) {
                throw new EntityNotFoundException('Media', $request->logoMediaUuid);
            }
            $restaurant->setLogoMedia($media);
        }

        if ($request->coverMediaUuid !== null) {
            $media = $this->mediaRepository->findOneByUuidAndRestaurant($request->coverMediaUuid, $restaurant);
            if ($media === null) {
                throw new EntityNotFoundException('Media', $request->coverMediaUuid);
            }
            $restaurant->setCoverMedia($media);
        }

        $this->entityManager->flush();

        return $restaurant;
    }

    /**
     * Onboard a new restaurant (SuperAdmin action):
     * 1. Create the restaurant with a default theme
     * 2. Create an owner user with ROLE_RESTAURANT_OWNER
     * 3. Copy global default categories into the restaurant
     *
     * @throws ConflictException
     */
    public function onboard(CreateRestaurantRequest $request): Restaurant
    {
        // Check slug uniqueness
        $existing = $this->restaurantRepository->findBySlug($request->slug);
        if ($existing !== null) {
            throw new ConflictException(sprintf('Restaurant with slug "%s" already exists.', $request->slug));
        }

        // Check email uniqueness
        $existingUser = $this->userRepository->findByEmail($request->ownerEmail);
        if ($existingUser !== null) {
            throw new ConflictException(sprintf('User with email "%s" already exists.', $request->ownerEmail));
        }

        // Use the first active theme as default
        $themes = $this->themeRepository->findAllActive();
        if (empty($themes)) {
            throw new EntityNotFoundException('Theme', 'default');
        }
        $defaultTheme = $themes[0];

        // Create restaurant
        $restaurant = new Restaurant($request->name, $request->slug, $defaultTheme);
        $this->entityManager->persist($restaurant);

        // Create owner user
        $user = new User($request->ownerEmail, '', $request->ownerFirstName, $request->ownerLastName);
        $hashedPassword = $this->passwordHasher->hashPassword($user, $request->ownerPassword);
        $user->setPasswordHash($hashedPassword);
        $user->setRestaurant($restaurant);

        // Assign ROLE_RESTAURANT_OWNER
        $ownerRole = $this->entityManager->getRepository(Role::class)->findOneBy(['name' => 'ROLE_RESTAURANT_OWNER']);
        if ($ownerRole !== null) {
            $user->addRole($ownerRole);
        }

        $this->entityManager->persist($user);

        // Copy global default categories
        $globalCategories = $this->categoryRepository->findGlobalDefaults();
        foreach ($globalCategories as $globalCategory) {
            $category = new Category($globalCategory->getName(), $restaurant);
            $category->setDescription($globalCategory->getDescription());
            $category->setIcon($globalCategory->getIcon());
            $category->setSortOrder($globalCategory->getSortOrder());
            $this->entityManager->persist($category);
        }

        $this->entityManager->flush();

        return $restaurant;
    }

    // ─── SuperAdmin operations ───────────────────────────────────────

    /**
     * List all restaurants (SuperAdmin).
     *
     * @return Restaurant[]
     */
    public function listAll(): array
    {
        return $this->restaurantRepository->findBy([], ['createdAt' => 'DESC']);
    }

    /**
     * Update restaurant basics (SuperAdmin).
     *
     * @throws EntityNotFoundException
     * @throws ConflictException
     */
    public function updateBySuperAdmin(string $uuid, SuperAdminUpdateRequest $request): Restaurant
    {
        $restaurant = $this->restaurantRepository->findByUuid($uuid);
        if ($restaurant === null) {
            throw new EntityNotFoundException('Restaurant', $uuid);
        }

        if ($request->name !== null) {
            $restaurant->setName($request->name);
        }

        if ($request->slug !== null) {
            $existing = $this->restaurantRepository->findBySlug($request->slug);
            if ($existing !== null && $existing->getId() !== $restaurant->getId()) {
                throw new ConflictException(sprintf('Slug "%s" is already in use.', $request->slug));
            }
            $restaurant->setSlug($request->slug);
        }

        if ($request->isActive !== null) {
            $restaurant->setIsActive($request->isActive);
        }

        $this->entityManager->flush();

        return $restaurant;
    }

    /**
     * Activate a restaurant (SuperAdmin).
     *
     * @throws EntityNotFoundException
     */
    public function activate(string $uuid): Restaurant
    {
        $restaurant = $this->restaurantRepository->findByUuid($uuid);
        if ($restaurant === null) {
            throw new EntityNotFoundException('Restaurant', $uuid);
        }

        $restaurant->setIsActive(true);
        $this->entityManager->flush();

        return $restaurant;
    }

    /**
     * Find the owner user for a restaurant (first user created for it).
     */
    public function findOwnerByRestaurant(Restaurant $restaurant): ?User
    {
        return $this->userRepository->findOneByRestaurant($restaurant);
    }

    /**
     * Deactivate a restaurant (SuperAdmin).
     *
     * @throws EntityNotFoundException
     */
    public function deactivate(string $uuid): Restaurant
    {
        $restaurant = $this->restaurantRepository->findByUuid($uuid);
        if ($restaurant === null) {
            throw new EntityNotFoundException('Restaurant', $uuid);
        }

        $restaurant->setIsActive(false);
        $this->entityManager->flush();

        return $restaurant;
    }
}
