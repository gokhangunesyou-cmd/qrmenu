<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Media;
use App\Entity\Restaurant;
use App\Form\RestaurantProfileType;
use App\Infrastructure\Storage\StorageInterface;
use Doctrine\ORM\EntityManagerInterface;
use Ramsey\Uuid\Uuid;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/restaurant')]
class RestaurantWebController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly StorageInterface $storage,
    ) {
    }

    #[Route('', name: 'admin_restaurant_show', methods: ['GET'])]
    public function show(): Response
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        $restaurant = $user->getRestaurant();

        if (!$restaurant) {
            throw $this->createNotFoundException('Restaurant not found');
        }

        return $this->render('admin/restaurant/show.html.twig', [
            'restaurant' => $restaurant,
        ]);
    }

    #[Route('/edit', name: 'admin_restaurant_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request): Response
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        $restaurant = $user->getRestaurant();

        if (!$restaurant) {
            throw $this->createNotFoundException('Restaurant not found');
        }

        $form = $this->createForm(RestaurantProfileType::class, $restaurant);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Handle logo upload
            $logoFile = $form->get('logoFile')->getData();
            if ($logoFile instanceof UploadedFile) {
                $media = $this->uploadMedia($logoFile, $restaurant);
                $restaurant->setLogoMedia($media);
            }

            // Handle cover upload
            $coverFile = $form->get('coverFile')->getData();
            if ($coverFile instanceof UploadedFile) {
                $media = $this->uploadMedia($coverFile, $restaurant);
                $restaurant->setCoverMedia($media);
            }

            $this->em->flush();

            $this->addFlash('success', 'Restoran bilgileri başarıyla güncellendi.');

            return $this->redirectToRoute('admin_restaurant_show');
        }

        return $this->render('admin/restaurant/edit.html.twig', [
            'restaurant' => $restaurant,
            'form' => $form,
        ]);
    }

    private function uploadMedia(UploadedFile $file, Restaurant $restaurant): Media
    {
        $extension = $file->guessExtension() ?? 'jpg';
        $mediaUuid = Uuid::uuid7()->toString();
        $storagePath = sprintf('media/%s/%s.%s', $restaurant->getUuid()->toString(), $mediaUuid, $extension);

        // Upload to storage
        $this->storage->put($storagePath, $file->getContent(), $file->getMimeType());

        // Create media entity
        $media = new Media(
            $file->getClientOriginalName(),
            $storagePath,
            $file->getMimeType() ?? 'image/jpeg',
            $file->getSize(),
            $restaurant
        );

        $this->em->persist($media);

        return $media;
    }
}
