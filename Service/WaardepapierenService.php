<?php

namespace CommonGateway\WaardepapierenBundle\Service;

use App\Entity\Entity;
use App\Entity\ObjectEntity;
use Doctrine\ORM\EntityManagerInterface;
// use Endroid\QrCode\QrCode;
// use Endroid\QrCodeBundle\Response\QrCodeResponse;
use Dompdf\Dompdf;
use Jose\Component\Core\AlgorithmManager;
use Jose\Component\KeyManagement\JWKFactory;
use Jose\Component\Signature\Algorithm\RS512;
use Jose\Component\Signature\Serializer\CompactSerializer;
use Twig\Environment as Twig;
use App\Entity\Gateway;
use App\Service\ObjectEntityService;

class WaardepapierenService
{
    private EntityManagerInterface $entityManager;
    // private TranslationService $translationService;
    private ObjectEntityService $objectEntityService;
    private array $configuration;
    private array $data;
    private $twig;
    // private QrCode $qrCode;

    /**
     * @param EntityManagerInterface $entityManager
     */
    public function __construct(
        EntityManagerInterface $entityManager,
        ObjectEntityService $objectEntityService,
        Twig $twig
        // QrCode $qrCode
    ) {
        $this->entityManager = $entityManager;
        $this->objectEntityService = $objectEntityService;
        $this->twig = $twig;
        // $this->qrCode = $qrCode;

        $this->objectEntityRepo = $this->entityManager->getRepository(ObjectEntity::class);
        $this->entityRepo = $this->entityManager->getRepository(Entity::class);
    }

    // /**
    //  * This function creates a QR code for the given claim.
    //  *
    //  * @param array $certificate The certificate object
    //  *
    //  * @return array The modified certificate object
    //  */
    // public function createImage(array $certificate = [])
    // {
    //     // TODO testing, might not work

    //     // Then we need to render the QR code
    //     $qrCode = $this->qrCode->create($certificate['jwt'], [
    //         'size'  => 1000,
    //         'margin' => 1,
    //         'writer' => 'png',
    //     ]);
    //     // $response = new QrCodeResponse($qrCode);

    //     // And finnaly we need to set the result on the certificate resource
    //     $certificate['image'] = 'data:image/png;base64,' . base64_encode($qrCode);

    //     return $certificate;
    // }

    /**
     * This function generates a claim based on the w3c structure.
     *
     * @param array       $data        The data used to create the claim
     * @param array $certificate The certificate object
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
        $array['id'] = $certificate['id'];
        $array['type'] = ['VerifiableCredential', $certificate['type']];
        $array['issuer'] = $certificate['organization'];
        $array['inssuanceDate'] = $now->format('H:i:s d-m-Y');
        $array['credentialSubject']['id'] = $certificate['personObject']['burgerservicenummer'] ?? $certificate['organization'];
        foreach ($data as $key => $value) {
            $array['credentialSubject'][$key] = $value;
        }
        $array['proof'] = $this->createProof($certificate, $array);

        return $array;
    }


    /**
     * This function creates a proof.
     *
     * @param array $certificate the certificate object
     * @param array       $data        the data that gets stored in the jws token of the proof
     *
     * @return array proof
     */
    public function createProof(array $certificate, array $data)
    {
        $proof = [];
        $proof['type'] = 'RsaSignature';
        // $proof['created'] = date('H:i:s d-m-Y', filectime("cert/{" . $certificate['organization'] . "}.pem"));
        $proof['proofPurpose'] = 'assertionMethode';
        // $proof['verificationMethod'] = $this->requestStack->getCurrentRequest()->getSchemeAndHttpHost() . "/cert/{" . $certificate['organization'] . "}.pem";
        // $proof['jws'] = $this->createJWS($certificate, $data['credentialSubject']);

        return $proof;
    }

    /**
     * This function generates a JWS token with the RS512 algorithm.
     *
     * @param array $certificate the certificate object
     * @param array       $data        the data that gets stored in the jws token
     *
     * @return string Generated JWS token.
     */
    public function createJWS(array $certificate, array $data)
    {
        $algorithmManager = new AlgorithmManager([
            new RS512(),
        ]);
        $jwk = JWKFactory::createFromKeyFile(
            "../cert/{" . $certificate['organization'] . "}.pem"
        );
        $jwsBuilder = new \Jose\Component\Signature\JWSBuilder($algorithmManager);
        $payload = json_encode([
            'iat'  => time(),
            'nbf'  => time(),
            'exp'  => time() + 3600,
            // 'crt'  => $this->commonGroundService->cleanUrl(['component' => 'frontend', 'type' => 'claims/public_keys', 'id' => $certificate['organization']]),
            'iss'  => $certificate['id'],
            'aud'  => $certificate['personObject']['burgerservicenummer'] ?? $certificate['organizaiton'],
            'data' => $data,
        ]);
        $jws = $jwsBuilder
            ->create()
            ->withPayload($payload)
            ->addSignature($jwk, ['alg' => 'RS512'])
            ->build();
        $serializer = new CompactSerializer();

        return $serializer->serialize($jws, 0);
    }

    /**
     * This function creates the (pdf) document for a given certificate type.
     *
     * @param array $certificate The certificate object
     *
     * @throws \Twig\Error\LoaderError
     * @throws \Twig\Error\RuntimeError
     * @throws \Twig\Error\SyntaxError
     *
     * @return array The modified certificate object
     */
    public function createDocument(array $certificate)
    {
        $data = [
            'qr'     => $certificate['image'],
            'claim'  => $certificate['claim'],
            'person' => $certificate['personObject'] ?? null,
            'base'   => '/organizations/' . $certificate['organization'] . '.html.twig',
        ];

        // if ($certificate['type'] == 'historisch_uittreksel_basis_registratie_personen') {
        //     $data['verblijfplaatshistorie'] = $this->commonGroundService->getResourceList(['component' => 'brp', 'type' => 'ingeschrevenpersonen', 'id' => $certificate->getPersonObject()['burgerservicenummer'].'/verblijfplaatshistorie'])['_embedded']['verblijfplaatshistorie'];
        // }

        // First we need the HTML  for the template
        // $html = $this->twig->render('certificates/' . $certificate->getType() . '.html.twig', array_filter($data));
        $html = json_encode($data);

        // Then we need to render the template
        $dompdf = new DOMPDF();
        $dompdf->loadHtml($html);
        $dompdf->render();

        // And finnaly we need to set the result on the certificate resource
        $certificate['document'] = 'data:application/pdf;base64,' . base64_encode($dompdf->output());

        return $certificate;
    }

    /**
     * This function generates a jwt token using the claim that's available from the certificate object.
     *
     * @param array $certificate The certificate object
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
        $jwk = JWKFactory::createFromKeyFile(
            "../cert/{" . $certificate['organization'] . "}.pem"
        );
        $jwsBuilder = new \Jose\Component\Signature\JWSBuilder($algorithmManager);
        $jws = $jwsBuilder
            ->create()
            ->withPayload(json_encode($payload))
            ->addSignature($jwk, ['alg' => 'RS512'])
            ->build();
        $serializer = new CompactSerializer();

        return $serializer->serialize($jws, 0);
    }

    /**
     * This function creates the claim based on the type defined in the certificate object.
     *
     * @param array $certificate The certificate object
     *
     * @throws \Exception
     *
     * @return array The modified certificate object
     */
    public function createClaim(array $certificate)
    {
        // Lets add data to this claim
        isset($certificate['claimData']) ? $claimData = $certificate['claimData'] : [];

        isset($certificate['data']) && $claimData = $certificate['data'];

        // switch ($certificate['type']) {
        // }

        $certificate['w3c'] = $this->w3cClaim($claimData, $certificate);
        isset($certificate['person']) && $claimData['persoon'] = $certificate['personObject']['burgerservicenummer'];

        $claimData['doel'] = $certificate['type'];
        $certificate['claimData'] = $claimData;

        // Create token payload as a JSON string
        $certificate['claim'] = [
            'iss'                 => $certificate['id'],
            'user_id'             => $certificate['personObject']['id'] ?? $certificate['organization'],
            'user_representation' => $certificate['personObject']['@id'] ?? $certificate['organization'],
            'claim_data'          => $certificate['claimData'],
            // 'validation_uri'      => $this->commonGroundService->cleanUrl(['component' => 'frontend', 'type' => 'claims/public_keys', 'id' => $certificate->getOrganization()]),
            'iat'                 => time(),
        ];

        // Create token payload as a JSON string
        $certificate['discipl'] = [
            'claimData' => [
                'did:discipl:ephemeral:crt:4c86faf535029c8cf4a371813cc44cb434875b18' => [
                    'link:discipl:ephemeral:tEi6K3mPRmE6QRf4WvpxY1hQgGmIG7uDV85zQILQNSCnQjAZPg2mj4Fbok/BHL9C8mFJQ1tCswBHBtsu6NIESA45XnN13pE+nLD6IPOeHx2cUrObxtzsqLhAy4ZXN6eDpZDmqnb6ymELUfXu/D2n4rL/t9aD279vqjFRKgBVE5WsId9c6KEYA+76mBQUBoJr8sF7w+3oMjzKy88oW693I3Keu+cdl/9sRCyYAYIDzwmg3A6n8t9KUpsBDK1b6tNznA6qoiN9Zb4JZ7rpq6lnVpyU5pyJjD+p9DiWgIYsVauJy8WOcKfNWkeOomWez0of2o+gu9xf+VLzcX3MSiAfZA==' => $certificate['claimData'],
                ],
            ],
            'metadata' => ['cert' => 'localhost:8080'],
        ];

        // Create token payload as a JSON string
        $certificate['irma'] = $certificate['discipl'];

        // $certificate['jwt'] = $this->createJWT($certificate);;

        return $certificate;
    }

    /**
     * Creates or updates a Certificate.
     *
     * @param array $data          Data from the handler where the xxllnc casetype is in.
     * @param array $configuration Configuration from the Action where the ZaakType entity id is stored in.
     *
     * @return array $certificate Certificate which we updated with new data
     */
    public function waardepapierenHandler(array $data, array $configuration): array
    {
        $certificate = $data['response'];
        $this->configuration = $configuration;

        $pinkBRPGateway = $this->entityManager->find('App:Gateway', $configuration['source']);

        if (!$pinkBRPGateway instanceof Gateway) {
            throw new \Exception('PinkBRP gateway could not be found');
        }

        // 1. Haal persoonsgegevens op bij pink brp 
        // get persoonsgegevens with $pinkBRPGateway

        // Test object
        $certificate['person'] = 'http://localhost/api/ingeschrevenpersonen/1234567';
        $certificate['personObject'] = [
            'id'                     => '1234567',
            '@id'                    => 'http://localhost/api/ingeschrevenpersonen/1234567',
            'burgerservicenummer'    => '1234567',
            'naam' => 'Barry Brands'
        ];

        // 2. Vul data van certificate in
        $certificate['claimData']['persoon'] = $certificate['person'];
        $certificate['claimData']['type'] = $certificate['type'];
        $certificate = $this->createClaim($certificate);
        // $certificate = $this->createImage($certificate);
        $certificate = $this->createDocument($certificate);

        $certificate['personObject'] = json_encode($certificate['personObject']);
        $certificate['irma'] = json_encode($certificate['irma']);
        $certificate['discipl'] = json_encode($certificate['discipl']);

        $certificateObjectEntity = $this->objectEntityRepo->find($certificate['id']);
        $certificateObjectEntity->hydrate($certificate);
        $this->entityManager->persist($certificateObjectEntity);
        $this->entityManager->flush();

        $certificate = $certificateObjectEntity->toArray();

        return ['response' => $certificate];
    }
}
