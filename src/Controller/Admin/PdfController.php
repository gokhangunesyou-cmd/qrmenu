<?php

namespace App\Controller\Admin;

use App\DTO\Request\Admin\GeneratePdfRequest;
use App\DTO\Response\Admin\PdfTemplateResponse;
use App\Entity\PdfTemplate;
use App\Service\PdfService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

class PdfController extends AbstractController
{
    public function __construct(
        private readonly PdfService $pdfService,
    ) {
    }

    #[Route('/pdf-templates', name: 'admin_pdf_list_templates', methods: ['GET'])]
    public function listTemplates(): JsonResponse
    {
        $templates = $this->pdfService->listTemplates();

        return $this->json(array_map(fn(PdfTemplate $t) => new PdfTemplateResponse(
            slug: $t->getSlug(),
            name: $t->getName(),
            description: $t->getDescription(),
            previewImageUrl: $t->getPreviewImageUrl(),
            pageSize: $t->getPageSize(),
            labelsPerPage: $t->getLabelsPerPage(),
        ), $templates));
    }

    #[Route('/qr-codes/pdf', name: 'admin_pdf_generate', methods: ['POST'])]
    public function generatePdf(#[MapRequestPayload] GeneratePdfRequest $request): Response
    {
        $restaurant = $this->getUser()->getRestaurant();
        $pdfContent = $this->pdfService->generate($request->templateSlug, $request->qrCodeUuids, $restaurant);

        return new Response($pdfContent, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => sprintf('attachment; filename="qr-labels-%s.pdf"', $restaurant->getSlug()),
        ]);
    }
}
