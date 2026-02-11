<?php

namespace App\Fixtures;

use App\Entity\Locale;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class LocaleFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $locales = [
            ['code' => 'tr', 'name' => 'Türkçe'],
            ['code' => 'en', 'name' => 'English'],
            ['code' => 'ar', 'name' => 'العربية'],
            ['code' => 'ru', 'name' => 'Русский'],
        ];
        $allowedCodes = array_map(static fn (array $data): string => $data['code'], $locales);

        foreach ($locales as $data) {
            $existing = $manager->getRepository(Locale::class)->findOneBy(['code' => $data['code']]);
            if ($existing !== null) {
                if (!$existing->isActive()) {
                    $existing->setIsActive(true);
                }
                continue;
            }

            $locale = new Locale($data['code'], $data['name']);
            $manager->persist($locale);
        }

        $allLocales = $manager->getRepository(Locale::class)->findAll();
        foreach ($allLocales as $locale) {
            if (!in_array($locale->getCode(), $allowedCodes, true) && $locale->isActive()) {
                $locale->setIsActive(false);
            }
        }

        $manager->flush();
    }
}
