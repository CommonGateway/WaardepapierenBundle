<?php

// src/Service/WaardepapierenService.php

namespace CommonGateway\WaardepapierenBundle\Service;

class WaardepapierenService
{

    /*
     * Returns a welcoming string
     * 
     * @return array 
     */
    public function test(array $data, array $configuration): array
    {
        return ['response' => 'Hello. Your WaardepapierenBundle works'];
    }
}
