<?php

namespace CommonGateway\WaardepapierenBundle\Service;

use App\Entity\ObjectEntity;
use App\Entity\Synchronization;
use Brick\Reflection\Tests\S;
use CommonGateway\CoreBundle\Service\CallService;
use CommonGateway\CoreBundle\Service\DownloadService;
use CommonGateway\CoreBundle\Service\GatewayResourceService;
use CommonGateway\CoreBundle\Service\MappingService;
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

    private CallService $callService;

    private MappingService $mappingService;


    /**
     * __construct
     */
    public function __construct(
        EntityManagerInterface $entityManager,
        WaardepapierService $waardepapierService,
        DownloadService $downloadService,
        GatewayResourceService $resourceService,
        DRCService $DRCService,
        CallService $callService,
        MappingService $mappingService
    ) {
        $this->entityManager       = $entityManager;
        $this->waardepapierService = $waardepapierService;
        $this->downloadService     = $downloadService;
        $this->resourceService     = $resourceService;
        $this->DRCService          = $DRCService;
        $this->callService         = $callService;
        $this->mappingService      = $mappingService;

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
     * New setup for synchronizing upstream.
     * To be added in an adapted form into the core synchronizationService.
     *
     * @param Synchronization $synchronization The synchronization to synchronize
     *
     * @return bool Whether or not the synchronization has passed.
     *
     * @throws \Twig\Error\LoaderError
     * @throws \Twig\Error\SyntaxError
     */
    public function synchronizeUpstream(Synchronization $synchronization): bool
    {
        if ($synchronization->getLastSynced() === null) {
            $method = 'POST';
        } else {
            $method = 'PUT';
        }

        $data = $this->mappingService->mapping($synchronization->getMapping(), $synchronization->getObject()->toArray());

        $response     = $this->callService->call($synchronization->getSource(), $synchronization->getEndpoint(), $method, ['body' => $data]);
        $updateObject = $this->callService->decodeResponse($synchronization->getSource(), $response);

        $synchronization->getObject()->hydrate($updateObject);
        $synchronization->setLastSynced(new DateTime());
        $synchronization->setSourceId($updateObject['url']);
        // hardcoded for openzaak, to determine dynamically
        $this->entityManager->persist($synchronization);
        $this->entityManager->flush();

        return true;

    }//end synchronizeUpstream()


    /**
     * Store information objects to an upstream source
     *
     * @param ObjectEntity $informatieobject     The information object to store
     * @param ObjectEntity $zaakinformatieobject The case-information-object to store
     * @param ObjectEntity $zaak                 The case the objects belong to
     *
     * @return bool Whether or not the synchronization has passed.
     */
    public function storeWaardepapierInSourceDRC(ObjectEntity $informatieobject, ObjectEntity $zaakinformatieobject, ObjectEntity $zaak): bool
    {
        if (count($zaak->getSynchronizations()) === 0) {
            return true;
        }

        $zaakSync = $zaak->getSynchronizations()[0];

        $source = $zaakSync->getSource();

        $eioSync = new Synchronization();
        $eioSync->setSource($this->configuration['drcSource']);
        $eioSync->setMapping($this->resourceService->getMapping('https://waardepapieren.commongateway.nl/mapping/drc.enkelvoudigInformatieObjectUpstream.mapping.json'));
        $eioSync->setEndpoint('/enkelvoudiginformatieobjecten');
        $eioSync->setObject($informatieobject);

        $result = $this->synchronizeUpstream($eioSync);

        if ($result === false) {
            return false;
        }

        $zioSync = new Synchronization();
        $zioSync->setSource($source);
        $zioSync->setMapping('https://waardepapieren.commongateway.nl/mapping/zrc.zaakInformatieObjectUpstream.mapping.json');
        $zioSync->setEndpoint('/zaakinformatieobjecten');
        $eioSync->setObject($informatieobject);

        $result = $this->synchronizeUpstream($zioSync);

        return $result;

    }//end storeWaardepapierInSourceDRC()


    public function saveWaardepapierInDRC(string $data, ObjectEntity $zaakObject): void
    {
        $now = new DateTime();

        $informationArray = [
            'inhoud'                       => base64_encode($data),
            'informatieobjecttype'         => $this->resourceService->getObject($this->configuration['informatieobjecttype']),
            'bronorganisatie'              => $zaakObject->getValue('bronorganisatie'),
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

        $this->storeWaardepapierInSourceDRC($informationObject, $caseInformationObject, $zaakObject);

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
        $brpPersoon = [];
        // $this->waardepapierService->fetchPersoonsgegevens($bsn);
        // 5. Fill certificate with persons information and/or zaak
        $certificate = $this->downloadService->downloadPdf($zaak);

        $this->saveWaardepapierInDRC($certificate, $zaakObject);

        // $certificate = $this->waardepapierService->createCertificate($certificate, 'zaak', $brpPersoon, $zaak);
        return $this->data;

    }//end wpZaakHandler()


}//end class
