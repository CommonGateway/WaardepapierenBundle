<?php

// src/Service/InstallationService.php
namespace CommonGateway\WaardepapierenBundle\Service;

use App\Entity\Action;
use App\Entity\Gateway;
use App\Entity\DashboardCard;
use App\Entity\Endpoint;
use App\Entity\CollectionEntity;
use CommonGateway\CoreBundle\Installer\InstallerInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class InstallationService implements InstallerInterface
{
    private EntityManagerInterface $entityManager;
    private SymfonyStyle $io;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    /**
     * Set symfony style in order to output to the console
     *
     * @param SymfonyStyle $io
     * @return self
     */
    public function setStyle(SymfonyStyle $io): self
    {
        $this->io = $io;

        return $this;
    }

    public function install()
    {
        $this->checkDataConsistency();
    }

    public function update()
    {
        $this->checkDataConsistency();
    }

    public function uninstall()
    {
        // Do some cleanup
    }
    
    /**
     * Creates the kiss Endpoints
     *
     * @return void
     */
    private function createEndpoints(): void
    {
        // OpenZaak webhook endpoint
        // $dynamicCertEndpoint = $endpointRepository->findOneBy(['name' => 'Dynamic certificate']) ?? new Endpoint();
        // $dynamicCertEndpoint->setName('Dynamic certificate');
        // $dynamicCertEndpoint->setDescription('Endpoint for dynamic certificates that creates certificates with given data');
        // $dynamicCertEndpoint->setPathRegex('^(waar/dynamic_certificates)$');
        // $dynamicCertEndpoint->setPath(['waar', 'dynamic_certificates']);
        // $dynamicCertEndpoint->setMethods(["POST", "GET"]);
        // $dynamicCertEndpoint->setMethod("POST");
        // $dynamicCertEndpoint->setOperationType('collection');
        // $dynamicCertEndpoint->setThrows(['commongateway.dynamiccertficate.trigger']);

        // // OpenZaak webhook endpoint
        // $endpoint = $endpointRepository->findOneBy(['name' => 'OpenZaak webhook']) ?? new Endpoint();
        // $endpoint->setName('OpenZaak webhook');
        // $endpoint->setDescription('Endpoint for OpenZaak webhook that has a zaak id');
        // $endpoint->setPathRegex('^(waar/webhook)$');
        // $endpoint->setMethods(["POST"]);
        // $endpoint->setMethod("POST");
        // $endpoint->setOperationType('collection');
        // $endpoint->setThrows(['commongateway.openzaakwebhook.trigger']);
        // $this->entityManager->persist($endpoint);
        // isset($this->io) && $this->io->writeln('Endpoint: \'OpenZaak webhook\' created');
        // $endpoints[] = $endpoint;
    }

    private function createActions()
    {

        // $actionRepository = $this->entityManager->getRepository('App:Action');
        // $action = $actionRepository->findOneBy(['name' => 'WaardepapierenAction']) ?? new Action();
        // $action->setName('WaardepapierenAction');
        // $action->setDescription('This is a action to validate a certificate.');
        // $action->setListens(['commongateway.object.created']);
        // $action->setConditions(['==' => [['var' => 'entity'], $certificateID]]);
        // // $action->setConfiguration(); Must be set with postman
        // $action->setClass('CommonGateway\WaardepapierenBundle\ActionHandler\WaardepapierenHandler');
        // $action->setIsEnabled(true);
        // $this->entityManager->persist($action);
        // isset($this->io) && $this->io->writeln('Action: \'WaardepapierenAction\' created');

        // $action = $actionRepository->findOneBy(['name' => 'WaardepapierenOpenZaakAction']) ?? new Action();
        // $action->setName('WaardepapierenOpenZaakAction');
        // $action->setDescription('This is a action to update a zaak with certificate.');
        // $action->setListens(['commongateway.openzaakwebhook.trigger']);
        // $action->setConditions(['==' => [1, 1]]);
        // // $action->setConfiguration(); Must be set with postman
        // $action->setClass('CommonGateway\WaardepapierenBundle\ActionHandler\WaardepapierenOpenZaakHandler');
        // $action->setIsEnabled(true);
        // $this->entityManager->persist($action);
        // isset($this->io) && $this->io->writeln('Action: \'WaardepapierenOpenZaakAction\' created');

        // $action = $actionRepository->findOneBy(['name' => 'WaardepapierenDynamicAction']) ?? new Action();
        // $action->setName('WaardepapierenDynamicAction');
        // $action->setDescription('This is a action to create a dynamic certificate.');
        // $action->setListens(['commongateway.dynamiccertficate.trigger']);
        // $action->setConditions(['==' => [1, 1]]);
        // $action->setClass('CommonGateway\WaardepapierenBundle\ActionHandler\WaardepapierenDynamicHandler');
        // $action->setIsEnabled(true);
        // $this->entityManager->persist($action);
        // isset($this->io) && $this->io->writeln('Action: \'WaardepapierenDynamicAction\' created');
    }

    // private function createSources()
    // {
    //     $sourceRepository = $this->entityManager->getRepository('App:Gateway');
    //     $gateway = $sourceRepository->findOneBy(['name' => 'Haalcentraal BRP Pink API']) ?? new Gateway();
    //     $gateway->setName('Haalcentraal BRP Pink API');
    //     $gateway->setType('json');
    //     $gateway->setAuth('none');
    //     $gateway->setAccept('application/json');
    //     $gateway->setLocation('https://apitest.locgov.nl/iconnect/apihcbrp/mks/1.3.0');
    //     $this->entityManager->persist($gateway);
    //     isset($this->io) && $this->io->writeln('Source: \'Haalcentraal BRP Pink API\' created');
    // }

    public function checkDataConsistency()
    {

        // // Lets create some genneric dashboard cards
        // $objectsThatShouldHaveCards = ['https://waardepapieren.commonground.nl/certificate.schema.json'];

        // foreach ($objectsThatShouldHaveCards as $object) {
        //     (isset($this->io) ? $this->io->writeln('Looking for a dashboard card for: ' . $object) : '');
        //     $entity = $this->entityManager->getRepository('App:Entity')->findOneBy(['reference' => $object]);
        //     if (
        //         $entity &&
        //         !$dashboardCard = $this->entityManager->getRepository('App:DashboardCard')->findOneBy(['entityId' => $entity->getId()])
        //     ) {
        //         $dashboardCard = new DashboardCard($entity);
        //         (isset($this->io) ? $this->io->writeln('Dashboard card created') : '');
        //         continue;
        //     }
        //     (isset($this->io) ? $this->io->writeln('Dashboard card found') : '');
        // }

        // Let create some endpoints
        // $objectsThatShouldHaveEndpoints = ['https://waardepapieren.commonground.nl/certificate.schema.json'];

        // foreach ($objectsThatShouldHaveEndpoints as $object) {
        //     (isset($this->io) ? $this->io->writeln('Looking for a endpoint for: ' . $object) : '');
        //     $entity = $this->entityManager->getRepository('App:Entity')->findOneBy(['reference' => $object]);

        //     if (
        //         $entity &&
        //         count($entity->getEndpoints()) == 0
        //     ) {
        //         $endpoint = new Endpoint($entity);
        //         $endpoint->setPath(['waar', 'certificates']);
        //         $endpoint->setPathRegex('['waar', 'certificates']');
        //         $this->entityManager->persist($endpoint);
        //         (isset($this->io) ? $this->io->writeln('Endpoint created') : '');
        //         continue;
        //     }
        //     (isset($this->io) ? $this->io->writeln('Endpoint found') : '');
        // }

        // $this->createCollections(); OLD
        // $this->createSources();
        // $this->createEndpoints();
        // $this->createActions();

        // $this->entityManager->flush();

        // Lets see if there is a generic search endpoint


    }
}
