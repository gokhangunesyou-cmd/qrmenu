<?php

namespace App\Fixtures;

use App\Entity\Plan;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class PlanFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $free = $manager->getRepository(Plan::class)->findOneBy(['code' => 'free']);
        if ($free === null) {
            $free = new Plan('free', 'Free', 1, 1, '0.00');
            $free->setDescription('1 restoran 1 kullanici yillik plan');
            $manager->persist($free);
        } else {
            $free->setMaxRestaurants(1);
            $free->setMaxUsers(1);
            $free->setYearlyPrice('0.00');
            $free->setDescription('1 restoran 1 kullanici yillik plan');
        }

        $premium = $manager->getRepository(Plan::class)->findOneBy(['code' => 'premium']);
        if ($premium === null) {
            $premium = new Plan('premium', 'Premium', 20, 25, '0.00');
            $premium->setDescription('20 restoran 25 kullanici yillik plan');
            $manager->persist($premium);
        } else {
            $premium->setMaxRestaurants(20);
            $premium->setMaxUsers(25);
            $premium->setYearlyPrice('0.00');
            $premium->setDescription('20 restoran 25 kullanici yillik plan');
        }

        $manager->flush();
    }
}
