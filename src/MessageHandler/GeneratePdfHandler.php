<?php

namespace App\MessageHandler;

use App\Message\GeneratePdfMessage;
use App\Service\PdfService;
use App\Repository\RestaurantRepository;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class GeneratePdfHandler
{
    public function __construct(
        private readonly PdfService $pdfService,
        private readonly RestaurantRepository $restaurantRepository,
    ) {
    }

    public function __invoke(GeneratePdfMessage $message): void
    {
        $restaurant = $this->restaurantRepository->find($message->restaurantId);

        if ($restaurant === null) {
            return;
        }

        $this->pdfService->generate(
            $message->templateSlug,
            $message->qrCodeUuids,
            $restaurant,
        );
    }
}
