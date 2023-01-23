<?php

namespace CommonGateway\WaardepapierenBundle\ActionHandler;

use CommonGateway\CoreBundle\ActionHandler\ActionHandlerInterface;
use CommonGateway\WaardepapierenBundle\Service\WaardepapierenService;

class WaardepapierenOpenZaakHandler implements ActionHandlerInterface
{
    private WaardepapierenService $waardepapierenService;

    public function __construct(WaardepapierenService $waardepapierenService)
    {
        $this->waardepapierenService = $waardepapierenService;
    }

    /**
     *  This function returns the requered configuration as a [json-schema](https://json-schema.org/) array.
     *
     * @throws array a [json-schema](https://json-schema.org/) that this  action should comply to
     */
    public function getConfiguration(): array
    {
        return [
            '$id'         => 'https://example.com/person.schema.json',
            '$schema'     => 'https://json-schema.org/draft/2020-12/schema',
            'title'       => 'Waardepapieren Action',
            'description' => 'This handler returns a welcoming string',
            'required'    => [],
            'properties'  => [],
        ];
    }

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
        return $this->waardepapierenService->waardepapierenOpenZaakHandler($data, $configuration);
    }
}
