<?php

namespace CommonGateway\WaardepapierenBundle\Service;

use App\Entity\Gateway as Source;
use App\Entity\Entity as Schema;
use App\Entity\ObjectEntity;
use App\Entity\Synchronization;
use App\Service\SynchronizationService;
use App\Service\ApplicationService;
use CommonGateway\CoreBundle\Service\CallService;
use CommonGateway\CoreBundle\Service\DownloadService;
use CommonGateway\CoreBundle\Service\GatewayResourceService;
use CommonGateway\CoreBundle\Service\MappingService;
use CommonGateway\WaardepapierenBundle\Service\WaardepapierService;
use Psr\Log\LoggerInterface;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Ramsey\Uuid\Uuid;
use Safe\DateTime;
use Symfony\Component\HttpFoundation\Response;
use Twig\Error\LoaderError;
use Twig\Error\SyntaxError;

/**
 * ZaakNotificationService makes a certificate with for a zaak
 *
 * @author   Barry Brands barry@conduction.nl
 * @package  common-gateway/waardepapieren-bundle
 * @category Service
 * @access   public
 */
class ZaakNotificationService
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
     * @var DownloadService The Download service from the Core bundle.
     */
    private DownloadService $downloadService;

    /**
     * @var GatewayResourceService The gateway resource service.
     */
    private GatewayResourceService $resourceService;

    /**
     * @var SynchronizationService The Synchronization service from the Core bundle.
     */
    private SynchronizationService $syncService;

    /**
     * @var array $configuration of the current action.
     */
    private array $configuration;

    /**
     * @var CallService The Call service from the Core bundle.
     */
    private CallService $callService;

    /**
     * @var MappingService The Mapping service from the Core bundle.
     */
    private MappingService $mappingService;

    /**
     * @var ApplicationService The Mapping service from the Core bundle.
     */
    private ApplicationService $applicationService;

    /**
     * @var LoggerInterface LoggerInterface.
     */
    private LoggerInterface $logger;


    /**
     * __construct
     */
    public function __construct(
        EntityManagerInterface $entityManager,
        WaardepapierService $waardepapierService,
        DownloadService $downloadService,
        GatewayResourceService $resourceService,
        CallService $callService,
        MappingService $mappingService,
        SynchronizationService $syncService,
        ApplicationService $applicationService,
        LoggerInterface $logger
    ) {
        $this->entityManager       = $entityManager;
        $this->waardepapierService = $waardepapierService;
        $this->downloadService     = $downloadService;
        $this->resourceService     = $resourceService;
        $this->callService         = $callService;
        $this->mappingService      = $mappingService;
        $this->syncService         = $syncService;
        $this->applicationService  = $applicationService;
        $this->logger = $logger;

    }//end __construct()


    /**
     * New setup for synchronizing upstream.
     * To be added in an adapted form into the core synchronizationService.
     *
     * @param Synchronization $synchronization The synchronization to synchronize
     *
     * @return bool Whether the synchronization has passed.
     *
     * @throws Exception|LoaderError|SyntaxError
     */
    public function synchronizeUpstream(Synchronization $synchronization): bool
    {
        $data = $this->mappingService->mapping($synchronization->getMapping(), $synchronization->getObject()->toArray());

        try {
            $response = $this->callService->call($synchronization->getSource(), $synchronization->getEndpoint(), 'POST', ['json' => $data]);
        } catch (Exception $exception) {
            $this->logger->error($exception->getMessage());
            throw new Exception($exception->getMessage());
        }

        $updateObject = $this->callService->decodeResponse($synchronization->getSource(), $response);

        $synchronization->getObject()->hydrate($updateObject);
        $this->entityManager->persist($synchronization->getObject());
        // $this->entityManager->flush();
        if (key_exists('uuid', $updateObject) === true) {
            $sourceId = $updateObject['uuid'];
        }

        if (key_exists('uuid', $updateObject) === false
            && key_exists('url', $updateObject) === true
        ) {
            $sourceId = $updateObject['url'];
        }

        $synchronization->setLastSynced(new DateTime());
        $synchronization->setSourceId($sourceId);
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
     * @return bool Whether the synchronization has passed.
     * @throws Exception
     */
    public function storeWaardepapierInSourceDRC(ObjectEntity $informatieobject, ObjectEntity $zaakinformatieobject, ObjectEntity $gebruiksrecht, ObjectEntity $zaak): bool
    {
        if (count($zaak->getSynchronizations()) === 0) {
            $this->logger->warning('Can\'t store waardepapier in drc, zaak has no synchronziaitons in source.');
            return true;
        }

        $drcSource = $this->resourceService->getSource($this->configuration['drcSource'], 'common-gateway/waardepapieren-bundle');

        $zaakSync = $zaak->getSynchronizations()->first();

        $source = $zaakSync->getSource();

        $eioSync = new Synchronization();
        $eioSync->setSource($drcSource);
        $eioSync->setMapping($this->resourceService->getMapping($this->configuration['enkelvoudigInfoMapping'], 'common-gateway/waardepapieren-bundle'));
        $eioSync->setEndpoint('/enkelvoudiginformatieobjecten');
        $eioSync->setObject($informatieobject);

        $this->entityManager->persist($eioSync);
        // $this->entityManager->flush();
        $result = $this->synchronizeUpstream($eioSync);

        if ($result === false) {
            $this->logger->error('Could not synchronize waardepapier to DRC source', ['plugin' => 'common-gateway/waardepapieren-bundle']);
            return false;
        }

        $gebruiksrechtSync = new Synchronization();
        $gebruiksrechtSync->setSource($drcSource);
        $gebruiksrechtSync->setMapping($this->resourceService->getMapping('https://waardepapieren.commongateway.nl/mapping/drc.gebruiksrechtUpstream.mapping.json', 'common-gateway/waardepapieren-bundle'));
        $gebruiksrechtSync->setEndpoint('/gebruiksrechten');
        $gebruiksrechtSync->setObject($gebruiksrecht);

        $this->entityManager->persist($gebruiksrechtSync);
        // $this->entityManager->flush();
        $result = $this->synchronizeUpstream($gebruiksrechtSync);

        if ($result === false) {
            $this->logger->error('Could not synchronize gebruiksrecht to DRC source', ['plugin' => 'common-gateway/waardepapieren-bundle']);
            return false;
        }

        $zioSync = new Synchronization();
        $zioSync->setSource($source);
        $zioSync->setMapping($this->resourceService->getMapping($this->configuration['zaakInfoMapping'], 'common-gateway/waardepapieren-bundle'));
        $zioSync->setEndpoint('/zaakinformatieobjecten');
        $zioSync->setObject($zaakinformatieobject);

        $this->entityManager->persist($zioSync);
        // $this->entityManager->flush();
        $result = $this->synchronizeUpstream($zioSync);

        return $result;

    }//end storeWaardepapierInSourceDRC()


    /**
     * Store information objects to an upstream source
     *
     * @param string       $data
     * @param ObjectEntity $zaakObject              The case as object.
     * @param string       $informatieobjecttypeUrl The url of the information object type that is related to the case type.
     *
     * @return void Whether the synchronization has passed.
     * @throws Exception
     */
    public function saveWaardepapierInDRC(string $data, ObjectEntity $zaakObject, string $informatieobjecttypeUrl): void
    {
        $now = new DateTime();

        $informationArray = [
            'inhoud'                       => base64_encode($data),
            'informatieobjecttype'         => $informatieobjecttypeUrl,
            'bronorganisatie'              => $zaakObject->getValue('bronorganisatie'),
            'creatiedatum'                 => $now->format('Y-m-d'),
            'titel'                        => 'Waardepapier',
            'vertrouwelijkheidsaanduiding' => 'vertrouwelijk',
            'auteur'                       => 'Common Gateway',
            'taal'                         => 'NLD',
            'bestandsnaam'                 => 'waardepapier.pdf',
            'versie'                       => null,
        ];

        $informationObjectSchema = $this->resourceService->getSchema('https://vng.opencatalogi.nl/schemas/drc.enkelvoudigInformatieObject.schema.json', 'common-gateway/waardepapieren-bundle');
        if ($informationObjectSchema === null) {
            return;
        }

        $informationObject = new ObjectEntity($informationObjectSchema);

        $informationObject->hydrate($informationArray);
        $this->entityManager->persist($informationObject);
        // $this->entityManager->flush();
        $gebruiksrechtSchema = $this->resourceService->getSchema('https://vng.opencatalogi.nl/schemas/drc.gebruiksrecht.schema.json', 'common-gateway/waardepapieren-bundle');
        if ($gebruiksrechtSchema === null) {
            $this->logger->error('gebruiksrechtSchema is null', ['plugin' => 'common-gateway/waardepapieren-bundle']);
            return;
        }

        $gebruiksrechtArray = [
            'informatieobject'        => $informationObject,
            'startdatum'              => $now->format('c'),
            'omschrijvingVoorwaarden' => 'Voorwaarden',
        ];

        $gebruiksrecht = new ObjectEntity($gebruiksrechtSchema);

        $gebruiksrecht->hydrate($gebruiksrechtArray);
        $this->entityManager->persist($gebruiksrecht);
        // $this->entityManager->flush();
        $caseInformationObjectSchema = $this->resourceService->getSchema('https://vng.opencatalogi.nl/schemas/zrc.zaakInformatieObject.schema.json', 'common-gateway/waardepapieren-bundle');
        if ($caseInformationObjectSchema === null) {
            $this->logger->error('caseInformationObjectSchema is null', ['plugin' => 'common-gateway/waardepapieren-bundle']);
            return;
        }

        $caseInformationArray  = [
            'zaak'             => $zaakObject,
            'informatieobject' => $informationObject,
        ];
        $caseInformationObject = new ObjectEntity($caseInformationObjectSchema);

        $caseInformationObject->hydrate($caseInformationArray);
        $this->entityManager->persist($caseInformationObject);
        // $this->entityManager->flush();
        $this->storeWaardepapierInSourceDRC($informationObject, $caseInformationObject, $gebruiksrecht, $zaakObject);

    }//end saveWaardepapierInDRC()


    /**
     * Gets the zaaktype from the given zaak
     *
     * @param string $objectUrl The url of the zaaktype.
     * @param string $schemaRef The reference of the schema
     * @param string $endpoint  The endpoint
     *
     * @return ObjectEntity|null The zaaktype of the given source
     * @throws Exception
     */
    public function getZaaktypeSubObjects(string $objectUrl, string $schemaRef, string $endpoint): ?ObjectEntity
    {
        // Get zaaktype schema.
        $schema = $this->resourceService->getSchema($schemaRef, 'common-gateway/waardepapieren-bundle');
        $source = $this->resourceService->getSource($this->configuration['ztcSource'], 'common-gateway/waardepapieren-bundle');

        // Get the uuid from the zaaktype url.
        $explodedUrl = explode('/', $objectUrl);
        foreach ($explodedUrl as $item) {
            if (Uuid::isValid($item)) {
                $objectId = $item;
            }
        }

        if (isset($objectId) === false) {
            $this->logger->error("No object id found ZaakType subobject type $schemaRef its url", ['plugin' => 'common-gateway/waardepapieren-bundle']);
            return null;
        }

        // Check if we have a zaaktype with findSyncByObject.
        $objectSync = $this->syncService->findSyncBySource($source, $schema, $objectId);

        // if no object is present, a call must be made to retrieve the zaaktype.
        if ($objectSync->getObject() === null) {
            try {
                $response = $this->callService->call($source, $endpoint.'/'.$objectId);
            } catch (Exception $exception) {
                $this->logger->error("Failed to fetch zaaktype subobject: {$exception->getMessage()}", ['plugin' => 'common-gateway/waardepapieren-bundle']);
                throw new Exception($exception->getMessage());
            }

            $response   = $this->callService->decodeResponse($source, $response);
            $objectSync = $this->syncService->synchronize($objectSync, $response);
            $this->entityManager->flush();

            return $objectSync->getObject();
        }

        if ($objectSync->getObject() !== null) {
            return $objectSync->getObject();
        }

        return null;

    }//end getZaaktypeSubObjects()


    /**
     * Gets the zaaktype from the given zaak
     *
     * @param array $response The response of the call
     *
     * @return array The zaaktype of the given source
     * @throws Exception
     */
    public function getSubobjects(array $response): array
    {
        foreach ($response['informatieobjecttypen'] as $infoObjectType) {
            $info = $this->getZaaktypeSubObjects($infoObjectType, 'https://vng.opencatalogi.nl/schemas/ztc.informatieObjectType.schema.json', '/informatieobjecttypen');
            $response['informatieobjecttypen'][] = $info;
        }

        foreach ($response['eigenschappen'] as $eigenschap) {
            $eigenschapObject            = $this->getZaaktypeSubObjects($eigenschap, 'https://vng.opencatalogi.nl/schemas/ztc.eigenschap.schema.json', '/eigenschappen');
            $response['eigenschappen'][] = $eigenschapObject;
        }

        foreach ($response['roltypen'] as $roltype) {
            $roltypeObject          = $this->getZaaktypeSubObjects($roltype, 'https://vng.opencatalogi.nl/schemas/ztc.rolType.schema.json', '/roltypen');
            $response['roltypen'][] = $roltypeObject;
        }

        foreach ($response['statustypen'] as $statustype) {
            $statustypeObject          = $this->getZaaktypeSubObjects($statustype, 'https://vng.opencatalogi.nl/schemas/ztc.statusType.schema.json', '/statustypen');
            $response['statustypen'][] = $statustypeObject;
        }

        foreach ($response['resultaattypen'] as $resultaattype) {
            $resultaattypeObject          = $this->getZaaktypeSubObjects($resultaattype, 'https://vng.opencatalogi.nl/schemas/ztc.resultaatType.schema.json', '/resultaattypen');
            $response['resultaattypen'][] = $resultaattypeObject;
        }

        return $response;

    }//end getSubobjects()


    /**
     * Gets the zaaktype from the given zaak
     *
     * @param string $zaaktypeUrl      The url of the zaaktype.
     * @param string $zaakTypeSourceId The sourceId of the zaaktype.
     *
     * @return ObjectEntity|null The zaaktype of the given source
     * @throws Exception
     */
    public function getZaaktypeFromSource(string $zaaktypeUrl, ?string &$zaakTypeSourceId=null): ?ObjectEntity
    {
        // Get zaaktype schema.
        $schema = $this->resourceService->getSchema('https://vng.opencatalogi.nl/schemas/ztc.zaakType.schema.json', 'common-gateway/waardepapieren-bundle');
        $source = $this->resourceService->getSource($this->configuration['ztcSource'], 'common-gateway/waardepapieren-bundle');

        // Get the uuid from the zaaktype url.
        $explodedUrl = explode('/', $zaaktypeUrl);
        $zaaktypeId  = null;
        foreach ($explodedUrl as $item) {
            if (Uuid::isValid($item)) {
                $zaaktypeId       = $item;
                $zaakTypeSourceId = $item;
            }
        }

        if ($zaaktypeId === null) {
            $this->logger->error("Could not get a ID on the ZaakType url $zaaktypeUrl", ['plugin' => 'common-gateway/waardepapieren-bundle']);
            return null;
        }

        // Check if we have a zaaktype with findSyncByObject.
        $zaaktypeSync = $this->syncService->findSyncBySource($source, $schema, $zaaktypeId);

        // if no object is present, a call must be made to retrieve the zaaktype.
        try {
            $response = $this->callService->call($source, '/zaaktypen/'.$zaaktypeId);
        } catch (Exception $exception) {
            $this->logger->error("Failed to fetch ZaakType from source: {$exception->getMessage()}", ['plugin' => 'common-gateway/waardepapieren-bundle']);
            throw new Exception($exception->getMessage());
        }

        $response     = $this->callService->decodeResponse($source, $response);
        $response     = $this->getSubobjects($response);
        $zaaktypeSync = $this->syncService->synchronize($zaaktypeSync, $response);
        $this->entityManager->flush();

        return $zaaktypeSync->getObject();

    }//end getZaaktypeFromSource()


    /**
     * Store information objects to an upstream source
     *
     * @param ObjectEntity $resultaat The resultaat to store
     * @param ObjectEntity $status    The status object to store
     * @param ObjectEntity $zaak      The case the objects belong to
     *
     * @return bool Whether the synchronization has passed.
     */
    public function storeInSourceZRC(ObjectEntity $resultaat, ObjectEntity $status, ObjectEntity $zaak): bool
    {
        if (count($zaak->getSynchronizations()) === 0) {
            return true;
        }

        $zaakSync = $zaak->getSynchronizations()->first();
        $source   = $zaakSync->getSource();

        $resultaatSync = new Synchronization();
        $resultaatSync->setSource($source);
        $resultaatSync->setMapping($this->resourceService->getMapping($this->configuration['resultaatMapping'], 'common-gateway/waardepapieren-bundle'));
        $resultaatSync->setEndpoint('/resultaten');
        $resultaatSync->setObject($resultaat);

        $this->entityManager->persist($resultaatSync);
        // $this->entityManager->flush();
        $result = $this->synchronizeUpstream($resultaatSync);

        if ($result === false) {
            $this->logger->error("ResultaatType is null", ['plugin' => 'common-gateway/waardepapieren-bundle']);
            return false;
        }

        $statusSync = new Synchronization();
        $statusSync->setSource($source);
        $statusSync->setMapping($this->resourceService->getMapping($this->configuration['statusMapping'], 'common-gateway/waardepapieren-bundle'));
        $statusSync->setEndpoint('/statussen');
        $statusSync->setObject($status);

        $this->entityManager->persist($statusSync);
        $this->entityManager->flush();

        return $this->synchronizeUpstream($statusSync);

    }//end storeInSourceZRC()


    /**
     * Store information objects to an upstream source
     *
     * @param ObjectEntity $zaakObject
     * @param ObjectEntity $zaaktype
     *
     * @return void Whether the synchronization has passed.
     */
    public function saveInZRC(ObjectEntity $zaakObject, ObjectEntity $zaaktype): void
    {
        $zaaktypeArray  = $zaaktype->toArray();
        $resultaattypen = $zaaktypeArray['resultaattypen'];

        $resultaattype = null;
        foreach ($resultaattypen as $item) {
            if ($item['omschrijving'] === 'Geleverd') {
                $resultaattype = $item;
            }
        }

        if ($resultaattype === null) {
            $this->logger->error("ResultaatType is null", ['plugin' => 'common-gateway/waardepapieren-bundle']);
            return;
        }

        $resultaatArray = [
            'zaak'          => $zaakObject,
            'resultaattype' => $resultaattype['url'],
        ];

        $resultaatSchema = $this->resourceService->getSchema('https://vng.opencatalogi.nl/schemas/zrc.resultaat.schema.json', 'common-gateway/waardepapieren-bundle');
        if ($resultaatSchema === null) {
            $this->logger->error("resultaatSchema is null", ['plugin' => 'common-gateway/waardepapieren-bundle']);
            return;
        }

        $resultaatObject = new ObjectEntity($resultaatSchema);

        $resultaatObject->hydrate($resultaatArray);
        $this->entityManager->persist($resultaatObject);
        // $this->entityManager->flush();
        $statusSchema = $this->resourceService->getSchema("https://vng.opencatalogi.nl/schemas/zrc.status.schema.json", 'common-gateway/waardepapieren-bundle');
        if ($statusSchema === null) {
            $this->logger->error("statusSchema is null", ['plugin' => 'common-gateway/waardepapieren-bundle']);
            return;
        }

        $statustypen = $zaaktypeArray['statustypen'];

        $statustype = null;
        foreach ($statustypen as $item) {
            if ($item['statustekst'] === 'Geleverd') {
                $statustype = $item;
            }
        }

        if ($statustype === null) {
            $this->logger->error("statustype is null", ['plugin' => 'common-gateway/waardepapieren-bundle']);
            return;
        }

        $datum        = new DateTime('now');
        $statusArray  = [
            'zaak'             => $zaakObject,
            'statustype'       => $statustype['url'],
            'datumStatusGezet' => $datum->format('c'),
        ];
        $statusObject = new ObjectEntity($statusSchema);

        $statusObject->hydrate($statusArray);
        $this->entityManager->persist($statusObject);
        // $this->entityManager->flush();
        $this->storeInSourceZRC($resultaatObject, $statusObject, $zaakObject);

    }//end saveInZRC()


    /**
     * Gets the zaaktype from the given zaak
     *
     * @param ObjectEntity $zaak The zaak object.
     *
     * @return ObjectEntity|null The zaaktype of the given source
     * @throws Exception
     */
    public function getZaakFromSource(ObjectEntity $zaak): ?ObjectEntity
    {
        $source   = $this->resourceService->getSource($this->configuration['zrcSource'], 'common-gateway/waardepapieren-bundle');
        $zaakSync = $zaak->getSynchronizations()->first();

        try {
            $response = $this->callService->call($source, '/zaken/'.$zaakSync->getSourceId());
        } catch (Exception $exception) {
            $this->logger->error("Failed to fetch zaak from source. Error: {$exception->getMessage()}", ['plugin' => 'common-gateway/waardepapieren-bundle']);
            throw new Exception($exception->getMessage());
        }

        if (isset($response) === true) {
            $response = $this->callService->decodeResponse($source, $response);

            $zaak->hydrate($response);
            $this->entityManager->persist($zaak);
            $this->entityManager->flush();

            return $zaak;
        }

        return null;

    }//end getZaakFromSource()


    /**
     * Gets Zaak from its source with id.
     *
     * @param Source            $source     ZRC source.
     * @param Schema            $schema     Zaak Schema object.
     * @param ObjectEntity|null $zaakObject Can be null and gets passed back to parent function.
     *
     * @return array BSN if found from zaak.
     */
    private function getZaak(Source $source, Schema $schema, ?ObjectEntity &$zaakObject=null): array
    {
        // Get the zaak url from the body of the request.
        $zaakUrl = $this->data['body']['resourceUrl'];
        // Get the uuid from the zaaktype url.
        $explodedUrl = explode('/', $zaakUrl);
        $zaakId      = null;
        foreach ($explodedUrl as $item) {
            if (Uuid::isValid($item)) {
                $zaakId = $item;
            }
        }

        if ($zaakId === null) {
            $this->logger->error("Could not get a ID from the zaak url: $zaakUrl", ['plugin' => 'common-gateway/waardepapieren-bundle']);
            return $this->data;
        }

        // Find the zaak object through the source and sourceId.
        $zaakSync = $this->syncService->findSyncBySource($source, $schema, $zaakId);
        if (($zaakObject = $zaakSync->getObject()) === null) {
            $this->logger->error("Failed to fetch zaak from source. Error: {$exception->getMessage()}", ['plugin' => 'common-gateway/waardepapieren-bundle']);
            return $this->data;
        }

        return $zaakObject->toArray(['embedded' => true]);

    }//end getZaak()


    /**
     * Gets ZaakType through synchronization in gateway.
     *
     * @param array        $zaak             Zaak array object.
     * @param ObjectEntity $zaakObject
     * @param string|null  $zaakTypeSourceId ZaakType id from its source.
     *
     * @return ObjectEntity|null BSN if found from zaak.
     * @throws Exception
     */
    private function getZaakType(array $zaak, ObjectEntity $zaakObject, ?string &$zaakTypeSourceId=null): ?ObjectEntity
    {
        if (is_string($zaak['zaaktype']) === true) {
            $zaaktypeUrl = $zaak['zaaktype'];
        }

        if (is_string($zaak['zaaktype']) === false) {
            // Get the zaaktype from the source with the url from the zaak.
            $zaaktype = $zaakObject->getValue('zaaktype');
            if ($zaaktype instanceof ObjectEntity === false) {
                return null;
            }

            $zaaktypeSync = $zaaktype->getSynchronizations()->first();
            $zaaktypeUrl  = $zaaktypeSync->getSourceId();
        }

        if (isset($zaaktypeUrl) === false) {
            $this->logger->error("ZaakType url is not set", ['plugin' => 'common-gateway/waardepapieren-bundle']);
            return $this->data;
        }

        return $this->getZaaktypeFromSource($zaaktypeUrl, $zaakTypeSourceId);

    }//end getZaakType()


    /**
     * Gets BSN from zaak.
     *
     * @param array $zaak Zaak array object.
     *
     * @return string|null BSN if found from zaak.
     */
    private function getBsnFromZaak(array $zaak): ?string
    {
        if (isset($zaak['embedded']['rollen'][0]['betrokkeneIdentificatie']['inpBsn'])) {
            return $zaak['embedded']['rollen'][0]['betrokkeneIdentificatie']['inpBsn'];
        }

        if (isset($zaak['embedded']['eigenschappen']) === true) {
            foreach ($zaak['embedded']['eigenschappen'] as $eigenschap) {
                if ($eigenschap['naam'] === 'BSN'
                    || $eigenschap['naam'] === 'bsn'
                ) {
                    return $eigenschap['waarde'];
                }
            }
        }

        return null;

    }//end getBsnFromZaak()


    /**
     * Gets BSN from Zaak and fetches BRP persoonsgegevens with waardepapierService.
     *
     * @param array                        $zaak      Zaak array object.
     * @param string                       $sourceRef Reference for Source object (brp).
     * @param Source zrcSource  ZRC source.
     *
     * @return array Persoonsgegevens.
     */
    private function getPersoonsgegevens(array $zaak, string $sourceRef, Source $zrcSource): ?array
    {
        $bsn = $this->getBsnFromZaak($zaak);
        if ($bsn === null) {
            $this->logger->error("BSN not found in Zaak, trying to fetch rollen of a Zaak.", ['plugin' => 'common-gateway/waardepapieren-bundle']);

            // Fetch rollen from Zaak and check for a BSN.
            $response = $this->callService->call($zrcSource, "/rollen?zaak={$this->data['body']['resourceUrl']}");
            $response = $this->callService->decodeResponse($zrcSource, $response);
            foreach ($response['results'] as $rol) {
                if (isset($rol['betrokkeneIdentificatie']['inpBsn']) === true) {
                    $bsn = $rol['betrokkeneIdentificatie']['inpBsn'];
                    break;
                }
            }
        }

        $this->waardepapierService->configuration['source'] = $sourceRef;
        return $this->waardepapierService->fetchPersoonsgegevens($bsn);

    }//end getPersoonsgegevens()


    /**
     * Creates a certificate for a ZGW Zaak.
     * This action is triggered by a notification.
     *
     * @param array $data          Data from the handler where the xxllnc casetype is in.
     * @param array $configuration Configuration for the Action.
     *
     * @return array $this->data Zaak which we updated with new data
     * @throws Exception
     */
    public function zaakNotificationHandler(array $data, array $configuration): array
    {
        $this->configuration = $configuration;
        $this->waardepapierService->configuration = $configuration;
        $this->data = $data;

        $this->logger->debug("WaardepapierenBundle -> ZaakNotificationService -> zaakNotificationHandler()", ['plugin' => 'common-gateway/waardepapieren-bundle']);

        $application = $this->applicationService->getApplication();
        if ($application === null || $application->getPrivateKey() === null || empty($application->getDomains()) === true) {
            $this->logger->error("Application is null, has no private key or has no domains", ['plugin' => 'common-gateway/waardepapieren-bundle']);
            return $this->data;
        }

        $zrcSource  = $this->resourceService->getSource($this->configuration['zrcSource'], 'common-gateway/waardepapieren-bundle');
        $zaakSchema = $this->resourceService->getSchema($this->configuration['zaakSchema'], 'common-gateway/waardepapieren-bundle');

        if ($zrcSource instanceof Source === false || $zaakSchema instanceof Schema === false) {
            $this->logger->error("zrcSource instanceof Source is false or zaakSchema instanceof Schema is false", ['plugin' => 'common-gateway/waardepapieren-bundle']);
            return $this->data;
        }

        // $zaakObject gets passed back here in getZaak function by the ampersand &.
        $zaakObject = null;
        $zaak       = $this->getZaak($zrcSource, $zaakSchema, $zaakObject);

        // $zaakTypeSourceId gets passed back here in getZaakType function by the ampersand &.
        $zaakTypeSourceId = null;
        $zaakType         = $this->getZaakType($zaak, $zaakObject, $zaakTypeSourceId);
        if ($zaakType === null) {
            return $this->data;
        }

        // Check if we have config for this source id.
        if (isset($this->configuration['zaakTypen'][$zaakTypeSourceId]) === false) {
            $this->logger->error("No action config found for ZaakType ID: $zaakTypeSourceId", ['plugin' => 'common-gateway/waardepapieren-bundle']);

            return $this->data;
        }

        $zaakTypeConfig = $this->configuration['zaakTypen'][$zaakTypeSourceId];

        $dataToMap = [
            'zaak'              => $zaak,
            'applicationDomain' => $application->getDomains()[0],
        ];

        foreach ($zaakTypeConfig['sources'] as $type => $reference) {
            switch ($type) {
            case 'brp':
                $dataToMap['persoonsgegevens'] = $this->getPersoonsgegevens($zaak, $reference, $zrcSource);
                break;
            default:
                break;
            }
        }

        $claim   = $this->waardepapierService->createClaim($dataToMap, $zaakTypeConfig['mapping']);
        $jwt     = $this->waardepapierService->createJWT($claim, $application->getPrivateKey());
        $qrImage = $this->waardepapierService->createQRImage($jwt);

        // Get the informatieobjecttypen of the zaaktype to set to the enkelvoudiginformatieobject.
        // TODO: how do we know which we need to get?
        $informatieobjecttypen   = $zaakType->getValue('informatieobjecttypen');
        $informatieobjecttypeUrl = $informatieobjecttypen[0]->getValue('url');

        $templateData = [
            'claim'   => $claim,
            'qrImage' => $qrImage,
        ];

        // Fill certificate with persons information and/or zaak.
        $certificate = $this->downloadService->downloadPdf($templateData, $zaakTypeConfig['template']);
        // Store waardepapier in DRC source.
        $this->saveWaardepapierInDRC($certificate, $zaakObject, $informatieobjecttypeUrl);

        // Store resultaat and status in ZRC source.
        // Only create a new resultaat and status if there is not one yet (1 max per zaak).
        if (isset($zaak['resultaat']) === false) {
            $this->saveInZRC($zaakObject, $zaakType);
        }

        // Get the zaak from source with updated data.
        $zaak = $this->getZaakFromSource($zaakObject);

        // Set the zaak as response in the dataArray response.
        $this->data['response'] = new Response(json_encode($zaak->toArray()), 200);

        $this->logger->warning("Succesfully added waardepapier to Zaak and synced the Zaak and its subobjects back to its source.", ['plugin' => 'common-gateway/waardepapieren-bundle']);
        $this->logger->notice("Succesfully added waardepapier to Zaak and synced the Zaak and its subobjects back to its source.", ['plugin' => 'common-gateway/waardepapieren-bundle']);

        return $this->data;

    }//end zaakNotificationHandler()


}//end class
