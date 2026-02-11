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
            ['slug' => 'classic', 'name' => 'Classic', 'path' => 'pdf/label_classic.html.twig', 'labels' => 6],
            ['slug' => 'modern', 'name' => 'Modern', 'path' => 'pdf/label_modern.html.twig', 'labels' => 6],
            ['slug' => 'elegant', 'name' => 'Elegant', 'path' => 'pdf/label_elegant.html.twig', 'labels' => 6],
            ['slug' => 'minimal', 'name' => 'Minimal', 'path' => 'pdf/label_minimal.html.twig', 'labels' => 9],
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
