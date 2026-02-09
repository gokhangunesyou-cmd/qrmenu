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
            ['code' => 'tr', 'name' => 'Turkish'],
            ['code' => 'en', 'name' => 'English'],
            ['code' => 'de', 'name' => 'German'],
            ['code' => 'ar', 'name' => 'Arabic'],
            ['code' => 'fr', 'name' => 'French'],
        ];

        foreach ($locales as $data) {
            $locale = new Locale($data['code'], $data['name']);
            $manager->persist($locale);
        }

        $manager->flush();
    }
}
