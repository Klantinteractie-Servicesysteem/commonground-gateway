<?php

namespace App\Service;

use App\Entity\Entity;
use App\Entity\ObjectEntity;
use Doctrine\ORM\EntityManagerInterface;

class MapRelocationService
{
    private EntityManagerInterface $entityManager;
    private TranslationService $translationService;
    private ObjectEntityService $objectEntityService;
    private EavService $eavService;
    private SynchronizationService $synchronizationService;
    private array $configuration;
    private array $data;

    /**
     * @param EntityManagerInterface $entityManager
     */
    public function __construct(
        EntityManagerInterface $entityManager,
        TranslationService $translationService,
        ObjectEntityService $objectEntityService,
        EavService $eavService,
        SynchronizationService $synchronizationService
    ) {
        $this->entityManager = $entityManager;
        $this->translationService = $translationService;
        $this->objectEntityService = $objectEntityService;
        $this->eavService = $eavService;
        $this->synchronizationService = $synchronizationService;

        $this->objectEntityRepo = $this->entityManager->getRepository(ObjectEntity::class);
        $this->entityRepo = $this->entityManager->getRepository(Entity::class);

        $this->mappingIn = [];

        $this->skeletonIn = [];
    }

    /**
     * Maps relocators to their VrijBRP layout.
     *
     * @param array $eigenschap The relocators-element in the SimXML message
     *
     * @return array The resulting relocators array
     */
    public function mapRelocators(array $eigenschap): array
    {
        foreach (json_decode($eigenschap['waarde'], true) as $meeverhuizende) {
            switch ($meeverhuizende['ROL']) {
                case 'P':
                    $declarationType = 'PARTNER';
                    break;
                case 'K':
                    $declarationType = 'ADULT_CHILD_LIVING_WITH_PARENTS';
                    break;
                default:
                    $declarationType = 'ADULT_CHILD_LIVING_WITH_PARENTS';
                    break;
            }
            $relocators[] = [
                'bsn'             => $meeverhuizende['BSN'],
                'declarationType' => $declarationType,
            ];
        }

        return $relocators;
    }

    // /**
    //  * Maps an element from the SimXML to an element in VrijBRP.
    //  *
    //  * @param array $eigenschap The element to map
    //  * @param array $relocator  The main relocator (as pointer)
    //  *
    //  * @throws \Exception
    //  *
    //  * @return array The resulting relocationArray
    //  */
    // public function mapEigenschap(array $eigenschap, array &$relocator): array
    // {
    //     switch ($eigenschap['naam']) {
    //         case 'DATUM_VERZENDING':
    //             $dateTimeObject = new \DateTime($eigenschap['waarde']);
    //             $dateTimeFormatted = $dateTimeObject->format('Y-m-d');
    //             $relocationArray['dossier']['startDate'] = $dateTimeFormatted;
    //             continue 2;
    //         case 'VERHUISDATUM':
    //             $dateTimeObject = new \DateTime($eigenschap['waarde']);
    //             $dateTimeFormatted = $dateTimeObject->format('Y-m-d\TH:i:s');
    //             $relocationArray['dossier']['entryDateTime'] = $dateTimeFormatted;
    //             $relocationArray['dossier']['status']['entryDateTime'] = $dateTimeFormatted;
    //             $relocationArray['dossier']['type']['code'] = $zaakArray['zaaktype']['omschrijving'];
    //             continue 2;
    //         case 'WOONPLAATS_NIEUW':
    //             $relocationArray['newAddress']['municipality']['description'] = $eigenschap['waarde'];
    //             $relocationArray['newAddress']['residence'] = $eigenschap['waarde'];
    //             continue 2;
    //         case 'TELEFOONNUMMER':
    //             $relocator['telephoneNumber'] = $eigenschap['waarde'];
    //             continue 2;
    //         case 'STRAATNAAM_NIEUW':
    //             $relocationArray['newAddress']['street'] = $eigenschap['waarde'];
    //             continue 2;
    //         case 'POSTCODE_NIEUW':
    //             $relocationArray['newAddress']['postalCode'] = $eigenschap['waarde'];
    //             continue 2;
    //         case 'HUISNUMMER_NIEUW':
    //             $relocationArray['newAddress']['houseNumber'] = intval($eigenschap['waarde']);
    //             continue 2;
    //         case 'HUISNUMMERTOEVOEGING_NIEUW':
    //             $relocationArray['newAddress']['houseNumberAddition'] = $eigenschap['waarde'];
    //             continue 2;
    //         case 'GEMEENTECODE':
    //             $relocationArray['newAddress']['municipality']['code'] = $eigenschap['waarde'];
    //             continue 2;
    //         case 'EMAILADRES':
    //             $relocator['email'] = $eigenschap['waarde'];
    //             continue 2;
    //         case 'BSN':
    //             $relocationArray['declarant']['bsn'] = $eigenschap['waarde'];
    //             $relocationArray['newAddress']['mainOccupant']['bsn'] = $eigenschap['waarde'];
    //             $relocationArray['newAddress']['liveIn'] = [
    //                 'liveInApplicable' => true,
    //                 'consent' => 'PENDING',
    //                 'consenter' => [
    //                     'bsn' => $eigenschap['waarde']
    //                 ]
    //             ];
    //             $relocationArray['newAddress']['addressFunction'] = 'LIVING_ADDRESS';
    //             continue 2;
    //         case 'AANTAL_PERS_NIEUW_ADRES':
    //             $relocationArray['newAddress']['numberOfResidents'] = intval($eigenschap['waarde']);
    //             continue 2;
    //     }

    //     return $relocationArray;
    // }

    /**
     * Creates a VrijRBP Relocation from a ZGW Zaak with the use of mapping.
     *
     * @param array $data          Data from the handler where the xxllnc casetype is in.
     * @param array $configuration Configuration from the Action where the ZaakType entity id is stored in.
     *
     * @return array $this->data Data which we entered the function with
     */
    public function mapRelocationHandler(array $data, array $configuration): array
    {
        $this->data = $data;
        $this->configuration = $configuration;

        if (!isset($data['id']) && !isset($data['response']['id'])) {
            throw new \Exception('Zaak ID not given for MapRelocationHandler');
        }

        $zaakObjectEntity = $this->entityManager->find('App:ObjectEntity', $data['id']);
        if (!$zaakObjectEntity instanceof ObjectEntity) {
            $zaakObjectEntity = $this->entityManager->find('App:ObjectEntity', $data['response']['id']);
            if (!$zaakObjectEntity instanceof ObjectEntity) {
                throw new \Exception('Zaak not found with given ID for MapRelocationHandler');
            }
        }

        $zaakArray = $zaakObjectEntity->toArray();

        if ($zaakArray['zaaktype']['identificatie'] !== 'B0366') {
            return $this->data;
        }

        $interRelocationEntity = $this->entityRepo->find($configuration['entities']['InterRelocation']);
        $intraRelocationEntity = $this->entityRepo->find($configuration['entities']['IntraRelocation']);

        if (!isset($interRelocationEntity)) {
            throw new \Exception('IntraRelocation entity could not be found');
        }
        if (!isset($intraRelocationEntity)) {
            throw new \Exception('InterRelocation entity could not be found');
        }

        $relocators = [];
        $relocator = [];

        $zaakArray['eigenschappen'] = [
            [
                "eigenschap" => "string",
                "naam" => "FORMULIERID",
                "waarde" => "000000100000"
            ],
            [
                "eigenschap" => "string",
                "naam" => "DATUMVERZENDING",
                "waarde" => "2021-12-01T23:41:18"
            ],
            [
                "eigenschap" => "string",
                "naam" => "ZAAKTYPE",
                "waarde" => "B0366"
            ],
            [
                "eigenschap" => "string",
                "naam" => "CODE_KANAAL",
                "waarde" => "web"
            ],
            [
                "eigenschap" => "string",
                "naam" => "INDIENER",
                "waarde" => "999995923"
            ],
            [
                "eigenschap" => "string",
                "naam" => "INDIENERSOORT",
                "waarde" => "burger"
            ],
            [
                "eigenschap" => "string",
                "naam" => "BETROUWBAARHEID_IDENTIFICATIE",
                "waarde" => "10"
            ],
            [
                "eigenschap" => "string",
                "naam" => "BETROUWBAARHEID_NAW",
                "waarde" => "1"
            ],
            [
                "eigenschap" => "string",
                "naam" => "BSN",
                "waarde" => "999995923"
            ],
            [
                "eigenschap" => "string",
                "naam" => "VOORLETTERS",
                "waarde" => "W.J."
            ],
            [
                "eigenschap" => "string",
                "naam" => "ACHTERNAAM",
                "waarde" => "Zech"
            ],
            [
                "eigenschap" => "string",
                "naam" => "GESLACHT",
                "waarde" => "m"
            ],
            [
                "eigenschap" => "string",
                "naam" => "STRAATNAAM",
                "waarde" => "Amazonestroom"
            ],
            [
                "eigenschap" => "string",
                "naam" => "HUISNUMMER",
                "waarde" => "96"
            ],
            [
                "eigenschap" => "string",
                "naam" => "POSTCODE",
                "waarde" => "2721EP"
            ],
            [
                "eigenschap" => "string",
                "naam" => "WOONPLAATS",
                "waarde" => "Zoetermeer"
            ],
            [
                "eigenschap" => "string",
                "naam" => "TELEFOONNUMMER",
                "waarde" => "0650606977"
            ],
            [
                "eigenschap" => "string",
                "naam" => "EMAILADRES",
                "waarde" => "wim@zech.nl"
            ],
            [
                "eigenschap" => "string",
                "naam" => "GEMEENTECODE",
                "waarde" => "0268"
            ],
            [
                "eigenschap" => "string",
                "naam" => "STRAATNAAM_NIEUW",
                "waarde" => "Binderskampweg"
            ],
            [
                "eigenschap" => "string",
                "naam" => "HUISNUMMER_NIEUW",
                "waarde" => "29"
            ],
            [
                "eigenschap" => "string",
                "naam" => "HUISLETTER_NIEUW",
                "waarde" => "U"
            ],
            [
                "eigenschap" => "string",
                "naam" => "HUISNUMMERTOEVOEGING_NIEUW",
                "waarde" => "39"
            ],
            [
                "eigenschap" => "string",
                "naam" => "POSTCODE_NIEUW",
                "waarde" => "6545CA"
            ],
            [
                "eigenschap" => "string",
                "naam" => "WOONPLAATS_NIEUW",
                "waarde" => "Nijmegen"
            ],
            [
                "eigenschap" => "string",
                "naam" => "VERHUISDATUM",
                "waarde" => "2022-01-09"
            ],
            [
                "eigenschap" => "string",
                "naam" => "AANTAL_PERS_NIEUW_ADRES",
                "waarde" => "3"
            ],
            [
                "eigenschap" => "string",
                "naam" => "WIJZE_BEWONING",
                "waarde" => "nieuwbouw"
            ],
            [
                "eigenschap" => "string",
                "naam" => "MEEVERHUIZENDE_GEZINSLEDEN",
                "waarde" => "[{\"BSN\":\"999995959\",\"ROL\":\"P\",\"VOORLETTERS\":\"S.C.A.\",\"VOORNAAM\":\"Simone Clarina Antoinette\",\"ACHTERNAAM\":\"Meelis\",\"GESLACHTSAANDUIDING\":\"v\",\"GEBOORTEDATUM\":\"19680822\"},{\"BSN\":\"999995996\",\"ROL\":\"K\",\"VOORLETTERS\":\"K.\",\"VOORNAAM\":\"Kim\",\"ACHTERNAAM\":\"Zech\",\"GESLACHTSAANDUIDING\":\"v\",\"GEBOORTEDATUM\":\"19980227\"}]"
            ]
        ];

        foreach ($zaakArray['eigenschappen'] as $eigenschap) {
            if ($eigenschap['naam'] == 'MEEVERHUIZENDE_GEZINSLEDEN') {
                $relocators = $this->mapRelocators($eigenschap);
            } else {
                switch ($eigenschap['naam']) {
                    case 'DATUM_VERZENDING':
                        $dateTimeObject = new \DateTime($eigenschap['waarde']);
                        $dateTimeFormatted = $dateTimeObject->format('Y-m-d');
                        $relocationArray['dossier']['startDate'] = $dateTimeFormatted;
                        continue 2;
                    case 'VERHUISDATUM':
                        $dateTimeObject = new \DateTime($eigenschap['waarde']);
                        $dateTimeFormatted = $dateTimeObject->format('Y-m-d\TH:i:s');
                        $relocationArray['dossier']['entryDateTime'] = $dateTimeFormatted;
                        $relocationArray['dossier']['status']['entryDateTime'] = $dateTimeFormatted;
                        $relocationArray['dossier']['type']['code'] = $zaakArray['zaaktype']['omschrijving'];
                        continue 2;
                    case 'WOONPLAATS_NIEUW':
                        $relocationArray['newAddress']['municipality']['description'] = $eigenschap['waarde'];
                        $relocationArray['newAddress']['residence'] = $eigenschap['waarde'];
                        continue 2;
                    case 'TELEFOONNUMMER':
                        $relocator['telephoneNumber'] = $eigenschap['waarde'];
                        continue 2;
                    case 'STRAATNAAM_NIEUW':
                        $relocationArray['newAddress']['street'] = $eigenschap['waarde'];
                        continue 2;
                    case 'POSTCODE_NIEUW':
                        $relocationArray['newAddress']['postalCode'] = $eigenschap['waarde'];
                        continue 2;
                    case 'HUISNUMMER_NIEUW':
                        $relocationArray['newAddress']['houseNumber'] = intval($eigenschap['waarde']);
                        continue 2;
                    case 'HUISNUMMERTOEVOEGING_NIEUW':
                        $relocationArray['newAddress']['houseNumberAddition'] = $eigenschap['waarde'];
                        continue 2;
                    case 'GEMEENTECODE':
                        $relocationArray['newAddress']['municipality']['code'] = $eigenschap['waarde'];
                        continue 2;
                    case 'EMAILADRES':
                        $relocator['email'] = $eigenschap['waarde'];
                        continue 2;
                    case 'BSN':
                        $relocationArray['declarant']['bsn'] = $eigenschap['waarde'];
                        $relocationArray['newAddress']['mainOccupant']['bsn'] = $eigenschap['waarde'];
                        $relocationArray['newAddress']['liveIn'] = [
                            'liveInApplicable' => true,
                            'consent' => 'PENDING',
                            'consenter' => [
                                'bsn' => $eigenschap['waarde']
                            ]
                        ];
                        $relocationArray['newAddress']['addressFunction'] = 'LIVING_ADDRESS';
                        continue 2;
                    case 'AANTAL_PERS_NIEUW_ADRES':
                        $relocationArray['newAddress']['numberOfResidents'] = intval($eigenschap['waarde']);
                        continue 2;
                }
            }
        }

        $relocationArray['newAddress']['liveIn']['consenter']['contactInformation'] = [
            'email'           => $relocator['email'] ?? null,
            'telephoneNumber' => $relocator['telephoneNumber'] ?? null,
        ];
        $relocationArray['newAddress']['mainOccupant']['contactInformation'] = [
            'email'           => $relocator['email'] ?? null,
            'telephoneNumber' => $relocator['telephoneNumber'] ?? null,
        ];
        $relocationArray['relocators'] = $relocators;
        $relocationArray['relocators'][] = array_merge($relocationArray['newAddress']['mainOccupant'], ['declarationType' => 'ADULT_AUTHORIZED_REPRESENTATIVE']);

        $relocationArray['dossier']['dossierId'] = $zaakArray['id'];
        !isset($relocationArray['dossier']['startDate']) && $relocationArray['dossier']['startDate'] = $relocationArray['dossier']['entryDateTime'];

        // Save in gateway

        $intraObjectEntity = new ObjectEntity();
        $intraObjectEntity->setEntity($intraRelocationEntity);

        $intraObjectEntity->hydrate($relocationArray);

        $this->entityManager->persist($intraObjectEntity);
        $this->entityManager->flush();

        $this->objectEntityService->dispatchEvent('commongateway.object.create', ['entity' => $intraRelocationEntity->getId()->toString(), 'response' => $relocationArray]);

        return $this->data;
    }
}
