<?php

namespace CommonGateway\WaardepapierenBundle\Service;

use Doctrine\ORM\EntityManagerInterface;
use CommonGateway\WaardepapierenBundle\Service\WaardepapierService;
use App\Entity\ObjectEntity;
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
     * Gets the ZGW Zaak waardepapier template
     * 
     * @return ObjectEntity|null ZGW Zaak waardepapier template
     */
    private function getZaakTemplate(): ?ObjectEntity
    {
        // @todo Get bsn from zaak
        if (isset($this->configuration['templateId']) === false) {
            // $this->logger->error('No templateId found in Action config, failed to create certificate')

            return null;
        }

        return $this->entityManager->getRepository('App:ObjectEntity')->find($this->configuration['templateId']);

    }//end getZaakTemplate()

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
        $this->waardepapierService->configuration = $configuration;
        $certificate = [];
        dump('get bsn');

        // 1. Get BSN from Zaak.
        $bsn = $this->getBSN($zaak);
        if ($bsn === null) {
            return $data;
        }

        // 2. Get RSIN organisatie from Zaak
        $certificate['organization'] =  $this->getRSIN($zaak);
        if ($certificate['organization'] === null) {
            return $data;
        }

        // 2. Get zaak waardepapier template.
        dump('get getZaakTemplate');
        $template = $this->getZaakTemplate();
        if ($template === null) {
            return $data;
        }

        dump('get getHaalcentraalSource');
        // 3. Check configuration and necessary gateway objects
        $this->waardepapierService->haalcentraalSource = $this->waardepapierService->getHaalcentraalSource();
        if ($this->waardepapierService->haalcentraalSource === null) {
            return $data;

        }
        dump('get getCertificateEntity');
        $certificateEntity  = $this->waardepapierService->getCertificateEntity();
        if ($certificateEntity === null) {
            return $data;
        }

        dump('get fetchPersoonsgegevens');
        // 4. Get persons information from pink haalcentraalGateway
        $brpPersoon = $this->waardepapierService->fetchPersoonsgegevens($bsn);

        // 5. Fill certificate with persons information and/or zaak
        $this->waardepapierService->certTemplate = $template->toArray();
        $certificate = $this->waardepapierService->createCertificate($certificate, 'zaak', $brpPersoon, $certificateEntity, $zaak);

        dump($certificate['document']);
        return ['response' => $certificate];

    }//end wpZaakHandler()


}//end class
