# Architectuur

De Waardepapieren Service is in 2023 getransformeerd van een standalone applicatie naar een microservice die complementair is aan een zaakregistratiesysteem. Deze transformatie maakt het mogelijk om de service naadloos te integreren met bestaande zaaksystemen binnen gemeentelijke organisaties.

![Microserviced Architecture](https://raw.githubusercontent.com/CommonGateway/WaardepapierenBundle/main/docs/microservice.svg)

**Referenties**

* [Microservices Architectuur](https://www.noraonline.nl/wiki/Microservices)

## Varianten

Afhankelijk van de opzet van het zaaksysteem binnen de organisatie die waardepapieren wil creëren, zijn er verschillende varianten (of combinaties daarvan) mogelijk.

### ZGW (Zaakgericht Werken)

Deze variant is specifiek ontworpen om te integreren met ZGW-gebaseerde zaaksystemen. Het maakt gebruik van de ZGW API's voor een naadloze interactie.

![ZGW  Architecture](https://raw.githubusercontent.com/CommonGateway/WaardepapierenBundle/main/docs/zgw_waardepapier_klein.svg)

**Referenties**

* [ZGW API's](https://www.vngrealisatie.nl/producten/api-standaarden-zaakgericht-werken)

### Mijn Omgeving

Deze variant is bedoeld voor integratie met persoonlijke omgevingen waar burgers hun zaken kunnen inzien en beheren.

![Mijn Omgeving  Architecture](https://raw.githubusercontent.com/CommonGateway/WaardepapierenBundle/main/docs/zgw_waardepapier_mijn-zaken.svg)

**Referenties**

* [MijnOverheid](https://www.mijnoverheid.nl/)

### Notify

Deze variant maakt gebruik van notificaties om gebruikers en systemen op de hoogte te stellen van de status van waardepapieren.

![Notify  Architecture](https://raw.githubusercontent.com/CommonGateway/WaardepapierenBundle/main/docs/zgw_waardepapier_notify.svg)

**Referenties**

* [Notificatie API's](https://www.vngrealisatie.nl/producten/api-standaard-notificaties)

### StuF-zaken en ZDS

Het is ook mogenlijk om waardepapieren te draaien op StuF-Zaken of ZDS, zie daarvoor de pagina [StuF en ZDS](Stuf_en_ZDS).
