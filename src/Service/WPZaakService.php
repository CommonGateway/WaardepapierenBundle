<?php

namespace CommonGateway\WaardepapierenBundle\Service;

use App\Entity\ObjectEntity;
use CommonGateway\CoreBundle\Service\DownloadService;
use CommonGateway\CoreBundle\Service\GatewayResourceService;
use CommonGateway\WaardepapierenBundle\Service\WaardepapierService;
use CommonGateway\ZGWBundle\Service\DRCService;
use Doctrine\ORM\EntityManagerInterface;
use Safe\DateTime;

/**
 * WPZaakService makes a certificate with for a zaak
 *
 * @author   Barry Brands barry@conduction.nl
 * @package  common-gateway/waardepapieren-bundle
 * @category Service
 * @access   public
 */
class WPZaakService
{

    /**
     * @var EntityManagerInterface
     */
    private EntityManagerInterface $entityManager;

    /**
     * @var WaardepapierService
     */
    private WaardepapierService $waardepapierService;

    /**
     * @var GatewayResourceService The gateway resource service.
     */
    private GatewayResourceService $resourceService;

    /**
     * @var DRCService The DRC service from the ZGW bundle.
     */
    private DRCService $DRCService;

    /**
     * @var array $configuration of the current action.
     */
    private array $configuration;

    /**
     * @var array $certificate that is being created in this service.
     */
    private ?array $certificate;

    /**
     * @var array $userData that is being used to create a certificate.
     */
    private ?array $userData;


    /**
     * __construct
     */
    public function __construct(
        EntityManagerInterface $entityManager,
        WaardepapierService $waardepapierService,
        DownloadService $downloadService,
        GatewayResourceService $resourceService,
        DRCService $DRCService
    ) {
        $this->entityManager       = $entityManager;
        $this->waardepapierService = $waardepapierService;
        $this->downloadService     = $downloadService;
        $this->resourceService     = $resourceService;
        $this->DRCService          = $DRCService;

    }//end __construct()


    /**
     * Gets a Dutch BSN from a ZGW Zaak
     *
     * @param array $zaak ZGW Zaak
     *
     * @return string|null Dutch BSN
     */
    private function getBSN(array $zaak): ?string
    {
        if (isset($zaak['embedded']['rollen']) === false) {
            // $this->logger->error('No BSN found for Zaak, failed to create certificate')
            return null;
        }

        foreach ($zaak['embedded']['rollen'] as $rol) {
            if (isset($rol['betrokkeneIdentificatie']['inpBsn']) === true) {
                return $rol['betrokkeneIdentificatie']['inpBsn'];
            }
        }

        return null;

    }//end getBSN()


    /**
     * Gets a Dutch RSIN from a ZGW Zaak
     *
     * @param array $zaak ZGW Zaak
     *
     * @return string|null Dutch RSIN
     */
    private function getRSIN(array $zaak): ?string
    {
        if (isset($zaak['verantwoordelijkeOrganisatie']) === false) {
            // $this->logger->error('No verantwoordelijkeOrganisatie found for Zaak, failed to create certificate')
            return null;
        }

        return $zaak['verantwoordelijkeOrganisatie'];

    }//end getRSIN()

    /**
     * Finds a informatieobjectttype id from a object url in the action configuration.
     * 
     * @return string|null
     */
    private function getInformatieObjectTypeId()
    {
        $informatieobjecttype = $this->configuration['informatieobjecttype'] ?? '';
        if (empty($informatieobjecttype) === false) {
            $uuidPattern = '/[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}/i';
            if (preg_match($uuidPattern, $informatieobjecttype, $matches)) {
                $id = $matches[0];
                $informatieobjecttype = $this->entityManager->find('App:ObjectEntity', $id);
                if ($informatieobjecttype !== null) {
                    return $informatieobjecttype->getId()->toString();
                } 
                
                // If no object is found try a synchronization and its object.
                $synchronization = $this->entityManager->getRepository('App:Synchronizations')->findOneBy(['sourceId' => $id]);
                if ($synchronization !== null) {
                    return $synchronization->getObject()->getId()->toString();
                }
            }
        }

        return null;
    }//end getInformatieObjectType()


    public function saveWaardepapierInDRC(string $data, ObjectEntity $zaakObject): void
    {
        $now = new DateTime();

        $informationArray = [
            'inhoud'                       => base64_encode($data),
            'informatieobjecttype'         => $this->getInformatieObjectTypeId(),
            'bronorganisatie'              => '999990639',
            'creatiedatum'                 => $now->format('Y-m-d'),
            'titel'                        => 'Waardepapier',
            'vertrouwelijkheidsaanduiding' => 'vertrouwelijk',
            'auteur'                       => 'Common Gateway',
            'taal'                         => 'NLD',
            'bestandsnaam'                 => 'waardepapier.pdf',
            'versie'                       => null,
        ];

        $informationObjectEntity = $this->resourceService->getSchema('https://vng.opencatalogi.nl/schemas/drc.enkelvoudigInformatieObject.schema.json', 'common-gateway/waardepapieren-bundle');

        $informationObject = new ObjectEntity($informationObjectEntity);

        $informationObject->hydrate($informationArray);
        $this->entityManager->persist($informationObject);
        $this->entityManager->flush();

        $this->DRCService->createOrUpdateFile($informationObject, $informationArray, $this->entityManager->getRepository('App:Endpoint')->findOneBy(['reference' => 'https://vng.opencatalogi.nl/endpoints/drc.downloadEnkelvoudigInformatieObject.endpoint.json']));

        $caseInformationObjectEntity = $this->resourceService->getSchema('https://vng.opencatalogi.nl/schemas/zrc.zaakInformatieObject.schema.json', 'common-gateway/waardepapieren-bundle');

        $caseInformationArray  = [
            'zaak'                => $zaakObject,
            'informatieobject'    => $informationObject,
            'aardRelatieWeergave' => 'Hoort bij, omgekeerd: kent',
        ];
        $caseInformationObject = new ObjectEntity($caseInformationObjectEntity);

        $caseInformationObject->hydrate($caseInformationArray);
        $this->entityManager->persist($caseInformationObject);
        $this->entityManager->flush();

    }//end saveWaardepapierInDRC()


    /**
     * Creates a certificate for a ZGW Zaak.
     *
     * @param array $data          Data from the handler where the xxllnc casetype is in.
     * @param array $configuration Configuration for the Action.
     *
     * @return array $this->certificate Certificate which we updated with new data
     */
    public function wpZaakHandler(array $data, array $configuration): array
    {
        $this->configuration = $configuration;
        $this->data          = $data;

        $data['method'] = 'PUT';

        $this->DRCService->setDataAndConfiguration($data, $configuration);

        if ($this->data['response']['_self']['schema']['ref'] === "https://vng.opencatalogi.nl/schemas/zrc.zaak.schema.json") {
            $zaak = $this->data['response'];
        } else if ($this->data['response']['_self']['schema']['ref'] === "https://vng.opencatalogi.nl/schemas/zrc.rol.schema.json"
            && isset($this->data['response']['embedded']['zaak'])
        ) {
            $zaak = $this->data['response']['embedded']['zaak'];
        } else {
            return $this->data;
        }

        $zaakObject = $this->entityManager->getRepository("App:ObjectEntity")->find($zaak['_self']['id']);
        if ($zaakObject instanceof ObjectEntity === false) {
            return $this->data;
        }

        $zaak = $zaakObject->toArray(['embedded' => true]);

        // 1. Get BSN from Zaak.
        $bsn = $this->getBSN($zaak);
        if ($bsn === null) {
            return $this->data;
        }

        // 2. Get RSIN organisatie from Zaak
        $certificate['organization'] = $this->getRSIN($zaak);
        if ($certificate['organization'] === null) {
            return $this->data;
        }

        // 3. Get persons information from pink haalcentraalGateway
        // $brpPersoon = $this->waardepapierService->fetchPersoonsgegevens($bsn);
        // 5. Fill certificate with persons information and/or zaak
        $certificate = $this->downloadService->downloadPdf($zaak);

        // 6. Store certificate in DRC.
        $this->saveWaardepapierInDRC($certificate, $zaakObject);

        return $this->data;

    }//end wpZaakHandler()


}//end class
