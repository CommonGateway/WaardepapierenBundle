<?php

// src/Service/InstallationService.php
namespace CommonGateway\WaardepapierenBundle\Service;

use CommonGateway\CoreBundle\Installer\InstallerInterface;

class InstallationService implements InstallerInterface
{

    public function __construct()
    {
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

    public function checkDataConsistency()
    {
    }
}
