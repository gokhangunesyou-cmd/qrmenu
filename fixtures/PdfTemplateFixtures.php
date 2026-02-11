<?php

namespace App\Fixtures;

use App\Entity\PdfTemplate;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class PdfTemplateFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $templates = [
            ['slug' => 'classic', 'name' => 'Classic Kare', 'path' => 'pdf/label_classic.html.twig', 'labels' => 6],
            ['slug' => 'modern', 'name' => 'Modern Kart', 'path' => 'pdf/label_modern.html.twig', 'labels' => 6],
            ['slug' => 'elegant', 'name' => 'Elegant Gold', 'path' => 'pdf/label_elegant.html.twig', 'labels' => 6],
            ['slug' => 'minimal', 'name' => 'Minimal Temiz', 'path' => 'pdf/label_minimal.html.twig', 'labels' => 6],
            ['slug' => 'bistro', 'name' => 'Bistro Rounded', 'path' => 'pdf/label_bistro.html.twig', 'labels' => 6],
            ['slug' => 'rustic', 'name' => 'Rustic Craft', 'path' => 'pdf/label_rustic.html.twig', 'labels' => 6],
        ];

        foreach ($templates as $data) {
            $existing = $manager->getRepository(PdfTemplate::class)->findOneBy(['slug' => $data['slug']]);
            if ($existing !== null) {
                continue;
            }

            $template = new PdfTemplate($data['slug'], $data['name'], $data['path']);
            $manager->persist($template);
        }

        $manager->flush();
    }
}
