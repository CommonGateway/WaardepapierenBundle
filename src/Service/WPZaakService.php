<?php

namespace CommonGateway\WaardepapierenBundle\Service;

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
     * Creates a certificate for a ZGW Zaak.
     *
     * @param array $data          Data from the handler where the xxllnc casetype is in.
     * @param array $configuration Configuration for the Action.
     *
     * @return array $this->certificate Certificate which we updated with new data
     */
    public function wpZaakHandler(array $data, array $configuration): array
    {
        $zaak = $data['response'];
        $this->configuration = $configuration;

        // @todo Get bsn from zaak
        // $bsn = $zaak['']
        // 1. Check Action configuration and set values
        $haalcentraalSource = $this->waardepapierService->getHaalcentraalSource();
        $certificateEntity  = $this->waardepapierService->getCertificateEntity();

        // 2. Get persons information from pink haalcentraalGateway
        $brpPersoon = $this->waardepapierService->fetchPersoonsgegevens($haalcentraalSource, $bsn);

        // 3. Fill certificate with persons information
        $certificate = $this->waardepapierService->createCertificate([], 'zaak', $brpPersoon, $certificateEntity, $zaak);

        // var_dump($this->certificate);
        return ['response' => $certificate];

    }//end wpZaakHandler()


}//end class
