<?php

namespace App\EventSubscriber;

use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\Events;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\Persistence\Event\LifecycleEventArgs;

class DoctrineSanitizeSubscriber implements EventSubscriber
{
    public function getSubscribedEvents(): array
    {
        return [
            Events::prePersist,
            Events::preUpdate,
        ];
    }

    public function prePersist(LifecycleEventArgs $args): void
    {
        $entity = $args->getObject();
        $metadata = $args->getObjectManager()->getClassMetadata($entity::class);
        $this->sanitizeEntity($entity, $metadata);
    }

    public function preUpdate(PreUpdateEventArgs $args): void
    {
        $entity = $args->getObject();
        $em = $args->getObjectManager();
        $metadata = $em->getClassMetadata($entity::class);

        if ($this->sanitizeEntity($entity, $metadata)) {
            $em->getUnitOfWork()->recomputeSingleEntityChangeSet($metadata, $entity);
        }
    }

    private function sanitizeEntity(object $entity, ClassMetadata $metadata): bool
    {
        $hasChanges = false;

        foreach ($metadata->getFieldNames() as $field) {
            $fieldType = $metadata->getTypeOfField($field);
            if (!in_array($fieldType, ['string', 'text'], true)) {
                continue;
            }

            $value = $metadata->getFieldValue($entity, $field);
            if (!is_string($value)) {
                continue;
            }

            $sanitized = $this->sanitizeText($value);
            if ($sanitized !== $value) {
                $metadata->setFieldValue($entity, $field, $sanitized);
                $hasChanges = true;
            }
        }

        return $hasChanges;
    }

    private function sanitizeText(string $value): string
    {
        // Remove control characters while preserving standard whitespace.
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value) ?? $value;

        return trim($value);
    }
}
