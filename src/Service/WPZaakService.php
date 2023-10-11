<?php

namespace CommonGateway\WaardepapierenBundle\Service;

use App\Entity\Gateway as Source;
use App\Entity\Entity as Schema;
use App\Entity\ObjectEntity;
use App\Entity\Synchronization;
use App\Service\SynchronizationService;
use Brick\Reflection\Tests\S;
use CommonGateway\CoreBundle\Service\CallService;
use CommonGateway\CoreBundle\Service\DownloadService;
use CommonGateway\CoreBundle\Service\GatewayResourceService;
use CommonGateway\CoreBundle\Service\MappingService;
use CommonGateway\WaardepapierenBundle\Service\WaardepapierService;
use CommonGateway\ZGWBundle\Service\DRCService;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Ramsey\Uuid\Uuid;
use Safe\DateTime;
use Symfony\Component\HttpFoundation\Response;
use Twig\Error\LoaderError;
use Twig\Error\SyntaxError;

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
     * __construct
     */
    public function __construct(
        EntityManagerInterface $entityManager,
        WaardepapierService $waardepapierService,
        DownloadService $downloadService,
        GatewayResourceService $resourceService,
        CallService $callService,
        MappingService $mappingService,
        SynchronizationService $syncService
    ) {
        $this->entityManager       = $entityManager;
        $this->waardepapierService = $waardepapierService;
        $this->downloadService     = $downloadService;
        $this->resourceService     = $resourceService;
        $this->callService         = $callService;
        $this->mappingService      = $mappingService;
        $this->syncService         = $syncService;

    }//end __construct()


    /**
     * New setup for synchronizing upstream.
     * To be added in an adapted form into the core synchronizationService.
     *
     * @param Synchronization $synchronization The synchronization to synchronize
     *
     * @return bool Whether the synchronization has passed.
     *
     * @throws LoaderError
     * @throws SyntaxError
     */
    public function synchronizeUpstream(Synchronization $synchronization): bool
    {
        $data = $this->mappingService->mapping($synchronization->getMapping(), $synchronization->getObject()->toArray());

        $response     = $this->callService->call($synchronization->getSource(), $synchronization->getEndpoint(), 'POST', ['json' => $data]);
        $updateObject = $this->callService->decodeResponse($synchronization->getSource(), $response);

        $synchronization->getObject()->hydrate($updateObject);
        $this->entityManager->persist($synchronization->getObject());
        $this->entityManager->flush();

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
     */
    public function storeWaardepapierInSourceDRC(ObjectEntity $informatieobject, ObjectEntity $zaakinformatieobject, ObjectEntity $zaak): bool
    {
        if (count($zaak->getSynchronizations()) === 0) {
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
        $this->entityManager->flush();

        $result = $this->synchronizeUpstream($eioSync);

        if ($result === false) {
            return false;
        }

        $zioSync = new Synchronization();
        $zioSync->setSource($source);
        $zioSync->setMapping($this->resourceService->getMapping($this->configuration['zaakInfoMapping'], 'common-gateway/waardepapieren-bundle'));
        $zioSync->setEndpoint('/zaakinformatieobjecten');
        $zioSync->setObject($zaakinformatieobject);

        $this->entityManager->persist($zioSync);
        $this->entityManager->flush();

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
        $this->entityManager->flush();

        $caseInformationObjectSchema = $this->resourceService->getSchema('https://vng.opencatalogi.nl/schemas/zrc.zaakInformatieObject.schema.json', 'common-gateway/waardepapieren-bundle');
        if ($caseInformationObjectSchema === null) {
            return;
        }

        $caseInformationArray  = [
            'zaak'             => $zaakObject,
            'informatieobject' => $informationObject,
        ];
        $caseInformationObject = new ObjectEntity($caseInformationObjectSchema);

        $caseInformationObject->hydrate($caseInformationArray);
        $this->entityManager->persist($caseInformationObject);
        $this->entityManager->flush();

        $this->storeWaardepapierInSourceDRC($informationObject, $caseInformationObject, $zaakObject);

    }//end saveWaardepapierInDRC()


    /**
     * Gets the zaaktype from the given zaak
     *
     * @param ObjectEntity $zaak The zaak object.
     * @param string $objectUrl The url of the zaaktype.
     * @param string $schemaRef The reference of the schema
     * @param string $endpoint The endpoint
     *
     * @return ObjectEntity|null The zaaktype of the given source
     * @throws Exception
     */
    public function getZaaktypeSubObjects(ObjectEntity $zaak, string $objectUrl, string $schemaRef, string $endpoint): ?ObjectEntity
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

        // Check if we have a zaaktype with findSyncByObject.
        $objectSync = $this->syncService->findSyncBySource($source, $schema, $objectId);

        // if no object is present, a call must be made to retrieve the zaaktype.
        if ($objectSync->getObject() === null) {
            try {
                $response = $this->callService->call($source, $endpoint.'/'.$objectId);
            } catch (Exception $exception) {
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
     * @param array        $response The response of the call
     * @param ObjectEntity $zaak     The zaak object
     *
     * @return array The zaaktype of the given source
     */
    public function getSubobjects(array $response, ObjectEntity $zaak): array
    {
        foreach ($response['informatieobjecttypen'] as $infoObjectType) {
            $info = $this->getZaaktypeSubObjects($zaak, $infoObjectType, 'https://vng.opencatalogi.nl/schemas/ztc.informatieObjectType.schema.json', '/informatieobjecttypen');
            $response['informatieobjecttypen'][] = $info;
        }

        foreach ($response['eigenschappen'] as $eigenschap) {
            $eigenschapObject            = $this->getZaaktypeSubObjects($zaak, $eigenschap, 'https://vng.opencatalogi.nl/schemas/ztc.eigenschap.schema.json', '/eigenschappen');
            $response['eigenschappen'][] = $eigenschapObject;
        }

        foreach ($response['roltypen'] as $roltype) {
            $roltypeObject          = $this->getZaaktypeSubObjects($zaak, $roltype, 'https://vng.opencatalogi.nl/schemas/ztc.rolType.schema.json', '/roltypen');
            $response['roltypen'][] = $roltypeObject;
        }

        foreach ($response['statustypen'] as $statustype) {
            $statustypeObject          = $this->getZaaktypeSubObjects($zaak, $statustype, 'https://vng.opencatalogi.nl/schemas/ztc.statusType.schema.json', '/statustypen');
            $response['statustypen'][] = $statustypeObject;
        }

        foreach ($response['resultaattypen'] as $resultaattype) {
            $resultaattypeObject          = $this->getZaaktypeSubObjects($zaak, $resultaattype, 'https://vng.opencatalogi.nl/schemas/ztc.resultaatType.schema.json', '/resultaattype');
            $response['resultaattypen'][] = $resultaattypeObject;
        }

        return $response;

    }//end getSubobjects()


    /**
     * Gets the zaaktype from the given zaak
     *
     * @param ObjectEntity $zaak The zaak object.
     * @param string $zaaktypeUrl The url of the zaaktype.
     *
     * @return ObjectEntity|null The zaaktype of the given source
     * @throws Exception
     */
    public function getZaaktypeFromSource(ObjectEntity $zaak, string $zaaktypeUrl): ?ObjectEntity
    {
        // Get zaaktype schema.
        $schema = $this->resourceService->getSchema('https://vng.opencatalogi.nl/schemas/ztc.zaakType.schema.json', 'common-gateway/waardepapieren-bundle');
        $source = $this->resourceService->getSource($this->configuration['ztcSource'], 'common-gateway/waardepapieren-bundle');

        // Get the uuid from the zaaktype url.
        $explodedUrl = explode('/', $zaaktypeUrl);
        foreach ($explodedUrl as $item) {
            if (Uuid::isValid($item)) {
                $zaaktypeId = $item;
            }
        }

        // Check if we have a zaaktype with findSyncByObject.
        $zaaktypeSync = $this->syncService->findSyncBySource($source, $schema, $zaaktypeId);

        // if no object is present, a call must be made to retrieve the zaaktype.
        try {
            $response = $this->callService->call($source, '/zaaktypen/'.$zaaktypeId);
        } catch (Exception $exception) {
            throw new Exception($exception->getMessage());
        }

        $response     = $this->callService->decodeResponse($source, $response);
        $response     = $this->getSubobjects($response, $zaak);
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
        $this->entityManager->flush();

        $result = $this->synchronizeUpstream($resultaatSync);

        if ($result === false) {
            return false;
        }

        $statusSync = new Synchronization();
        $statusSync->setSource($source);
        $statusSync->setMapping($this->resourceService->getMapping($this->configuration['statusMapping'], 'common-gateway/waardepapieren-bundle'));
        $statusSync->setEndpoint('/statussen');
        $statusSync->setObject($status);

        $this->entityManager->persist($statusSync);
        $this->entityManager->flush();

        $result = $this->synchronizeUpstream($statusSync);

        return $result;

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
            return;
        }

        $resultaatArray = [
            'zaak'          => $zaakObject,
            'resultaattype' => $resultaattype['url'],
        ];

        $resultaatSchema = $this->resourceService->getSchema('https://vng.opencatalogi.nl/schemas/zrc.resultaat.schema.json', 'common-gateway/waardepapieren-bundle');
        if ($resultaatSchema === null) {
            return;
        }

        $resultaatObject = new ObjectEntity($resultaatSchema);

        $resultaatObject->hydrate($resultaatArray);
        $this->entityManager->persist($resultaatObject);
        $this->entityManager->flush();

        $statusSchema = $this->resourceService->getSchema("https://vng.opencatalogi.nl/schemas/zrc.status.schema.json", 'common-gateway/waardepapieren-bundle');
        if ($statusSchema === null) {
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
        $this->entityManager->flush();

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
        $source = $this->resourceService->getSource($this->configuration['zrcSource'], 'common-gateway/waardepapieren-bundle');

        try {
            $response = $this->callService->call($source, '/zaken');
        } catch (Exception $exception) {
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
     * Creates a certificate for a ZGW Zaak.
     * This action is triggered by a notification.
     *
     * @param array $data          Data from the handler where the xxllnc casetype is in.
     * @param array $configuration Configuration for the Action.
     *
     * @return array $this->data Zaak which we updated with new data
     */
    public function wpZaakHandler(array $data, array $configuration): array
    {
        $this->configuration = $configuration;
        $this->data          = $data;

        $source = $this->resourceService->getSource($this->configuration['zrcSource'], 'common-gateway/waardepapieren-bundle');
        $schema = $this->resourceService->getSchema($this->configuration['zaakSchema'], 'common-gateway/waardepapieren-bundle');
        if ($source instanceof Source === false || $schema instanceof Schema === false) {
            return $this->data;
        }

        // Get the zaak url from the body of the request.
        $zaakUrl = $this->data['body']['resourceUrl'];
        // Get the uuid from the zaaktype url.
        $explodedUrl = explode('/', $zaakUrl);
        foreach ($explodedUrl as $item) {
            if (Uuid::isValid($item)) {
                $zaakId = $item;
            }
        }

        // Find the zaak object through the source and sourceId.
        $zaakSync = $this->syncService->findSyncBySource($source, $schema, $zaakId);
        if (($zaakObject = $zaakSync->getObject()) === null) {
            return $this->data;
        }

        $zaak = $zaakObject->toArray(['embedded' => true]);

        // Get the zaaktype from the source with the url from the zaak.
        $zaaktype     = $zaakObject->getValue('zaaktype');
        $zaaktypeSync = $zaaktype->getSynchronizations()->first();
        $zaaktypeUrl  = $zaaktypeSync->getSourceId();

        $zaaktype = $this->getZaaktypeFromSource($zaakObject, $zaaktypeUrl);

        // Get the informatieobjecttypen of the zaaktype to set to the enkelvoudiginformatieobject.
        $informatieobjecttypen   = $zaaktype->getValue('informatieobjecttypen');
        $informatieobjecttypeUrl = $informatieobjecttypen[0]->getValue('url');
        // TODO: how do we know which we need to get?
        // Fill certificate with persons information and/or zaak.
        $certificate = $this->downloadService->downloadPdf($zaak);

        // Store waardepapier in DRC source.
        $this->saveWaardepapierInDRC($certificate, $zaakObject, $informatieobjecttypeUrl);

        // Store resultaat and status in ZRC source.
        $this->saveInZRC($zaakObject, $zaaktype);

        // Get the zaak from source with updated data.
        $zaak = $this->getZaakFromSource($zaakObject);

        // Set the zaak as response in the dataArray response.
        $dataArray['response'] = new Response(json_encode($zaak), 200);

        // Create a certificate for the case.
        // $this->waardepapierService->waardepapierHandler($dataArray, $this->configuration);
        return $this->data;

    }//end wpZaakHandler()


}//end class
