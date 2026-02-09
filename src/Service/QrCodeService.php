<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\QrCode;
use App\Entity\Restaurant;
use App\Enum\QrCodeType;
use App\Repository\QrCodeRepository;
use App\Infrastructure\QrCode\QrCodeGenerator;
use App\Infrastructure\Storage\StorageInterface;
use App\DTO\Request\Admin\CreateQrCodeRequest;
use App\Exception\EntityNotFoundException;
use App\Exception\ValidationException;
use Doctrine\ORM\EntityManagerInterface;

class QrCodeService
{
    public function __construct(
        private readonly QrCodeRepository $qrCodeRepository,
        private readonly QrCodeGenerator $qrCodeGenerator,
        private readonly StorageInterface $storage,
        private readonly EntityManagerInterface $entityManager,
        private readonly string $publicMenuUrl,
    ) {
    }

    /**
     * @return QrCode[]
     */
    public function listByRestaurant(Restaurant $restaurant): array
    {
        return $this->qrCodeRepository->findByRestaurant($restaurant);
    }

    /**
     * @throws ValidationException
     */
    public function create(CreateQrCodeRequest $request, Restaurant $restaurant): QrCode
    {
        $type = QrCodeType::from($request->type);

        // Validate: table type requires a tableNumber
        if ($type === QrCodeType::TABLE && $request->tableNumber === null) {
            throw new ValidationException([
                ['field' => 'tableNumber', 'message' => 'Table number is required for table QR codes.'],
            ]);
        }

        // Build the URL that the QR code encodes
        $encodedUrl = $this->buildEncodedUrl($restaurant, $type, $request->tableNumber);

        $qrCode = new QrCode($restaurant, $type, $encodedUrl);

        if ($type === QrCodeType::TABLE) {
            $qrCode->setTableNumber($request->tableNumber);
            $qrCode->setTableLabel($request->tableLabel ?? sprintf('Table %d', $request->tableNumber));
        }

        $this->entityManager->persist($qrCode);
        $this->entityManager->flush();

        // Generate QR code image and upload to S3
        $imageBytes = $this->qrCodeGenerator->generate($encodedUrl);
        $storagePath = sprintf('qrcodes/%s/%s.png', $restaurant->getUuid()->toString(), $qrCode->getUuid()->toString());
        $this->storage->put($storagePath, $imageBytes, 'image/png');

        $qrCode->setStoragePath($storagePath);
        $this->entityManager->flush();

        return $qrCode;
    }

    /**
     * @throws EntityNotFoundException
     */
    public function delete(string $uuid, Restaurant $restaurant): void
    {
        $qrCode = $this->qrCodeRepository->findOneByUuidAndRestaurant($uuid, $restaurant);

        if ($qrCode === null) {
            throw new EntityNotFoundException('QrCode', $uuid);
        }

        // Delete the image from storage if it exists
        if ($qrCode->getStoragePath() !== null) {
            $this->storage->delete($qrCode->getStoragePath());
        }

        $this->entityManager->remove($qrCode);
        $this->entityManager->flush();
    }

    private function buildEncodedUrl(Restaurant $restaurant, QrCodeType $type, ?int $tableNumber): string
    {
        $baseUrl = sprintf('%s/%s', rtrim($this->publicMenuUrl, '/'), $restaurant->getSlug());

        if ($type === QrCodeType::TABLE && $tableNumber !== null) {
            return sprintf('%s?table=%d', $baseUrl, $tableNumber);
        }

        return $baseUrl;
    }
}
