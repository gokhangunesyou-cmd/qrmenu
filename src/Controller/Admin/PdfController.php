<?php

namespace App\Controller\Admin;

use App\DTO\Request\Admin\GeneratePdfRequest;
use App\DTO\Response\Admin\PdfTemplateResponse;
use App\Entity\PdfTemplate;
use App\Service\PdfService;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

#[OA\Tag(name: 'Admin - PDF')]
class PdfController extends AbstractController
{
    public function __construct(
        private readonly PdfService $pdfService,
    ) {
    }

    #[Route('/pdf-templates', name: 'admin_pdf_list_templates', methods: ['GET'])]
    #[OA\Get(
        path: '/api/admin/pdf-templates',
        summary: 'List available PDF templates',
        tags: ['Admin - PDF']
    )]
    #[OA\Response(
        response: 200,
        description: 'Returns list of PDF templates',
        content: new OA\JsonContent(
            type: 'array',
            items: new OA\Items(type: 'object')
        )
    )]
    #[OA\Response(response: 401, description: 'Unauthorized')]
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
    #[OA\Post(
        path: '/api/admin/qr-codes/pdf',
        summary: 'Generate PDF with QR codes',
        tags: ['Admin - PDF']
    )]
    #[OA\RequestBody(
        required: true
    )]
    #[OA\Response(
        response: 200,
        description: 'PDF generated successfully',
        content: new OA\MediaType(
            mediaType: 'application/pdf',
            schema: new OA\Schema(type: 'string', format: 'binary')
        )
    )]
    #[OA\Response(response: 401, description: 'Unauthorized')]
    #[OA\Response(response: 422, description: 'Validation error')]
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
