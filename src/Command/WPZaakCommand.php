<?php

namespace CommonGateway\WaardepapierenBundle\Command;

use CommonGateway\WaardepapierenBundle\Service\WPZaakService;
use Doctrine\ORM\EntityManagerInterface;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * This class handles the command for the creation of a certificate for a ZGW Zaak.
 *
 * This Command executes the WPZaakService->wpZaakHandler.
 *
 * @author Barry Brands <barry@conduction.nl>
 *
 * @package  common-gateway/waardepapieren-bundle
 * @category Command
 */
class WPZaakCommand extends Command
{

    /**
     * @var static $defaultName The actual command
     */
    protected static $defaultName = 'waardepapieren:certificate:zaak';

    /**
     * @var EntityManagerInterface
     */
    private EntityManagerInterface $entityManager;

    /**
     * @var WPZaakService
     */
    private WPZaakService $wpZaakService;


    /**
     * __construct
     */
    public function __construct(WPZaakService $wpZaakService, EntityManagerInterface $entityManager)
    {
        $this->wpZaakService = $wpZaakService;
        $this->entityManager = $entityManager;
        parent::__construct();

    }//end __construct()


    /**
     * Configures this command
     *
     * @return void
     */
    protected function configure(): void
    {
        $this
            ->setDescription('This command triggers WPZaakService')
            ->setHelp('This command triggers WPZaakService')
            ->addArgument('id', InputArgument::REQUIRED, 'Zaak ID');

    }//end configure()


    /**
     * Executes this command
     *
     * @param InputInterface  Handles input from cli
     * @param OutputInterface Handles output from cli
     *
     * @return int 0 for failure, 1 for success
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $style  = new SymfonyStyle($input, $output);
        $zaakId = $input->getArgument('id');

        $style->info('Getting the WPZaakAction for config');
        $wpZaakAction = $this->entityManager->getRepository('App:Action')->findOneBy(['reference' => 'https://waardepapieren.commonground.nl/action/waar.WaardepapierenZaakAction.action.json']);
        if ($wpZaakAction === null) {
            $style->error('WPZaakAction not found');
            return Command::FAILURE;
        }

        if (Uuid::isValid($zaakId) === false) {
            $style->error('Given zaak id not valid uuid');
            return Command::FAILURE;
        }

        $zaakObject = $this->entityManager->getRepository('App:ObjectEntity')->find($zaakId);
        if ($zaakObject === null) {
            $style->error('Zaak not found with given id');
            return Command::FAILURE;
        }

        $style->info('Creating certificate with WPZaakService..');
        if (is_array($this->wpZaakService->wpZaakHandler(['response' => $zaakObject->toArray()], $wpZaakAction->getConfiguration())) === true) {
            $style->success('Succesfully created certificate for zaak: '.$zaakId);
            return Command::SUCCESS;
        }//end if

        $style->error('Creating certificate went wrong');
        return Command::FAILURE;

    }//end execute()


}//end class
