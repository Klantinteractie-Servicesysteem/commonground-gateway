<?php

namespace App\Service;

use App\Entity\ObjectEntity;
use Doctrine\ORM\EntityManagerInterface;

class UhrZaakService
{
    private EntityManagerInterface $entityManager;
    private SynchronizationService $synchronizationService;

    public function __construct(
        EntityManagerInterface $entityManager,
        SynchronizationService $synchronizationService
    ) {
        $this->entityManager = $entityManager;
        $this->synchronizationService = $synchronizationService;
    }

    /**
     * This function returns the identifierPath field from the configuration array.
     *
     * @param array $data          The data from the call
     * @param array $configuration The configuration of the action
     *
     * @return string The identifierPath in the action configuration
     */
    public function getIdentifier(array $data, array $configuration): string
    {
        $dotData = new \Adbar\Dot($data);

        return $dotData->get($configuration['identifierPath']);
    }

    /**
     *
     * @param string $value
     * @param string $path
     * @param array $data
     * @return array
     */
    public function overridePath(string $value, string $path, array $data): array
    {
        $dotData = new \Adbar\Dot($data);
        $dotData->set($path, $value);

        return $dotData->jsonSerialize();
    }

    /**
     *
     * @param array $data
     * @param array $configuration
     * @return array
     */
    public function zaakTypeHandler(array $data, array $configuration): array
    {
        $identifier = $this->getIdentifier($data['request'], $configuration);
        $entity = $this->entityManager->getRepository('App:Entity')->find($configuration['entityId']);
        $objectEntities = $this->entityManager->getRepository('App:ObjectEntity')->findByEntity($entity, ['identificatie' => $identifier]);

        if (count($objectEntities) > 0 && $objectEntities[0] instanceof ObjectEntity) {
            $objectEntity = $objectEntities[0];

            $url = $objectEntity->getValueByAttribute($objectEntity->getEntity()->getAttributeByName('url'))->getStringValue();
            $data['request'] = $this->overridePath($url, $configuration['identifierPath'], $data['request']);
        }

        return $data;
    }

    /**
     * This function returns the eigenschappen field from the configuration array.
     *
     * @param array $data          The data from the call
     * @param array $configuration The configuration of the action
     *
     * @return array The eigenschappen in the action configuration
     */
    public function getExtraElements(array $data, array $configuration): array
    {
        $dotData = new \Adbar\Dot($data);

        return $dotData->get($configuration['eigenschappen']);
    }

    /**
     * This function creates a zaak eigenschap.
     *
     * @param array             $eigenschap   The eigenschap array with zaak, eigenschap and waarde as keys
     * @param ObjectEntity|null $objectEntity The object entity that relates to the entity Eigenschap
     * @param string            $method       The method of the call, defaulted to 'POST'
     *
     * @throws \App\Exception\GatewayException
     * @throws \Psr\Cache\CacheException
     * @throws \Psr\Cache\InvalidArgumentException
     * @throws \Respect\Validation\Exceptions\ComponentException
     *
     * @return ObjectEntity Creates a zaakeigenschap
     */
    public function createObject(array $eigenschap, ObjectEntity $objectEntity): ObjectEntity
    {
        $object = new ObjectEntity();
        $object->setEntity($objectEntity->getEntity());

        return $this->synchronizationService->populateObject($eigenschap, $object, 'POST');
    }

    /**
     * This function returns the zaak, eigenschap and waarde when matched with the element in de action configuration file.
     *
     * @param ObjectEntity|null $objectEntity  The object entity that relates to the entity Eigenschap
     * @param array             $extraElements The extra elements that are taken from the action configuration eigenschappen path
     * @param string            $eigenschap    The naam of the eigenschap that has to be matched
     * @param string            $zaakUrl       The zaakurl the eigenschap is related to
     *
     * @return array|null
     */
    public function getEigenschapValues(ObjectEntity $objectEntity, array $extraElements, string $eigenschap, string $zaakUrl): ?array
    {
        foreach ($extraElements['ns1:extraElement'] as $element) {
            if ($eigenschap == $element['@naam']) {
                $this->usedValues[] = $element['@naam'];

                return [
                    'zaak'       => $zaakUrl,
                    'eigenschap' => $objectEntity->getValueByAttribute($objectEntity->getEntity()->getAttributeByName('url'))->getStringValue(),
                    'waarde'     => $element['#'],
                    'zaaktype'   => $objectEntity->getValueByAttribute($objectEntity->getEntity()->getAttributeByName('zaaktype'))->getStringValue(),
                    'definitie'  => $objectEntity->getValueByAttribute($objectEntity->getEntity()->getAttributeByName('definitie'))->getStringValue(),
                    'naam'       => $objectEntity->getValueByAttribute($objectEntity->getEntity()->getAttributeByName('naam'))->getStringValue(),
                ];
            }
        }

        return null;
    }

    /**
     * This function returns updates the zaak with the unused elements under 'toelichting'.
     *
     * @param array $extraElements The extra elements that are taken from the action configuration eigenschappen path
     * @param array $data          The data from the call
     *
     * @return ObjectEntity
     */
    public function updateZaak(array $extraElements, array $data, ObjectEntity $zaakObject): ObjectEntity
    {
        $unusedElements = [
            'toelichting'                  => '',
            'zaaktype'                     => $data['zaaktype'],
            'startdatum'                   => $data['startdatum'],
            'bronorganisatie'              => $data['bronorganisatie'],
            'verantwoordelijkeOrganisatie' => $data['verantwoordelijkeOrganisatie'],
        ];

        foreach ($extraElements['ns1:extraElement'] as $element) {
            if (in_array($element['@naam'], $this->usedValues)) {
                continue;
            }
            $unusedElements['toelichting'] .= "{$element['@naam']}: {$element['#']}";
        }

        return $this->synchronizationService->populateObject($unusedElements, $zaakObject, 'PUT');
    }

    /**
     * This function gets the name of the eigenschap and returns the getEigenschapValues functie.
     *
     * @param ObjectEntity|null $objectEntity  The object entity that relates to the entity Eigenschap
     * @param array             $extraElements The extra elements that are taken from the action configuration eigenschappen path
     * @param string            $zaakUrl       The zaakurl the eigenschap is related to
     *
     * @return array|null
     */
    public function getEigenschap(?ObjectEntity $objectEntity, array $extraElements, string $zaakUrl): ?array
    {
        if ($objectEntity instanceof ObjectEntity) {
            $eigenschap = $objectEntity->getValueByAttribute($objectEntity->getEntity()->getAttributeByName('naam'));

            return $this->getEigenschapValues($objectEntity, $extraElements, $eigenschap->getStringValue(), $zaakUrl);
        }

        return null;
    }

    /**
     * This function gets the name of the eigenschap and returns the getEigenschapValues functie.
     *
     * @param array $data
     * @param array $configuration
     * @param array $extraElements The extra elements that are taken from the action configuration eigenschappen path
     * @return void
     * @throws \App\Exception\GatewayException
     * @throws \Psr\Cache\CacheException
     * @throws \Psr\Cache\InvalidArgumentException
     * @throws \Respect\Validation\Exceptions\ComponentException
     */
    public function getZaak(array $data, array $configuration, array $extraElements): void
    {
        $zaakEntity = $this->entityManager->getRepository('App:Entity')->find($configuration['zaakEntityId']);
        $zaakObject = $this->entityManager->getRepository('App:ObjectEntity')->findByEntity($zaakEntity, ['url' => $data['response']['url']]);
        $this->updateZaak($extraElements, $data['response'], $zaakObject[0]);
    }

    /**
     * This function gets the name of the eigenschap and returns the getEigenschapValues functie.
     *
     * @param array $data
     * @param array $configuration
     * @return array|null
     * @throws \App\Exception\GatewayException
     * @throws \Psr\Cache\CacheException
     * @throws \Psr\Cache\InvalidArgumentException
     * @throws \Respect\Validation\Exceptions\ComponentException
     */
    public function zaakEigenschappenHandler(array $data, array $configuration): array
    {
        $identifier = $this->getIdentifier($data['request'], $configuration);
        $eigenschapEntity = $this->entityManager->getRepository('App:Entity')->find($configuration['eigenschapEntityId']);
        $objectEntities = $this->entityManager->getRepository('App:ObjectEntity')->findByEntity($eigenschapEntity, ['zaaktype' => $identifier]);
        $extraElements = $this->getExtraElements($data['request'], $configuration);

        if (count($objectEntities) > 0) {
            foreach ($objectEntities as $objectEntity) {
                $eigenschap = $this->getEigenschap($objectEntity, $extraElements, $data['response']['url']);
                $eigenschap !== null && $this->createObject($eigenschap, $objectEntity);
            }
        }
       $this->getZaak($data, $configuration, $extraElements);

        return $data;
    }
}
