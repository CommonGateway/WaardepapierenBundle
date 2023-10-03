# CommonGateway\WaardepapierenBundle\Service\WPZaakService

WPZaakService makes a certificate with for a zaak

## Methods

| Name | Description |
|------|-------------|
|[\_\_construct](#wpzaakservice__construct)|\_\_construct|
|[wpZaakHandler](#wpzaakservicewpzaakhandler)|Creates a certificate for a ZGW Zaak.|

### WPZaakService::\_\_construct

**Description**

```php
public __construct (void)
```

\_\_construct

**Parameters**

`This function has no parameters.`

**Return Values**

`void`

<hr />

### WPZaakService::wpZaakHandler

**Description**

```php
public wpZaakHandler (array $data, array $configuration)
```

Creates a certificate for a ZGW Zaak.

**Parameters**

* `(array) $data`
  : Data from the handler where the xxllnc casetype is in.
* `(array) $configuration`
  : Configuration for the Action.

**Return Values**

`array`

> $this->certificate Certificate which we updated with new data

<hr />
