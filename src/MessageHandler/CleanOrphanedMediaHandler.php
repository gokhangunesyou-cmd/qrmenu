<?php

namespace App\MessageHandler;

use App\Infrastructure\Storage\StorageInterface;
use App\Message\CleanOrphanedMediaMessage;
use App\Repository\MediaRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class CleanOrphanedMediaHandler
{
    public function __construct(
        private readonly MediaRepository $mediaRepository,
        private readonly StorageInterface $storage,
        private readonly EntityManagerInterface $em,
    ) {
    }

    public function __invoke(CleanOrphanedMediaMessage $message): void
    {
        $cutoff = new \DateTimeImmutable(sprintf('-%d days', $message->daysOld));

        // Find soft-deleted media older than cutoff (bypass SoftDeleteFilter)
        $this->em->getFilters()->disable('soft_delete_filter');

        $orphaned = $this->mediaRepository->createQueryBuilder('m')
            ->where('m.deletedAt IS NOT NULL')
            ->andWhere('m.deletedAt < :cutoff')
            ->setParameter('cutoff', $cutoff)
            ->getQuery()
            ->getResult();

        foreach ($orphaned as $media) {
            // Delete from S3
            try {
                $this->storage->delete($media->getStoragePath());
            } catch (\Throwable) {
                // Storage may already be gone — continue with DB cleanup
            }

            // Hard-delete from DB
            $this->em->remove($media);
        }

        $this->em->flush();
        $this->em->getFilters()->enable('soft_delete_filter');
    }
}
