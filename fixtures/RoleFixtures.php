<?php

namespace App\Fixtures;

use App\Entity\Role;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class RoleFixtures extends Fixture
{
    public const ROLE_SUPER_ADMIN = 'role-super-admin';
    public const ROLE_RESTAURANT_OWNER = 'role-restaurant-owner';

    public function load(ObjectManager $manager): void
    {
        $superAdmin = $manager->getRepository(Role::class)->findOneBy(['name' => 'ROLE_SUPER_ADMIN']);
        if ($superAdmin === null) {
            $superAdmin = new Role('ROLE_SUPER_ADMIN', 'Full system administrator');
            $manager->persist($superAdmin);
        }
        $this->addReference(self::ROLE_SUPER_ADMIN, $superAdmin);

        $owner = $manager->getRepository(Role::class)->findOneBy(['name' => 'ROLE_RESTAURANT_OWNER']);
        if ($owner === null) {
            $owner = new Role('ROLE_RESTAURANT_OWNER', 'Restaurant owner with tenant-scoped access');
            $manager->persist($owner);
        }
        $this->addReference(self::ROLE_RESTAURANT_OWNER, $owner);

        $manager->flush();
    }
}
