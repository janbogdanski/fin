<?php

declare(strict_types=1);

namespace App\TaxCalc\Infrastructure\Doctrine;

use App\TaxCalc\Domain\Model\ClosedPosition;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\PreRemoveEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\Events;

/**
 * Prevents UPDATE and DELETE operations on ClosedPosition entities.
 *
 * ClosedPosition records form the FIFO audit trail for tax calculations.
 * They are append-only by design: once created, they must never be modified
 * or deleted. This listener enforces that invariant at the ORM level,
 * throwing a DomainException if any code attempts to update or remove
 * a ClosedPosition.
 *
 * This is a defense-in-depth measure. The domain model (readonly class)
 * already prevents in-memory mutation, but this listener guards against
 * direct EntityManager operations (e.g., $em->remove($closedPosition)).
 */
#[AsDoctrineListener(event: Events::preUpdate)]
#[AsDoctrineListener(event: Events::preRemove)]
final class ClosedPositionImmutabilityListener
{
    public function preUpdate(PreUpdateEventArgs $args): void
    {
        if ($args->getObject() instanceof ClosedPosition) {
            throw new \DomainException(
                'ClosedPosition is append-only (FIFO audit trail). UPDATE operations are forbidden.',
            );
        }
    }

    public function preRemove(PreRemoveEventArgs $args): void
    {
        if ($args->getObject() instanceof ClosedPosition) {
            throw new \DomainException(
                'ClosedPosition is append-only (FIFO audit trail). DELETE operations are forbidden.',
            );
        }
    }
}
