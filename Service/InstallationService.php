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

    private function createCollections()
    {
        if (!$collection = $this->entityManager->getRepository('App:CollectionEntity')->findOneBy(['name' => 'Waardepapieren'])) {
            $collection = new CollectionEntity();
            $collection->setName('Waardepapieren');
            $collection->setAutoLoad(true);
            $collection->setLoadTestData(true);
            $collection->setSourceType('GitHub');
            $collection->setPrefix('waar');
            $collection->setLocationOAS('https://raw.githubusercontent.com/CommonGateway/WaardepapierenAPI/main/OAS.yaml');
            $this->entityManager->persist($collection);
            isset($this->io) && $this->io->writeln('Collection: \'Waardepapieren\' created');
        }
        if (!$collection = $this->entityManager->getRepository('App:CollectionEntity')->findOneBy(['name' => 'Template'])) {
            $collection = new CollectionEntity();
            $collection->setName('Template');
            $collection->setAutoLoad(true);
            $collection->setLoadTestData(true);
            $collection->setSourceType('GitHub');
            $collection->setPrefix('waar');
            $collection->setLocationOAS('https://raw.githubusercontent.com/CommonGateway/TemplateAPI/main/OAS.yaml');
            $collection->setTestDataLocation('https://raw.githubusercontent.com/CommonGateway/TemplateAPI/main/data/waardepapieren.json');
            $this->entityManager->persist($collection);
            isset($this->io) && $this->io->writeln('Collection: \'Template\' created');
        }
    }

    private function createEndpoints()
    {
        if (!$endpoint = $this->entityManager->getRepository('App:Endpoint')->findOneBy(['name' => 'Dynamic certificate'])) {
            $endpoint = new Endpoint();
            $endpoint->setName('Dynamic certificate');
            $endpoint->setDescription('Endpoint for dynamic certificates that use the request body as data');
            $endpoint->setPathRegex('^(waar/dynamic_certificates)$');
            $endpoint->setMethods(["POST"]);
            $endpoint->setMethod("POST");
            $endpoint->setOperationType('collection');
            $endpoint->setThrows(['commongateway.dynamiccertficate.trigger']);
            $this->entityManager->persist($endpoint);
            isset($this->io) && $this->io->writeln('Endpoint: \'Dynamic certificate\' created');
        }
        if (!$endpoint = $this->entityManager->getRepository('App:Endpoint')->findOneBy(['name' => 'OpenZaak webhook'])) {
            $endpoint = new Endpoint();
            $endpoint->setName('OpenZaak webhook');
            $endpoint->setDescription('Endpoint for OpenZaak webhook that has a zaak id');
            $endpoint->setPathRegex('^(waar/webhook)$');
            $endpoint->setMethods(["POST"]);
            $endpoint->setMethod("POST");
            $endpoint->setOperationType('collection');
            $endpoint->setThrows(['commongateway.openzaakwebhook.trigger']);
            $this->entityManager->persist($endpoint);
            isset($this->io) && $this->io->writeln('Endpoint: \'OpenZaak webhook\' created');
        }
    }

    private function createActions()
    {
        if (!$action = $this->entityManager->getRepository('App:Action')->findOneBy(['name' => 'WaardepapierenAction'])) {
            $action = new Action();
            $action->setName('WaardepapierenAction');
            $action->setDescription('This is a action to validate a certificate.');
            $action->setListens(['commongateway.response.pre']);
            $action->setConditions(['==' => [1, 1]]);
            $action->setClass('CommonGateway\WaardepapierenBundle\ActionHandler\WaardepapierenHandler');
            $action->setPriority(0);
            $action->setAsync(false);
            $action->setIsEnabled(true);
            $this->entityManager->persist($action);
            isset($this->io) && $this->io->writeln('Action: \'WaardepapierenAction\' created');
        }
        if (!$action = $this->entityManager->getRepository('App:Action')->findOneBy(['name' => 'WaardepapierenOpenZaakAction'])) {
            $action = new Action();
            $action->setName('WaardepapierenOpenZaakAction');
            $action->setDescription('This is a action to update a zaak with certificate.');
            $action->setListens(['commongateway.openzaakwebhook.trigger']);
            $action->setConditions(['==' => [1, 1]]);
            $action->setClass('CommonGateway\WaardepapierenBundle\ActionHandler\WaardepapierenOpenZaakHandler');
            $action->setPriority(0);
            $action->setAsync(false);
            $action->setIsEnabled(true);
            $this->entityManager->persist($action);
            isset($this->io) && $this->io->writeln('Action: \'WaardepapierenOpenZaakAction\' created');
        }
        if (!$action = $this->entityManager->getRepository('App:Action')->findOneBy(['name' => 'WaardepapierenDynamicAction'])) {
            $action = new Action();
            $action->setName('WaardepapierenDynamicAction');
            $action->setDescription('This is a action to create a dynamic certificate.');
            $action->setListens(['commongateway.dynamiccertficate.trigger']);
            $action->setConditions(['==' => [1, 1]]);
            $action->setClass('CommonGateway\WaardepapierenBundle\ActionHandler\WaardepapierenDynamicHandler');
            $action->setPriority(0);
            $action->setAsync(false);
            $action->setIsEnabled(true);
            $this->entityManager->persist($action);
            isset($this->io) && $this->io->writeln('Action: \'WaardepapierenDynamicAction\' created');
        }
    }

    private function createSources()
    {

        if (!$gateway = $this->entityManager->getRepository('App:Gateway')->findOneBy(['name' => 'Haalcentraal BRP Pink API'])) {
            $gateway = new Gateway();
            $gateway->setName('Haalcentraal BRP Pink API');
            $gateway->setType('json');
            $gateway->setAuth('none');
            $gateway->setAccept('application/json');
            $gateway->setLocation('');
            $this->entityManager->persist($gateway);
            isset($this->io) && $this->io->writeln('Source: \'Haalcentraal BRP Pink API\' created');
        }
    }

    public function checkDataConsistency()
    {

        // Lets create some genneric dashboard cards
        $objectsThatShouldHaveCards = ['https://waardepapieren.commonground.nl/certificate.schema.json'];

        foreach ($objectsThatShouldHaveCards as $object) {
            (isset($this->io) ? $this->io->writeln('Looking for a dashboard card for: ' . $object) : '');
            $entity = $this->entityManager->getRepository('App:Entity')->findOneBy(['reference' => $object]);
            if (
                $entity &&
                !$dashboardCard = $this->entityManager->getRepository('App:DashboardCard')->findOneBy(['entityId' => $entity->getId()])
            ) {
                $dashboardCard = new DashboardCard();
                $dashboardCard->setType('schema');
                $dashboardCard->setEntity('App:Entity');
                $dashboardCard->setObject('App:Entity');
                $dashboardCard->setName($entity->getName());
                $dashboardCard->setDescription($entity->getDescription());
                $dashboardCard->setEntityId($entity->getId());
                $dashboardCard->setOrdering(1);
                $this->entityManager->persist($dashboardCard);
                (isset($this->io) ? $this->io->writeln('Dashboard card created') : '');
                continue;
            }
            (isset($this->io) ? $this->io->writeln('Dashboard card found') : '');
        }

        // Let create some endpoints
        $objectsThatShouldHaveEndpoints = ['https://waardepapieren.commonground.nl/certificate.schema.json'];

        foreach ($objectsThatShouldHaveEndpoints as $object) {
            (isset($this->io) ? $this->io->writeln('Looking for a endpoint for: ' . $object) : '');
            $entity = $this->entityManager->getRepository('App:Entity')->findOneBy(['reference' => $object]);

            if (
                $entity &&
                count($entity->getEndpoints()) == 0
            ) {
                $endpoint = new Endpoint($entity);
                $this->entityManager->persist($endpoint);
                (isset($this->io) ? $this->io->writeln('Endpoint created') : '');
                continue;
            }
            (isset($this->io) ? $this->io->writeln('Endpoint found') : '');
        }

        // $this->createCollections();
        // $this->createSources();
        // $this->createEndpoints();
        // $this->createActions();

        $this->entityManager->flush();

        // Lets see if there is a generic search endpoint


    }
}
