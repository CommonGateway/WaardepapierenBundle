<?php

// src/Service/InstallationService.php
namespace CommonGateway\WaardepapierenBundle\Service;

use CommonGateway\CoreBundle\Installer\InstallerInterface;

/**
 * InstallationService
 * InstallationService makes sure all custom things are configured, but currently does nothing.
 * 
 * @author Barry Brands barry@conduction.nl 
 * @package common-gateway/waardepapieren-bundle 
 * @category Service
 * @access public  
 */
class InstallationService implements InstallerInterface
{

    public function __construct()
    {
    }//end __construct()

    public function install()
    {
        $this->checkDataConsistency();

    }//end install()

    public function update()
    {
        $this->checkDataConsistency();

    }//end update()

    public function uninstall()
    {
        // Do some cleanup

    }//end uninstall()

    public function checkDataConsistency()
    {
    }//end checkDataConsistency()

}//end class
