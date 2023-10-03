# CommonGateway\WaardepapierenBundle\Service\WaardepapierService

WaardepapierService creates certificates
WaardepapierService creates certificates by template, given data, or created zgw zaak.

## Methods

| Name | Description |
|------|-------------|
|[\_\_construct](#waardepapierservice__construct)||
|[createCertificate](#waardepapierservicecreatecertificate)||
|[createClaim](#waardepapierservicecreateclaim)|This function creates the claim based on the type defined in the certificate object.|
|[createDocument](#waardepapierservicecreatedocument)|This function creates the (pdf) document for a given certificate type.|
|[createImage](#waardepapierservicecreateimage)|This function creates a QR code for the given claim.|
|[createJWS](#waardepapierservicecreatejws)|This function generates a JWS token with the RS512 algorithm.|
|[createJWT](#waardepapierservicecreatejwt)|This function generates a jwt token using the claim that's available from the certificate object.|
|[createProof](#waardepapierservicecreateproof)|This function creates a proof.|
|[fetchPersoonsgegevens](#waardepapierservicefetchpersoonsgegevens)|This function fetches a haalcentraal persoon with the callService.|
|[getCertificateEntity](#waardepapierservicegetcertificateentity)||
|[getHaalcentraalSource](#waardepapierservicegethaalcentraalsource)||
|[getTemplate](#waardepapierservicegettemplate)||
|[validateConfigAndSetValues](#waardepapierservicevalidateconfigandsetvalues)|Validates action config and sets the values to $this|
|[w3cClaim](#waardepapierservicew3cclaim)|This function generates a claim based on the w3c structure.|
|[waardepapierHandler](#waardepapierservicewaardepapierhandler)|Creates or updates a Certificate.|
|[waardepapierenDynamicHandler](#waardepapierservicewaardepapierendynamichandler)|Creates or updates a dynamic Certificate.|

### WaardepapierService::\_\_construct

**Description**

```php
 __construct (void)
```

**Parameters**

`This function has no parameters.`

**Return Values**

`void`

<hr />

### WaardepapierService::createCertificate

**Description**

```php
 createCertificate (void)
```

**Parameters**

`This function has no parameters.`

**Return Values**

`void`

<hr />

### WaardepapierService::createClaim

**Description**

```php
public createClaim (void)
```

This function creates the claim based on the type defined in the certificate object.

**Parameters**

`This function has no parameters.`

**Return Values**

`void`

**Throws Exceptions**

`\Exception`

<hr />

### WaardepapierService::createDocument

**Description**

```php
public createDocument (array $template)
```

This function creates the (pdf) document for a given certificate type.

**Parameters**

* `(array) $template`
  : The twig template

**Return Values**

`void`

**Throws Exceptions**

`\Twig\Error\LoaderError`

`\Twig\Error\RuntimeError`

`\Twig\Error\SyntaxError`

`\Exception`

<hr />

### WaardepapierService::createImage

**Description**

```php
public createImage (void)
```

This function creates a QR code for the given claim.

**Parameters**

`This function has no parameters.`

**Return Values**

`array`

> The modified certificate object

<hr />

### WaardepapierService::createJWS

**Description**

```php
public createJWS (array $data, array $certificate)
```

This function generates a JWS token with the RS512 algorithm.

**Parameters**

* `(array) $data`
  : the data that gets stored in the jws token
* `(array) $certificate`
  : the certificate object

**Return Values**

`string`

> Generated JWS token.

<hr />

### WaardepapierService::createJWT

**Description**

```php
public createJWT (void)
```

This function generates a jwt token using the claim that's available from the certificate object.

**Parameters**

`This function has no parameters.`

**Return Values**

`string`

> The generated jwt token

<hr />

### WaardepapierService::createProof

**Description**

```php
public createProof (array $data, array $certificate)
```

This function creates a proof.

**Parameters**

* `(array) $data`
  : the data that gets stored in the jws token of the proof
* `(array) $certificate`
  : the certificate object

**Return Values**

`array`

> proof

<hr />

### WaardepapierService::fetchPersoonsgegevens

**Description**

```php
public fetchPersoonsgegevens (void)
```

This function fetches a haalcentraal persoon with the callService.

**Parameters**

`This function has no parameters.`

**Return Values**

`array`

> The modified certificate object

**Throws Exceptions**

`\Exception`

<hr />

### WaardepapierService::getCertificateEntity

**Description**

```php
 getCertificateEntity (void)
```

**Parameters**

`This function has no parameters.`

**Return Values**

`void`

<hr />

### WaardepapierService::getHaalcentraalSource

**Description**

```php
 getHaalcentraalSource (void)
```

**Parameters**

`This function has no parameters.`

**Return Values**

`void`

<hr />

### WaardepapierService::getTemplate

**Description**

```php
 getTemplate (void)
```

**Parameters**

`This function has no parameters.`

**Return Values**

`void`

<hr />

### WaardepapierService::validateConfigAndSetValues

**Description**

```php
public validateConfigAndSetValues (void)
```

Validates action config and sets the values to $this

**Parameters**

`This function has no parameters.`

**Return Values**

`array`

> Template for certificate

**Throws Exceptions**

`\Exception`

<hr />

### WaardepapierService::w3cClaim

**Description**

```php
public w3cClaim (array $data)
```

This function generates a claim based on the w3c structure.

**Parameters**

* `(array) $data`
  : The data used to create the claim

**Return Values**

`array`

> The generated claim

**Throws Exceptions**

`\Exception`

<hr />

### WaardepapierService::waardepapierHandler

**Description**

```php
public waardepapierHandler (array $data, array $configuration)
```

Creates or updates a Certificate.

**Parameters**

* `(array) $data`
  : Data from the handler where the xxllnc casetype is in.
* `(array) $configuration`
  : Configuration for the Action.

**Return Values**

`array`

> $this->certificate Certificate which we updated with new data

<hr />

### WaardepapierService::waardepapierenDynamicHandler

**Description**

```php
public waardepapierenDynamicHandler (array $data, array $configuration)
```

Creates or updates a dynamic Certificate.

**Parameters**

* `(array) $data`
  : Data from the handler where the certificate info is in.
* `(array) $configuration`
  : Configuration for the Action.

**Return Values**

`array`

> $this->certificate Certificate which we updated with new data

<hr />
