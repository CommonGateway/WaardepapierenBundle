# Waardepapieren Service README

## Architectuur

De Waardepapieren Service is in 2023 getransformeerd van een standalone applicatie naar een microservice die complementair is aan een zaakregistratiesysteem. Deze transformatie maakt het mogelijk om de service naadloos te integreren met bestaande zaaksystemen binnen gemeentelijke organisaties.

![Microserviced Architecture](https://raw.githubusercontent.com/CommonGateway/WaardepapierenBundle/main/docs/microservice.svg)

### Referentie

* [Microservices Architectuur](https://www.noraonline.nl/wiki/Microservices)

## Varianten

Afhankelijk van de opzet van het zaaksysteem binnen de organisatie die waardepapieren wil creëren, zijn er verschillende varianten (of combinaties daarvan) mogelijk.

### ZGW (Zaakgericht Werken)

Deze variant is specifiek ontworpen om te integreren met ZGW-gebaseerde zaaksystemen. Het maakt gebruik van de ZGW API's voor een naadloze interactie.

![ZGW  Architecture](https://raw.githubusercontent.com/CommonGateway/WaardepapierenBundle/main/docs/zgw_waardepapier_klein.svg)

#### Referentie

* [ZGW API's](https://www.vngrealisatie.nl/producten/api-standaarden-zaakgericht-werken)

### Mijn Omgeving

Deze variant is bedoeld voor integratie met persoonlijke omgevingen waar burgers hun zaken kunnen inzien en beheren.

![Mijn Omgeving  Architecture](https://raw.githubusercontent.com/CommonGateway/WaardepapierenBundle/main/docs/zgw_waardepapier_mijn-zaken.svg)

#### Referentie

* [MijnOverheid](https://www.mijnoverheid.nl/)

### Notify

Deze variant maakt gebruik van notificaties om gebruikers en systemen op de hoogte te stellen van de status van waardepapieren.

![Notify  Architecture](https://raw.githubusercontent.com/CommonGateway/WaardepapierenBundle/main/docs/zgw_waardepapier_notify.svg)

#### Referentie

* [Notificatie API's](https://www.vngrealisatie.nl/producten/api-standaard-notificaties)

### StuF (Standaard Uitwisselings Formaat)

Deze variant is ontworpen voor systemen die gebruik maken van het StuF-protocol voor gegevensuitwisseling.

![Stuf  Architecture](https://raw.githubusercontent.com/CommonGateway/WaardepapierenBundle/main/docs/stuf_waardepapier.svg)

#### Referentie

* [StuF](https://www.gemmaonline.nl/index.php/StUF-gegevenswoordenboeken)

### ZDS (Zaak- en Documentservices)

Deze variant is bedoeld voor organisaties die gebruik maken van de ZDS-standaard voor zaak- en documentbeheer.

![ZDS  Architecture](https://raw.githubusercontent.com/CommonGateway/WaardepapierenBundle/main/docs/zds_waardepapier.svg)

#### Referentie

* [ZDS](https://www.gemmaonline.nl/index.php/Zaak-_en_Documentservices)
