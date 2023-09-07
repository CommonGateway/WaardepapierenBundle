<?php

namespace CommonGateway\WaardepapierenBundle\ActionHandler;

use CommonGateway\CoreBundle\ActionHandler\ActionHandlerInterface;
use CommonGateway\WaardepapierenBundle\Service\WaardepapierService;

/**
 * WaardepapierenDynamicHandler
 *
 * @author   Barry Brands barry@conduction.nl
 * @package  common-gateway/waardepapieren-bundle
 * @category ActionHandler
 * @access   public
 */
class WaardepapierenDynamicHandler implements ActionHandlerInterface
{

    private WaardepapierService $waardepapierService;


    public function __construct(WaardepapierService $waardepapierService)
    {
        $this->waardepapierService = $waardepapierService;

    }//end __construct()


    /**
     *  This function returns the requered configuration as a [json-schema](https://json-schema.org/) array.
     *
     * @throws array a [json-schema](https://json-schema.org/) that this  action should comply to
     */
    public function getConfiguration(): array
    {
        return [
            '$id'         => 'https://waardepapieren.commonground.nl/person.schema.json',
            '$schema'     => 'https://docs.commongateway.nl/schemas/ActionHandler.schema.json',
            'title'       => 'Waardepapieren Action',
            'description' => 'This handler returns a welcoming string',
            'required'    => [],
            'properties'  => [],
        ];

    }//end getConfiguration()


    /**
     * This function runs the service.
     *
     * @param array $data          The data from the call
     * @param array $configuration The configuration of the action
     *
     * @throws GatewayException
     * @throws CacheException
     * @throws InvalidArgumentException
     * @throws ComponentException
     *
     * @return array
     */
    public function run(array $data, array $configuration): array
    {
        return $this->waardepapierService->waardepapierDynamicHandler($data, $configuration);

    }//end run()


}//end class
