<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Product;
use App\Entity\ProductApprovalLog;
use App\Entity\User;
use App\Enum\ApprovalAction;
use App\Enum\ProductStatus;
use App\Repository\ProductRepository;
use App\Repository\ProductApprovalLogRepository;
use App\Exception\EntityNotFoundException;
use App\Exception\InvalidStatusTransitionException;
use Doctrine\ORM\EntityManagerInterface;

class CatalogApprovalService
{
    public function __construct(
        private readonly ProductRepository $productRepository,
        private readonly ProductApprovalLogRepository $productApprovalLogRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @return Product[]
     */
    public function listPending(): array
    {
        return $this->productRepository->findPendingApproval();
    }

    /**
     * Get a product with its approval history.
     *
     * @throws EntityNotFoundException
     */
    public function getDetail(string $uuid): Product
    {
        $product = $this->findByUuid($uuid);

        // Eagerly load approval logs so the controller can map them
        $this->productApprovalLogRepository->findByProduct($product);

        return $product;
    }

    /**
     * @return ProductApprovalLog[]
     */
    public function getApprovalHistory(Product $product): array
    {
        return $this->productApprovalLogRepository->findByProduct($product);
    }

    /**
     * Approve a pending product.
     *
     * @throws EntityNotFoundException
     * @throws InvalidStatusTransitionException
     */
    public function approve(string $uuid, User $user): Product
    {
        $product = $this->findByUuid($uuid);

        if (!$product->getStatus()->canTransitionTo(ProductStatus::APPROVED)) {
            throw new InvalidStatusTransitionException($product->getStatus(), ProductStatus::APPROVED);
        }

        $product->setStatus(ProductStatus::APPROVED);

        $log = new ProductApprovalLog($product, ApprovalAction::APPROVED, $user);
        $this->entityManager->persist($log);

        $this->entityManager->flush();

        return $product;
    }

    /**
     * Reject a pending product with a required note.
     *
     * @throws EntityNotFoundException
     * @throws InvalidStatusTransitionException
     */
    public function reject(string $uuid, string $note, User $user): Product
    {
        $product = $this->findByUuid($uuid);

        if (!$product->getStatus()->canTransitionTo(ProductStatus::REJECTED)) {
            throw new InvalidStatusTransitionException($product->getStatus(), ProductStatus::REJECTED);
        }

        $product->setStatus(ProductStatus::REJECTED);

        $log = new ProductApprovalLog($product, ApprovalAction::REJECTED, $user, $note);
        $this->entityManager->persist($log);

        $this->entityManager->flush();

        return $product;
    }

    private function findByUuid(string $uuid): Product
    {
        $product = $this->productRepository->createQueryBuilder('p')
            ->leftJoin('p.restaurant', 'r')
            ->addSelect('r')
            ->leftJoin('p.images', 'pi')
            ->addSelect('pi')
            ->leftJoin('pi.media', 'pm')
            ->addSelect('pm')
            ->where('p.uuid = :uuid')
            ->setParameter('uuid', $uuid)
            ->getQuery()
            ->getOneOrNullResult();

        if ($product === null) {
            throw new EntityNotFoundException('Product', $uuid);
        }

        return $product;
    }
}
