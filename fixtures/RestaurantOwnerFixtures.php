<?php

namespace App\Fixtures;

use App\Entity\Restaurant;
use App\Entity\Role;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class RestaurantOwnerFixtures extends Fixture implements DependentFixtureInterface
{
    public function __construct(
        private readonly UserPasswordHasherInterface $hasher,
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        $user = $manager->getRepository(User::class)->findOneBy(['email' => 'owner@qrmenu.local']);
        if ($user === null) {
            $user = new User(
                'owner@qrmenu.local',
                '',
                'Restaurant',
                'Owner',
            );
        }

        $user->setPasswordHash($this->hasher->hashPassword($user, 'ChangeMe123!'));

        /** @var Role $ownerRole */
        $ownerRole = $this->getReference(RoleFixtures::ROLE_RESTAURANT_OWNER, Role::class);
        $user->addRole($ownerRole);

        $restaurant = $manager->getRepository(Restaurant::class)->findOneBy(['slug' => 'demo-restoran']);
        if ($restaurant !== null && $user->getRestaurant() === null) {
            $user->setRestaurant($restaurant);
        }

        $manager->persist($user);
        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [
            SuperAdminFixtures::class,
        ];
    }
}
