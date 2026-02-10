<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\PdfTemplate;
use App\Entity\Restaurant;
use App\Exception\EntityNotFoundException;
use App\Exception\ValidationException;
use App\Infrastructure\Pdf\TableLabelGenerator;
use App\Repository\PdfTemplateRepository;
use App\Repository\QrCodeRepository;

class PdfService
{
    public function __construct(
        private readonly PdfTemplateRepository $pdfTemplateRepository,
        private readonly QrCodeRepository $qrCodeRepository,
        private readonly TableLabelGenerator $tableLabelGenerator,
    ) {
    }

    /**
     * @return PdfTemplate[]
     */
    public function listTemplates(): array
    {
        return $this->pdfTemplateRepository->findAllActive();
    }

    /**
     * Generate a PDF of QR code table labels.
     *
     * @param string[] $qrCodeUuids
     * @return string Binary PDF content
     * @throws EntityNotFoundException
     * @throws ValidationException
     */
    public function generate(string $templateSlug, array $qrCodeUuids, Restaurant $restaurant): string
    {
        $template = $this->pdfTemplateRepository->findOneBy(['slug' => $templateSlug]);
        if ($template === null) {
            throw new EntityNotFoundException('PdfTemplate', $templateSlug);
        }

        if (empty($qrCodeUuids)) {
            throw new ValidationException([
                ['field' => 'qrCodeUuids', 'message' => 'At least one QR code UUID is required.'],
            ]);
        }

        // Resolve QR codes, ensuring they all belong to this restaurant
        $qrCodes = [];
        foreach ($qrCodeUuids as $uuid) {
            $qrCode = $this->qrCodeRepository->findOneByUuidAndRestaurant($uuid, $restaurant);
            if ($qrCode === null) {
                throw new EntityNotFoundException('QrCode', $uuid);
            }
            $qrCodes[] = $qrCode;
        }

        return $this->tableLabelGenerator->generate($template, $qrCodes, $restaurant);
    }
}
