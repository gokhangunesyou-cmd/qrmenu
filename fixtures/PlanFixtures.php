<?php

namespace App\Fixtures;

use App\Entity\Plan;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class PlanFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $this->upsertPlan(
            $manager,
            code: 'free',
            name: 'Free Plan',
            maxRestaurants: 1,
            maxUsers: 1,
            yearlyPrice: '0.00',
            description: '3 aylik ucretsiz abonelik, 1 restoran, 1 kullanici.',
        );

        $this->upsertPlan(
            $manager,
            code: 'starter',
            name: 'Baslangic Plan',
            maxRestaurants: 1,
            maxUsers: 1,
            yearlyPrice: '0.00',
            description: '599 TL yerine indirimli 0 TL, 1 yillik abonelik, 1 restoran, 1 kullanici.',
        );

        $this->upsertPlan(
            $manager,
            code: 'branch10',
            name: '10 Subeli 10 Kullanicili Plan',
            maxRestaurants: 10,
            maxUsers: 10,
            yearlyPrice: '0.00',
            description: '2999 TL yerine indirimli 0 TL, 1 yillik abonelik, 10 sube, 10 kullanici.',
        );

        $this->upsertPlan(
            $manager,
            code: 'premium',
            name: 'Premium Plan',
            maxRestaurants: 10,
            maxUsers: 10,
            yearlyPrice: '5000.00',
            description: '5000 TL indirimli 1 yillik abonelik, 10 sube, 10 kullanici, data yukleme destegi, ozel QR tasarim destegi, ozel menu tasarimi, 7/24 iletisim.',
        );

        $activeCodes = ['free', 'starter', 'branch10', 'premium'];
        foreach ($manager->getRepository(Plan::class)->findAll() as $plan) {
            if (!in_array($plan->getCode(), $activeCodes, true)) {
                $plan->setIsActive(false);
            }
        }

        $manager->flush();
    }

    private function upsertPlan(
        ObjectManager $manager,
        string $code,
        string $name,
        int $maxRestaurants,
        int $maxUsers,
        string $yearlyPrice,
        string $description,
    ): void {
        $plan = $manager->getRepository(Plan::class)->findOneBy(['code' => $code]);
        if ($plan === null) {
            $plan = new Plan($code, $name, $maxRestaurants, $maxUsers, $yearlyPrice);
            $manager->persist($plan);
        }

        $plan->setName($name);
        $plan->setDescription($description);
        $plan->setMaxRestaurants($maxRestaurants);
        $plan->setMaxUsers($maxUsers);
        $plan->setYearlyPrice($yearlyPrice);
        $plan->setIsActive(true);
    }
}
