<?php

namespace App\Subscriber;

use App\Entity\ObjectEntity;
use App\Entity\Value;
use Doctrine\Bundle\DoctrineBundle\EventSubscriber\EventSubscriberInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Events;
use Doctrine\Persistence\Event\LifecycleEventArgs;
use Exception;
use Ramsey\Uuid\Uuid;

class ValueSubscriber implements EventSubscriberInterface
{
    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    public function getSubscribedEvents(): array
    {
        return [
            Events::preUpdate,
            Events::prePersist,
            Events::preRemove,
        ];
    }

    public function getSubObject(string $uuid): ObjectEntity
    {
        if ($subObject = $this->entityManager->find(ObjectEntity::class, $uuid)) {
            if (!$subObject instanceof ObjectEntity) {
                throw new Exception('No object found with uuid: '.$uuid);
            }
        } elseif ($subObject = $this->entityManager->getRepository(ObjectEntity::class)->findByAnyId($uuid)) {
            if (!$subObject instanceof ObjectEntity) {
                throw new Exception('No object found with uuid: ' . $uuid);
            }
        }

        return $subObject;
    }


    public function preUpdate(LifecycleEventArgs $value): void
    {

        if ($value instanceof Value && $value->getAttribute()->getType() == 'object') {
            if ($value->getArrayValue()) {
                foreach ($value->getArrayValue() as $uuid) {
                    $subObject = $this->getSubObject($uuid);
                    $value->addObject($subObject);
                }
                $value->setArrayValue([]);
            } elseif (($uuid = $value->getStringValue()) && Uuid::isValid($value->getStringValue())) {
                $subObject = $this->getSubObject($uuid);
                $value->addObject($subObject);
            }
        }
    }

    public function prePersist(LifecycleEventArgs $args): void
    {
        $this->preUpdate($args);
    }

    public function preRemove(LifecycleEventArgs $args): void
    {
    }
}
