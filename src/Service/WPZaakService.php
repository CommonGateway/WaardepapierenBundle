<?php

namespace CommonGateway\WaardepapierenBundle\Service;

use App\Entity\ObjectEntity;
use Doctrine\ORM\EntityManagerInterface;
use CommonGateway\WaardepapierenBundle\Service\WaardepapierService;

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
        WaardepapierService $waardepapierService
    ) {
        $this->entityManager       = $entityManager;
        $this->waardepapierService = $waardepapierService;

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
        if (isset($zaak['rollen']) === false) {
            // $this->logger->error('No BSN found for Zaak, failed to create certificate')
            return null;
        }

        foreach ($zaak['rollen'] as $rol) {
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

        $responseContent = $this->data['response']->getContent();
        $zaak            = \Safe\json_decode($responseContent, true);

        var_dump($zaak);
        $zaakObject = $this->entityManager->getRepository("App:ObjectEntity")->find($zaak['id']);
        if ($zaakObject instanceof ObjectEntity === false) {
            return $this->data;
        }

        // 1. Get BSN from Zaak.
        $bsn = $this->getBSN($zaak);
        if ($bsn === null) {
            return $this->data;
        }

        var_dump($bsn);

        // 2. Get RSIN organisatie from Zaak
        $certificate['organization'] = $this->getRSIN($zaak);
        if ($certificate['organization'] === null) {
            return $this->data;
        }

        // 3. Get persons information from pink haalcentraalGateway
        $brpPersoon = $this->waardepapierService->fetchPersoonsgegevens($bsn);

        // 5. Fill certificate with persons information and/or zaak
        $certificate = $this->waardepapierService->createCertificate($certificate, 'zaak', $brpPersoon, $zaak);

        return ['response' => $certificate];

    }//end wpZaakHandler()


}//end class
