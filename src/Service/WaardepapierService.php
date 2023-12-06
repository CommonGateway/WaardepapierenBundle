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
     * @param array $claim           The certificate object.
     * @param string $certificateKey Certificate to sign the jws with.
     *
     * @return string The generated jwt token.
     */
    public function createJWT(array $claim, string $certificateKey): ?string
    {
        $payload = $claim;


        // @TODO when ready for testing with certificates from action config
        $certificateKey = "-----BEGIN ENCRYPTED PRIVATE KEY-----
        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
        y6FTcXjXxaM7Mvyv0yE5ZA==
        -----END ENCRYPTED PRIVATE KEY-----
        ";

        $jwk = JWKFactory::createFromKey($certificateKey);

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
     * This function simply maps data with a mapping to a claim.
     *
     * @param array  $data Data to map to a claim.
     * @param string $mappingRef Reference to mapping to use.
     *
     * @return array The claim.
     */
    public function createClaim(array $data, string $mappingRef): ?array
    {
        $mapping = $this->resourceService->getMapping($mappingRef, 'common-gateway/waardepapieren-bundle');

        return $this->mappingService->mapping($data, $mapping);
    }//end createClaim()


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
