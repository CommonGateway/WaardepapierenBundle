# CommonGateway\WaardepapierenBundle\Service\ZaakNotificationService

ZaakNotificationService makes a certificate with for a zaak

## Methods

| Name | Description |
|------|-------------|
|[\_\_construct](#wpzaakservice__construct)|\_\_construct|
|[zaakNotificationHandler](#wpzaakservicewpzaakhandler)|Creates a certificate for a ZGW Zaak.|

### ZaakNotificationService::\_\_construct

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

### ZaakNotificationService::zaakNotificationHandler

**Description**

```php
public zaakNotificationHandler (array $data, array $configuration)
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
