<?php

namespace App\Doctrine\Listener;

use Doctrine\ORM\Event\PreRemoveEventArgs;

class SoftDeleteListener
{
    public function preRemove(PreRemoveEventArgs $args): void
    {
        $entity = $args->getObject();

        if (!method_exists($entity, 'setDeletedAt')) {
            return;
        }

        $entity->setDeletedAt(new \DateTimeImmutable());

        $em = $args->getObjectManager();
        $em->persist($entity);

        // Cancel the hard delete by detaching from unit of work scheduling
        $classMetadata = $em->getClassMetadata($entity::class);
        $em->getUnitOfWork()->scheduleExtraUpdate($entity, [
            'deletedAt' => [null, $entity->getDeletedAt()],
        ]);

        // Re-compute changeset to prevent actual DELETE
        $em->getUnitOfWork()->recomputeSingleEntityChangeSet($classMetadata, $entity);
    }
}
