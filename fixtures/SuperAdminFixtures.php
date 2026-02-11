<?php

namespace App\Fixtures;

use App\Entity\Role;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class SuperAdminFixtures extends Fixture implements DependentFixtureInterface
{
    public function __construct(
        private readonly UserPasswordHasherInterface $hasher,
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        $user = $manager->getRepository(User::class)->findOneBy(['email' => 'admin@qrmenu.local']);
        if ($user === null) {
            $user = new User(
                'admin@qrmenu.local',
                '', // Will be set below
                'Super',
                'Admin',
            );
        }

        $user->setPasswordHash($this->hasher->hashPassword($user, 'ChangeMe123!'));

        /** @var Role $role */
        $superAdminRole = $this->getReference(RoleFixtures::ROLE_SUPER_ADMIN, Role::class);
        $ownerRole = $this->getReference(RoleFixtures::ROLE_RESTAURANT_OWNER, Role::class);
        $user->addRole($superAdminRole);
        $user->addRole($ownerRole);

        $manager->persist($user);
        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [
            RoleFixtures::class,
        ];
    }
}
