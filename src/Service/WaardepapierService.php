<?php

namespace CommonGateway\WaardepapierenBundle\Service;

use App\Entity\Entity;
use App\Entity\Gateway;
use App\Entity\ObjectEntity;
use CommonGateway\CoreBundle\Service\CallService;
use CommonGateway\CoreBundle\Service\DownloadService;
use CommonGateway\CoreBundle\Service\GatewayResourceService;
use CommonGateway\CoreBundle\Service\FileService;
use CommonGateway\CoreBundle\Service\MappingService;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Dompdf\Dompdf;
use Endroid\QrCode\Factory\QrCodeFactoryInterface;
use Exception;
use Jose\Component\Core\AlgorithmManager;
use Jose\Component\KeyManagement\JWKFactory;
use Jose\Component\Signature\Algorithm\RS512;
use Jose\Component\Signature\Serializer\CompactSerializer;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment as Twig;

/**
 * WaardepapierService creates certificates
 * WaardepapierService creates certificates by template, given data, or created zgw zaak.
 *
 * @author   Barry Brands barry@conduction.nl
 * @package  common-gateway/waardepapieren-bundle
 * @category Service
 * @access   public
 */
class WaardepapierService
{

    /**
     * @var EntityManagerInterface
     */
    private EntityManagerInterface $entityManager;

    /**
     * @var QrCodeFactoryInterface
     */
    private QrCodeFactoryInterface $qrCode;

    /**
     * @var CallService
     */
    private CallService $callService;

    /**
     * @var GatewayResourceService
     */
    private GatewayResourceService $resourceService;

    /**
     * @var DownloadService
     */
    private DownloadService $downloadService;

    /**
     * @var MappingService
     */
    private MappingService $mappingService;

    /**
     * @var array $configuration of the current action.
     */
    public array $configuration;

    /**
     * @var array $data of the current action.
     */
    public array $data;


    /**
     * @param EntityManagerInterface $entityManager
     * @param QrCodeFactoryInterface $qrCode
     * @param CallService            $callService
     * @param GatewayResourceService $resourceService
     * @param DownloadService        $downloadService
     * @param MappingService         $mappingService
     */
    public function __construct(
        EntityManagerInterface $entityManager,
        QrCodeFactoryInterface $qrCode,
        CallService $callService,
        GatewayResourceService $resourceService,
        DownloadService $downloadService,
        MappingService $mappingService
    ) {
        $this->entityManager   = $entityManager;
        $this->qrCode          = $qrCode;
        $this->callService     = $callService;
        $this->resourceService = $resourceService;
        $this->downloadService = $downloadService;
        $this->mappingService  = $mappingService;

    }//end __construct()


    // private function sendEnkelvoudigInformatieObject($enkelvoudigInformatieObject)
    // {
    // try {
    // $response = $this->callService->call(
    // $this->openZaakSource,
    // '/enkelvoudiginformatieobjecten',
    // 'POST',
    // ['body' => $enkelvoudigInformatieObject]
    // );
    // } catch (\Exception $exception) {
    // throw new Exception($exception->getMessage());
    // }
    //
    // return $this->callService->decodeResponse($this->openZaakSource, $response);
    //
    // }//end sendEnkelvoudigInformatieObject()
    //
    //
    // private function sendObjectInformatieObject($objectInformatieObject)
    // {
    // try {
    // $response = $this->callService->call(
    // $this->openZaakSource,
    // '/objectinformatieobjecten',
    // 'POST',
    // ['body' => $objectInformatieObject]
    // );
    // } catch (\Exception $exception) {
    // throw new Exception($exception->getMessage());
    // }
    //
    // return $this->callService->decodeResponse($this->openZaakSource, $response);
    //
    // }//end sendObjectInformatieObject()
    //
    //
    // private function createInformatieObject()
    // {
    // $today = new DateTime();
    // $enkelvoudigInformatieObject = [
    // 'bronorganisatie'              => 'bsn buren',
    // 'creatiedatum'                 => $today->format('Y-m-d'),
    // 'titel'                        => 'Waardepapier'.$this->certificate['type'],
    // 'vertrouwelijkheidsaanduiding' => 'vertrouwelijk',
    // 'auteur'                       => 'bsn buren',
    // 'status'                       => 'gearchiveerd',
    // 'formaat'                      => 'application/pdf',
    // 'taal'                         => 'nld',
    // 'versie'                       => 1,
    // 'beginRegistratie'             => $today->format('Y-m-d'),
    // 'bestandsnaam'                 => 'todo',
    // 'inhoud'                       => ($this->certificate['pdf'] ?? 'todo'),
    // 'beschrijving'                 => 'Waardepapier '.$this->certificate['type'],
    // 'ontvangstdatum'               => $today->format('Y-m-d'),
    // 'verzenddatum'                 => $today->format('Y-m-d'),
    // 'informatieobjecttype'         => '?',
    // ];
    //
    // $enkelvoudigInformatieObjectResult = $this->sendEnkelvoudigInformatieObject($enkelvoudigInformatieObject);
    //
    // Check is valid
    // if ($enkelvoudigInformatieObject) {
    // }
    //
    // $objectInformatieObject = [
    // 'informatieobject' => $enkelvoudigInformatieObjectResult['id or uri'],
    // 'object'           => $this->userData['zaakId or uri'],
    // 'objectType'       => 'zaak',
    // ];
    //
    // $this->sendObjectInformatieObject($objectInformatieObject);
    //
    // }//end createInformatieObject()


    /**
     * Creates or updates a dynamic Certificate.
     *
     * @param array $data          Data from the handler where the certificate info is in.
     * @param array $configuration Configuration for the Action.
     *
     * @return array $this->certificate Certificate which we updated with new data
     */
    public function waardepapierenDynamicHandler(array $data, array $configuration): array
    {
        $this->userData      = $data['request'];
        $certificate         = $data['request'];
        $this->configuration = $configuration;

        return ['response' => $certificate];

    }//end waardepapierenDynamicHandler()


    /**
     * This function creates a QR code for the given claim.
     *
     * @param array $certificate The certificate object
     *
     * @return string The image as a string
     */
    public function createImage(array $certificate): string
    {
        // Then we need to render the QR code
        $qrCode = $this->qrCode->create(
        // $certificate['jwt'], //@todo some ssl certs dont work
            "QR code with text",
            // @todo remove if above line works
            [
                'size'   => 1000,
                'margin' => 1,
                'writer' => 'png',
            ]
        );

        // And finnaly we need to set the result on the certificate resource
        return 'data:image/png;base64,'.base64_encode($qrCode->writeString());

    }//end createImage()


    /**
     * This function creates the (pdf) document for a given certificate type.
     *
     * @param string $schemaId
     * @param array  $certificate The certificate object
     * @param array  $brpPersoon
     *
     * @return string
     */
    public function createDocument(string $schemaId, array $certificate, array $brpPersoon): string
    {
        $data = [
            '_self'  => [
                'schema' => ['id' => $schemaId],
            ],
            'qr'     => $certificate['image'],
            'claim'  => $certificate['claim'],
            'person' => $brpPersoon,
            'base'   => '/organizations/'.$certificate['organization'].'.html.twig',
        ];

        $document = $this->downloadService->downloadPdf($data);

        // And finnaly we need to set the result on the certificate resource
        return 'data:application/pdf;base64,'.base64_encode($document);

    }//end createDocument()


    /**
     * This function generates a jwt token using the claim that's available from the certificate object.
     *
     * @param array $certificate The certificate object
     *
     * @return string The generated jwt token
     */
    public function createJWT(array $certificate): ?string
    {
        $source = $this->resourceService->getSource($this->configuration['source'], 'common-gateway/waardepapieren-bundle');

        // Create a payload
        $payload = $certificate['claim'];

        if (key_exists('ssl_key', $source->getConfiguration()) === false) {
            return null;
        }

        $jwk = JWKFactory::createFromKey($source->getConfiguration()['ssl_key'][0]);

        $jwsBuilder = new \Jose\Component\Signature\JWSBuilder(new AlgorithmManager([new RS512()]));
        $jws        = $jwsBuilder
            ->create()
            ->withPayload(json_encode($payload))
            ->addSignature($jwk, ['alg' => 'RS512'])
            ->build();
        $serializer = new CompactSerializer();

        return $serializer->serialize($jws, 0);

    }//end createJWT()


    /**
     * This function fetches a haalcentraal persoon with the callService.
     *
     * @param string $bsn The bsn of the person.
     *
     * @throws Exception
     *
     * @return array The person as array
     */
    public function fetchPersoonsgegevens(string $bsn): ?array
    {
        $source = $this->resourceService->getSource($this->configuration['source'], 'common-gateway/waardepapieren-bundle');
        if ($source !== null || $source->getIsEnabled() === false) {
            return [];
        }

        if (key_exists('brpEndpoint', $this->configuration) === true) {
            $endpoint = $this->configuration['brpEndpoint'];
        }

        if (key_exists('brpEndpoint', $this->configuration) === false) {
            $endpoint = 'ingeschrevenpersonen';
        }

        $endpoint = '/'.$endpoint.'/'.$bsn;
        var_dump('$endpoint');
        var_dump($endpoint);
        var_dump('------');

        try {
            $response = $this->callService->call(
                $source,
                $endpoint,
                'GET'
            );
        } catch (\Exception $exception) {
            // Todo set error log
            throw new Exception($exception->getMessage());
        }//end try

        $brpPersoon = $this->callService->decodeResponse($source, $response);
        unset($brpPersoon['_links']);

        return $brpPersoon;

    }//end fetchPersoonsgegevens()


    /**
     * Creates or updates a Certificate.
     *
     * @param array $data          Data from the handler where the xxllnc casetype is in.
     * @param array $configuration Configuration for the Action.
     *
     * @throws Exception
     *
     * @return array $this->certificate Certificate which we updated with new data
     */
    public function waardepapierHandler(array $data, array $configuration): array
    {
        $this->configuration = $configuration;
        $this->data          = $data;

        $responseContent = $this->data['response']->getContent();
        $certificate     = \Safe\json_decode($responseContent, true);

        $certificateObject = $this->entityManager->getRepository("App:ObjectEntity")->find($certificate['id']);
        if ($certificateObject instanceof ObjectEntity === false) {
            return $this->data;
        }

        // 1. Get persons information from the given source.
        $brpPersoon = $certificate['personObject'] = $this->fetchPersoonsgegevens($certificate['person']);

        // 2. Check if the zaak is set and get the id.
        $zaakId = null;
        if (isset($certificate['zaak']['_self']['id']) === true
        ) {
            $zaakId = $certificate['zaak']['_self']['id'];
        }

        // 3. Create the image for the certificate.
        $image = $this->createImage($certificate);

        // 4. Make a data array to map from.
        $data = [
            'brpPerson'   => $brpPersoon,
            'certificate' => $certificate,
            'image'       => $image,
            'zaak'        => $zaakId,
        ];

        // 4. Get the mapping and map the certificate.
        $mapping          = $this->resourceService->getMapping($this->configuration['mapping'], 'common-gateway/waardepapieren-bundle');
        $certificateArray = $this->mappingService->mapping($mapping, $data);

        // 5. Create the JWT and Document for the certificate using the already mapped certificate.
        // $data['jwt'] = $this->createJWT($certificateArray);
        $data['document'] = $this->createDocument($certificate['_self']['schema']['id'], $certificateArray, $brpPersoon);

        // 6. Map the certificate again with the jwt and document
        $certificateArray = $this->mappingService->mapping($mapping, $data);

        $certificateObject->hydrate($certificateArray);
        $this->entityManager->persist($certificateObject);
        $this->entityManager->flush();

        return ['response' => new Response(json_encode($certificateObject->toArray()), 200, ['Content-Type' => 'application/json'])];

    }//end waardepapierHandler()


}//end class
