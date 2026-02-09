<?php

namespace App\Fixtures;

use App\Entity\Theme;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class ThemeFixtures extends Fixture
{
    public const THEME_CLASSIC = 'theme-classic';

    public function load(ObjectManager $manager): void
    {
        $themes = [
            [
                'slug' => 'classic',
                'name' => 'Classic Light',
                'ref' => self::THEME_CLASSIC,
                'config' => [
                    'colors' => [
                        'primary' => '#E63946',
                        'secondary' => '#457B9D',
                        'background' => '#F1FAEE',
                        'text' => '#1D3557',
                        'accent' => '#A8DADC',
                    ],
                    'fontFamily' => 'Inter',
                    'borderRadius' => '8px',
                    'headerStyle' => 'centered',
                    'cardStyle' => 'shadow',
                ],
            ],
            [
                'slug' => 'modern',
                'name' => 'Modern',
                'config' => [
                    'colors' => [
                        'primary' => '#6C63FF',
                        'secondary' => '#3F3D56',
                        'background' => '#FFFFFF',
                        'text' => '#2F2E41',
                        'accent' => '#FF6584',
                    ],
                    'fontFamily' => 'Poppins',
                    'borderRadius' => '12px',
                    'headerStyle' => 'left-aligned',
                    'cardStyle' => 'flat',
                ],
            ],
            [
                'slug' => 'dark',
                'name' => 'Dark Mode',
                'config' => [
                    'colors' => [
                        'primary' => '#BB86FC',
                        'secondary' => '#03DAC6',
                        'background' => '#121212',
                        'text' => '#E1E1E1',
                        'accent' => '#CF6679',
                    ],
                    'fontFamily' => 'Roboto',
                    'borderRadius' => '8px',
                    'headerStyle' => 'centered',
                    'cardStyle' => 'elevated',
                ],
            ],
            [
                'slug' => 'minimal',
                'name' => 'Minimal',
                'config' => [
                    'colors' => [
                        'primary' => '#000000',
                        'secondary' => '#666666',
                        'background' => '#FAFAFA',
                        'text' => '#222222',
                        'accent' => '#000000',
                    ],
                    'fontFamily' => 'Helvetica',
                    'borderRadius' => '0px',
                    'headerStyle' => 'left-aligned',
                    'cardStyle' => 'bordered',
                ],
            ],
        ];

        foreach ($themes as $i => $data) {
            $theme = new Theme($data['slug'], $data['name'], $data['config']);
            $theme->setSortOrder($i);
            $manager->persist($theme);

            if (isset($data['ref'])) {
                $this->addReference($data['ref'], $theme);
            }
        }

        $manager->flush();
    }
}
