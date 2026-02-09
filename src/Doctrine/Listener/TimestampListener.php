<?php

namespace App\Doctrine\Listener;

use Doctrine\ORM\Event\PrePersistEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;

class TimestampListener
{
    public function prePersist(PrePersistEventArgs $args): void
    {
        $entity = $args->getObject();
        $now = new \DateTimeImmutable();

        if (method_exists($entity, 'setCreatedAt') && method_exists($entity, 'getCreatedAt')) {
            if ($entity->getCreatedAt() === null) {
                $entity->setCreatedAt($now);
            }
        }

        if (method_exists($entity, 'setUpdatedAt')) {
            $entity->setUpdatedAt($now);
        }
    }

    public function preUpdate(PreUpdateEventArgs $args): void
    {
        $entity = $args->getObject();

        if (method_exists($entity, 'setUpdatedAt')) {
            $entity->setUpdatedAt(new \DateTimeImmutable());
        }
    }
}
