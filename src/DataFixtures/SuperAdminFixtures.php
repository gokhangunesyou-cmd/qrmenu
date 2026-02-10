<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Restaurant;
use App\Entity\Role;
use App\Entity\Theme;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class SuperAdminFixtures extends Fixture
{
    public function __construct(
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        // Ensure roles exist
        $roleOwner = $manager->getRepository(Role::class)->findOneBy(['name' => 'ROLE_RESTAURANT_OWNER']);
        if ($roleOwner === null) {
            $roleOwner = new Role('ROLE_RESTAURANT_OWNER', 'Restaurant owner');
            $manager->persist($roleOwner);
        }

        $roleSuperAdmin = $manager->getRepository(Role::class)->findOneBy(['name' => 'ROLE_SUPER_ADMIN']);
        if ($roleSuperAdmin === null) {
            $roleSuperAdmin = new Role('ROLE_SUPER_ADMIN', 'Super administrator');
            $manager->persist($roleSuperAdmin);
        }

        // Ensure a default theme exists
        $theme = $manager->getRepository(Theme::class)->findOneBy(['slug' => 'default']);
        if ($theme === null) {
            $theme = new Theme('default', 'Varsayılan Tema', []);
            $manager->persist($theme);
        }

        // Check if demo restaurant exists
        $restaurant = $manager->getRepository(Restaurant::class)->findOneBy(['slug' => 'demo-restoran']);
        if ($restaurant === null) {
            $restaurant = new Restaurant('Demo Restoran', 'demo-restoran', $theme);
            $manager->persist($restaurant);
        }

        // Check if admin user exists
        $existingUser = $manager->getRepository(User::class)->findOneBy(['email' => 'admin@qrmenu.test']);
        if ($existingUser !== null) {
            // Update password hash to ensure it works
            $hashedPassword = $this->passwordHasher->hashPassword($existingUser, 'admin123');
            $existingUser->setPasswordHash($hashedPassword);
            $manager->flush();

            return;
        }

        // Create admin user with properly hashed password
        $user = new User(
            email: 'admin@qrmenu.test',
            passwordHash: '', // temporary, will be set below
            firstName: 'Admin',
            lastName: 'User',
        );

        $hashedPassword = $this->passwordHasher->hashPassword($user, 'admin123');
        $user->setPasswordHash($hashedPassword);
        $user->setRestaurant($restaurant);
        $user->addRole($roleOwner);
        $user->addRole($roleSuperAdmin);

        $manager->persist($user);
        $manager->flush();
    }
}
