<?php

namespace CommonGateway\WaardepapierenBundle\Service;

use DateTime;
use App\Entity\Entity;
use App\Entity\ObjectEntity;
use App\Entity\Gateway;
use Doctrine\ORM\EntityManagerInterface;
use Endroid\QrCode\Factory\QrCodeFactoryInterface;
use Dompdf\Dompdf;
use Jose\Component\Core\AlgorithmManager;
use Jose\Component\KeyManagement\JWKFactory;
use Jose\Component\Signature\Algorithm\RS512;
use Jose\Component\Signature\Serializer\CompactSerializer;
use Twig\Environment as Twig;
use CommonGateway\CoreBundle\Service\CallService;
use CommonGateway\CoreBundle\Service\FileService;
use Exception;

/**
 * WaardepapierService creates certificates
 * WaardepapierService creates certificates by template, given data, or created zgw zaak.
 * 
 * @author Barry Brands barry@conduction.nl 
 * @package common-gateway/waardepapieren-bundle 
 * @category Service
 * @access public  
 */
class WaardepapierService
{

    /**
     * @var EntityManagerInterface
     */
    private EntityManagerInterface $entityManager;
    
    /**
     * @var Twig
     */
    private Twig $twig;

    /**
     * @var QrCodeFactoryInterface
     */
    private QrCodeFactoryInterface $qrCode;
    
    /**
     * @var CallService
     */
    private CallService $callService;
    
    /**
     * @var FileService
     */
    private FileService $fileService;

    /**
     * @var Entity|null $certificate schema we are creating a object with.
     */
    private ?Entity $certificateEntity;

    /**
     * @var Gateway|null $haalcentraalGateway source to get brp info from.
     */
    private ?Gateway $haalcentraalGateway;

    /**
     * @var array $configuration of the current action.
     */
    private array $configuration;

    /**
     * @var array $userData that is being used to create a certificate.
     */
    private ?array $userData;

    /**
     * @var ObjectEntity|null $certTemplate template to create certificate with.
     */
    private ?ObjectEntity $certTemplate;

    /**
     * @param EntityManagerInterface $entityManager
     */
    public function __construct(
        EntityManagerInterface $entityManager,
        Twig $twig,
        QrCodeFactoryInterface $qrCode,
        CallService $callService,
        FileService $fileService
    ) {
        $this->entityManager = $entityManager;
        $this->twig = $twig;
        $this->qrCode = $qrCode;
        $this->callService = $callService;
        $this->fileService = $fileService;

        $this->certificate = [];

    }//end construct()

    /**
     * This function creates a QR code for the given claim.
     *
     * @param array $this->certificate The certificate object
     *
     * @return array The modified certificate object
     */
    public function createImage()
    {
        // Then we need to render the QR code
        $qrCode = $this->qrCode->create($this->certificate['jwt'], [
            'size'  => 1000,
            'margin' => 1,
            'writer' => 'png',
        ]);

        // And finnaly we need to set the result on the certificate resource
        $this->certificate['image'] = 'data:image/png;base64,' . base64_encode($qrCode->writeString());
    
    }//end createImage()

    /**
     * This function generates a claim based on the w3c structure.
     *
     * @param array       $data        The data used to create the claim
     * @param array $this->certificate The certificate object
     *
     * @throws \Exception
     *
     * @return array The generated claim
     */
    public function w3cClaim(array $data, array $certificate)
    {
        $now = new \DateTime('now', new \DateTimeZone('Europe/Amsterdam'));
        $array = [];
        $array['@context'] = ['https://www.w3.org/2018/credentials/v1', 'https://www.w3.org/2018/credentials/examples/v1'];
        $array['id'] = $certificate['id'] ?? null;
        $array['type'] = ['VerifiableCredential', $certificate['type'] ?? 'dynamic'];
        $array['issuer'] = $certificate['organization'];
        $array['inssuanceDate'] = $now->format('H:i:s d-m-Y');
        $array['credentialSubject']['id'] = $certificate['personObject']['burgerservicenummer'] ?? $certificate['organization'];
        foreach ($data as $key => $value) {
            $array['credentialSubject'][$key] = $value;
        }
        $array['proof'] = $this->createProof($array, $certificate);

        return $array;

    }//end w3cClaim()


    /**
     * This function creates a proof.
     *
     * @param array       $data        the data that gets stored in the jws token of the proof
     * @param array       $certificate the certificate object
     *
     * @return array proof
     */
    public function createProof(array $data, array $certificate)
    {
        $proof = [];
        $proof['type'] = 'RsaSignature';
        // @TODO when should created be set, we have no cert key file anymore ?
        // $proof['created'] = date('H:i:s d-m-Y', filectime("cert/{" . $this->certificate['organization'] . "}.pem"));
        $proof['proofPurpose'] = 'assertionMethode';
        // @TODO what should the verifymethod be now, we have no cert key file anymore ?
        // $proof['verificationMethod'] = $this->requestStack->getCurrentRequest()->getSchemeAndHttpHost() . "/cert/{" . $this->configuration['organization'] . "}.pem";
        $proof['verificationMethod'] = 'http://localhost/cert/00000010.pem';
        $proof['jws'] = $this->createJWS($certificate, $data['credentialSubject']);

        return $proof;

    }//end createProof()

    /**
     * This function generates a JWS token with the RS512 algorithm.
     *
     * @param array       $data        the data that gets stored in the jws token
     * @param array       $certificate the certificate object
     *
     * @return string Generated JWS token.
     */
    public function createJWS(array $data, array $certificate)
    {
        $algorithmManager = new AlgorithmManager([
            new RS512(),
        ]);
        // Old
        // $jwk = JWKFactory::createFromKeyFile(
        //     "../cert/{" . $this->certificate['organization'] . "}.pem"
        // );
        // New
        $jwk = JWKFactory::createFromKey(
            $this->configuration['certificateKey']
        );
        $jwsBuilder = new \Jose\Component\Signature\JWSBuilder($algorithmManager);
        $payload = json_encode([
            'iat'  => time(),
            'nbf'  => time(),
            'exp'  => time() + 3600,
            // 'crt'  => $this->commonGroundService->cleanUrl(['component' => 'frontend', 'type' => 'claims/public_keys', 'id' => $this->certificate['organization']]),
            'iss'  => $certificate['id'] ?? null,
            'aud'  => $certificate['personObject']['burgerservicenummer'] ?? $certificate['organization'],
            'data' => $data,
        ]);
        $jws = $jwsBuilder
            ->create()
            ->withPayload($payload)
            ->addSignature($jwk, ['alg' => 'RS512'])
            ->build();
        $serializer = new CompactSerializer();

        return $serializer->serialize($jws, 0);

    }//end createJWS()

    /**
     * This function creates the (pdf) document for a given certificate type.
     *
     * @param array $template The twig template
     *
     * @throws \Twig\Error\LoaderError
     * @throws \Twig\Error\RuntimeError
     * @throws \Twig\Error\SyntaxError
     * @throws \Exception
     */
    public function createDocument()
    {
        $data = [
            'qr'     => $this->certificate['image'],
            'claim'  => $this->certificate['claim'],
            'person' => $this->certificate['personObject'] ?? null,
            'base'   => '/organizations/' . $this->certificate['organization'] . '.html.twig',
        ];

        if (isset($this->userData)) {
            $data['data'] = $this->userData;
        }

        // if ($this->certificate['type'] == 'historisch_uittreksel_basis_registratie_personen') {
        //     $data['verblijfplaatshistorie'] = $this->commonGroundService->getResourceList(['component' => 'brp', 'type' => 'ingeschrevenpersonen', 'id' => $this->certificate->getPersonObject()['burgerservicenummer'].'/verblijfplaatshistorie'])['_embedded']['verblijfplaatshistorie'];
        // }

        try {
            // First we need the HTML  for the template
            $createdTemplate = $this->twig->createTemplate($this->certTemplate['content']);
            $html = $this->twig->render($createdTemplate, $data);
        } catch (Exception $e) {
            throw new Exception('Something went wrong while creating the template, the available data might not be compatible with the template.');
        }

        // $html = json_encode($data);
        // var_dump($html);die;

        // Then we need to render the template
        $dompdf = new DOMPDF();
        $dompdf->loadHtml($html);
        $dompdf->render();

        // And finnaly we need to set the result on the certificate resource
        $this->certificate['document'] = 'data:application/pdf;base64,' . base64_encode($dompdf->output());

    }//end createDocument()

    /**
     * This function generates a jwt token using the claim that's available from the certificate object.
     *
     * @param array $this->certificate The certificate object
     *
     * @return string The generated jwt token
     */
    public function createJWT(array $certificate)
    {
        // Create a payload
        $payload = $certificate['claim'];

        $algorithmManager = new AlgorithmManager([
            new RS512(),
        ]);
        $jwk = JWKFactory::createFromKey($this->configuration['certificateKey']);
        // $jwk = JWKFactory::createFromKeyFile(
        //     "../cert/{" . $this->certificate['organization'] . "}.pem"
        // );
        $jwsBuilder = new \Jose\Component\Signature\JWSBuilder($algorithmManager);
        $jws = $jwsBuilder
            ->create()
            ->withPayload(json_encode($payload))
            ->addSignature($jwk, ['alg' => 'RS512'])
            ->build();
        $serializer = new CompactSerializer();

        return $serializer->serialize($jws, 0);

    }//end createJWT()

    /**
     * This function creates the claim based on the type defined in the certificate object.
     *
     * @throws \Exception
     */
    public function createClaim(array $certificate)
    {
        // Lets add data to this claim
        if (isset($certificate['claimData'])) {
            $claimData = $certificate['claimData'] ?? [];
        } elseif (isset($certificate['data'])) {
            $claimData = $this->certificate['data'];
        }

        // switch ($this->certificate['type']) {
        // }

        $certificate['w3c'] = $this->w3cClaim($claimData, $certificate);
        isset($certificate['person']) && $claimData['persoon'] = $certificate['personObject']['burgerservicenummer'];

        $claimData['doel'] = $certificate['type'] ?? 'dynamic';
        $certificate['claimData'] = $claimData;

        // Create token payload as a JSON string
        $certificate['claim'] = [
            'iss'                 => $certificate['id'] ?? null,
            'user_id'             => $certificate['personObject']['id'] ?? $certificate['organization'],
            'user_representation' => $certificate['personObject']['@id'] ?? $certificate['organization'],
            'claim_data'          => $certificate['claimData'],
            // 'validation_uri'      => $this->commonGroundService->cleanUrl(['component' => 'frontend', 'type' => 'claims/public_keys', 'id' => $this->certificate->getOrganization()]),
            'iat'                 => time(),
        ];

        // Create token payload as a JSON string
        $certificate['discipl'] = [
            'claimData' => [
                'did:discipl:ephemeral:crt:4c86faf535029c8cf4a371813cc44cb434875b18' => [
                    'link:discipl:ephemeral:tEi6K3mPRmE6QRf4WvpxY1hQgGmIG7uDV85zQILQNSCnQjAZPg2mj4Fbok/BHL9C8mFJQ1tCswBHBtsu6NIESA45XnN13pE+nLD6IPOeHx2cUrObxtzsqLhAy4ZXN6eDpZDmqnb6ymELUfXu/D2n4rL/t9aD279vqjFRKgBVE5WsId9c6KEYA+76mBQUBoJr8sF7w+3oMjzKy88oW693I3Keu+cdl/9sRCyYAYIDzwmg3A6n8t9KUpsBDK1b6tNznA6qoiN9Zb4JZ7rpq6lnVpyU5pyJjD+p9DiWgIYsVauJy8WOcKfNWkeOomWez0of2o+gu9xf+VLzcX3MSiAfZA==' => $this->certificate['claimData'],
                ],
            ],
            'metadata' => ['cert' => 'localhost:8080'],
        ];

        // Create token payload as a JSON string
        $certificate['irma'] = $this->certificate['discipl'];

        $certificate['jwt'] = $this->createJWT($certificate);

        return $certificate;

    }//end createClaim()

    /**
     * This function fetches a haalcentraal persoon with the callService.
     *
     * @throws \Exception
     *
     * @return array The modified certificate object
     */
    public function fetchPersoonsgegevens(Gateway $haalcentraalGateway, string $bsn)
    {
        $certFile = $this->fileService->writeFile('brp-cert', $this->configuration['authorization']['certificate'], 'crt');
        $certKeyFile = $this->fileService->writeFile('brp-cert-key', $this->configuration['authorization']['certificateKey'], 'key');

        try {
            $response = $this->callService->call(
                $haalcentraalGateway,
                '/ingeschrevenpersonen/' . $bsn,
                'GET',
                [
                    'cert' => $certFile,
                    'ssl_key' => [$certKeyFile, $this->configuration['authorization']['password']],
                    // 'ssl_key' => $certKeyFile,
                    'headers' => [
                        'x-doelbinding' => $this->configuration['authorization']['x-doelbinding'],
                        'x-origin-oin' => $this->configuration['authorization']['x-origin-oin']
                    ],
                    'verify' => false
                ],
                false,
                false
            );
        } catch (\Exception $exception) {
            throw new Exception($exception->getMessage());
        }

        $brpPersoon = $this->callService->decodeResponse($haalcentraalGateway, $response);
        unset($brpPersoon['_links']);

        return $brpPersoon;

    }//end fetchPersoonsgegevens()

    /**
     * Finds or creates Certificate object
     * 
     * @throws \Exception
     * 
     * @return ObjectEntity $certificateObjectEntity 
     */
    private function getCertificateObject(array $certificate, Entity $certificateEntity): ObjectEntity
    {
        // Find earlier created Certificate
        if (isset($certificate['id'])) {
            $id = is_string($certificate['id']) ? $certificate['id'] : $certificate['id']->toString();
            $certificateObjectEntity = $this->entityManager->getRepository('App:Entity')->find($id);
        }

        // If not created earlier (for dynamic certificate) create new
        if (!isset($certificateObjectEntity) || !$certificateObjectEntity instanceof ObjectEntity && isset($certificateEntity)) {
            $certificateObjectEntity = new ObjectEntity($certificateEntity);
        }

        // If not found and could not be created throw error
        if (!isset($certificateObjectEntity)) {
            throw new Exception('Could not find or create new Certificate object due not having the Certificate entity, check WaardepapierenAction config');
        }

        return $certificateObjectEntity;

    }//end getCertificateObject()

    public function createCertificate(?array $certificate = [], ?string $type = null, ?array $brpPersoon = null, Entity $certificateEntity, ?array $zaak = null): array
    {
        // If we dont have a brpPersoon this is a dynamic certificate
        if ($brpPersoon !== null) {
            $certificate['claimData']['persoon'] = $brpPersoon;
        }

        // If we dont have a type this is a dynamic certificate
        $certificate['claimData']['type'] = $type ?? 'dynamic';

        $certificate = $this->createClaim($certificate);
        $certificate = $this->createImage($certificate);
        $certificate =  $this->createDocument($certificate);

        isset($certificate['personObject']) && $certificate['personObject'] = json_encode($certificate['personObject']);
        $certificate['irma'] = json_encode($certificate['irma']);
        $certificate['discipl'] = json_encode($certificate['discipl']);

        $certificateObjectEntity = $this->getCertificateObject($certificate, $certificateEntity);

        // If we have a zaak set it
        if ($zaak) {
            $certificate['zaak'] = $zaak['_self']['id'];
        }

        $certificateObjectEntity->hydrate($certificate);

        $this->entityManager->persist($certificateObjectEntity);
        $this->entityManager->flush();

        $certificate = $certificateObjectEntity->toArray();

        return $certificate;

    }//end createCertificate()

    private function sendEnkelvoudigInformatieObject($enkelvoudigInformatieObject)
    {
        try {
            $response = $this->callService->call(
                $this->openZaakSource,
                '/enkelvoudiginformatieobjecten',
                'POST',
                [
                    'body' => $enkelvoudigInformatieObject
                ]
            );
        } catch (\Exception $exception) {
            throw new Exception($exception->getMessage());
        }

        return $this->callService->decodeResponse($this->openZaakSource, $response);

    }//end sendEnkelvoudigInformatieObject()

    private function sendObjectInformatieObject($objectInformatieObject)
    {
        try {
            $response = $this->callService->call(
                $this->openZaakSource,
                '/objectinformatieobjecten',
                'POST',
                [
                    'body' => $objectInformatieObject
                ]
            );
        } catch (\Exception $exception) {
            throw new Exception($exception->getMessage());
        }

        return $this->callService->decodeResponse($this->openZaakSource, $response);

    }//end sendObjectInformatieObject()

    private function createInformatieObject()
    {
        $today = new DateTime();
        $enkelvoudigInformatieObject = [
            'bronorganisatie' => 'bsn buren',
            'creatiedatum'    => $today->format('Y-m-d'),
            'titel'           => 'Waardepapier' . $this->certificate['type'],
            'vertrouwelijkheidsaanduiding' => 'vertrouwelijk',
            'auteur'          => 'bsn buren',
            'status'          => 'gearchiveerd',
            'formaat'         => 'application/pdf',
            'taal' => 'nld',
            'versie' => 1,
            'beginRegistratie' => $today->format('Y-m-d'),
            'bestandsnaam' => 'todo',
            'inhoud'       => $this->certificate['pdf'] ?? 'todo',
            'beschrijving' => 'Waardepapier ' . $this->certificate['type'],
            'ontvangstdatum' => $today->format('Y-m-d'),
            'verzenddatum'  => $today->format('Y-m-d'),
            'informatieobjecttype' => '?'
        ];

        $enkelvoudigInformatieObjectResult = $this->sendEnkelvoudigInformatieObject($enkelvoudigInformatieObject);

        // Check is valid
        if ($enkelvoudigInformatieObject) {
        }

        $objectInformatieObject = [
            'informatieobject' => $enkelvoudigInformatieObjectResult['id or uri'],
            'object' => $this->userData['zaakId or uri'],
            'objectType' => 'zaak'
        ];

        $this->sendObjectInformatieObject($objectInformatieObject);

    }//end createInformatieObject()


    /**
     * Validates action config and sets the values to $this
     * 
     * @throws \Exception
     * 
     * @return array Template for certificate
     */
    public function validateConfigAndSetValues(array $whatToValidate): void
    {
        if (array_key_exists('templateGroup', $whatToValidate) && !isset($this->configuration['templateGroup'])) {
            throw new \Exception('TemplateGroup not set, check WaardepapierenAction config');
        } elseif (array_key_exists('templateGroup', $whatToValidate)) {
            $templateGroup = $this->entityManager->find('App:ObjectEntity', $this->configuration['templateGroup']);
        }
        if (array_key_exists('certificateKey', $whatToValidate) && !isset($this->configuration['certificateKey'])) {
            throw new \Exception('Certificate key not found, check WaardepapierenAction config');
        }
        if (array_key_exists('organization', $whatToValidate) && !isset($this->configuration['organization']) && !isset($this->certificate['organization'])) {
            throw new \Exception('Organization not set, check WaardepapierenAction config or give in body');
        } elseif (array_key_exists('organization', $whatToValidate) && isset($this->configuration['organization'])) {
            $this->certificate['organization'] = $this->configuration['organization'];
        }
        if (array_key_exists('authorization', $whatToValidate)) {
            if (!isset($this->configuration['authorization']['certificate'])) {
                throw new \Exception('Auth certificate key not found, check WaardepapierenAction config');
            }
            if (!isset($this->configuration['authorization']['certificateKey'])) {
                throw new \Exception('Auth certificate key not found, check WaardepapierenAction config');
            }
            if (!isset($this->configuration['authorization']['password'])) {
                throw new \Exception('Auth certificate password not found, check WaardepapierenAction config');
            }
        }

        if (!isset($this->certTemplate)) {
            throw new \Exception('No template found, check if template exists for type');
        }

        if (array_key_exists('zaakId', $whatToValidate) && !isset($this->userData['zaakId'])) {
            throw new \Exception('No zaakid given, check body');
        }

    }//end validateConfigAndSetValues()

    public function getTemplate(ObjectEntity $templateGroup, ?string $type = null)
    {
        $templates = $templateGroup->getValue('templates');
        foreach ($templates as $template) {
            if ($template->getValue('description') == $type) {
                $this->certTemplate = $template->toArray();
                break;
            }
        }
        
        isset($templates[0]) && $this->certTemplate = $templates[0]->toArray();
        
    }//end getTemplates()

    public function getHaalcentraalSource()
    {
        if (!isset($this->configuration['source'])) {
            throw new \Exception('Source not set, check WaardepapierenAction config');
        } 

        $haalcentraalSource = $this->entityManager->getRepository('App:Gateway')->findOneBy(['reference' => $this->configuration['source']]);

        if (!$haalcentraalSource instanceof Gateway) {
            throw new \Exception('Source could not found, check if source exists');
        }

        return $haalcentraalSource;
        
    }//end getTemplates()

    public function getCertificateEntity()
    {
        $certificateEntity = $this->entityManager->getRepository('App:Entity')->findOneBy(['reference' => 'https://waardepapieren.commonground.nl/certificate.schema.json']);

        if (!$certificateEntity instanceof Entity) {
            throw new \Exception('Entity could not found, check if https://waardepapieren.commonground.nl/certificate.schema.json exists');
        }

        return $certificateEntity;
        
    }//end getCertificateEntity()

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
        $this->userData = $data['request'];
        $certificate = $data['request'];
        $this->configuration = $configuration;

        // // 1. Check Action configuration and set values
        // $this->validateConfigAndSetValues([
        //     'templateGroup'  => true,
        //     'certificateKey' => true,
        //     'certificate'    => true,
        //     'organization'   => true
        // ]);


        // // 2. Fill certificate with given data 
        // $this->createCertificate();

        return ['response' => $certificate];

    }//end waardepapierenDynamicHandler()

    /**
     * Creates or updates a Certificate.
     *
     * @param array $data          Data from the handler where the xxllnc casetype is in.
     * @param array $configuration Configuration for the Action.
     *
     * @return array $this->certificate Certificate which we updated with new data
     */
    public function waardepapierHandler(array $data, array $configuration): array
    {
        $this->configuration = $configuration;
        $certificate = $data['response'];

        // 1. Check Action configuration and set values
        $haalcentraalSource = $this->getHaalcentraalSource();
        $certificateEntity = $this->getCertificateEntity();

        // @todo old
        // 1. Check Action configuration and set values
        // $this->validateConfigAndSetValues([
        //     'templateGroup'  => true,
        //     'templateType'   => true,
        //     'source'         => true,
        //     'certificateKey' => true,
        //     'authorization'  => true,
        //     'organziation'   => true,
        //     'certificate'    => true   
        // ]);

        // 2. Get persons information from pink haalcentraalGateway 
        $brpPersoon = $this->fetchPersoonsgegevens($haalcentraalSource, $certificate['person']);

        // 3. Fill certificate with persons information
        $certificate = $this->createCertificate($certificate, $certificate['type'] ?? null, $brpPersoon, $certificateEntity);

        // var_dump($this->certificate);

        return ['response' => $certificate];

    }//end waardepapierenHandler()
    
}//end class
