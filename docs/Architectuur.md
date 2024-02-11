# Architectuur

De Waardepapieren Service is in 2023 getransformeerd van een standalone applicatie naar een microservice die complementair is aan een zaakregistratiesysteem. Deze transformatie maakt het mogelijk om de service naadloos te integreren met bestaande zaaksystemen binnen gemeentelijke organisaties. Hierbij is specifiek gekeken naar het ondersteunen van veel gebruikte open source pakketen zo als zaaksysteem.nl en open zaak.

![Microserviced Architecture](https://raw.githubusercontent.com/CommonGateway/WaardepapierenBundle/main/docs/microservice.svg)

**Referenties**

* [Microservices Architectuur](https://www.noraonline.nl/wiki/Microservices)

## Componenten

De Waardenpapieren.app bestaat uit de volgende componenten

1. **Scan app:** De (MVP) scan app voor het controlen van de QR code op een waardepapier.
2. **Waardepapieren Service:** De Service die verantwoordenlijk is voor het daadwerkenlijk creëren van het waardepapier
3. **Waardepapieren Register (optioneel):** Indien waardepapieren intrekbaar zijn moeten deze worden opgeslagen in een register zodat zij daadwerkenlijk vernietigd kunnen worden.

## Intrekken van waardepapieren

Het intrekken van waardepapieren vergt het installeren van een controlleregister (waardepapieren register), dat publiek toegankenlijk is en een kopie van de atributen bevat (zodat er atribuut specifiek vernietigd kan worden). Tijdens iedere controlle van een claim wordt dit register bevraagd aan de hand van een unieke hash. De server kan echter maar drie antwoorden geven (geldig, ongeldig, onbereibkaar) een waardepapier dat niet in het register kan worden gevonden word aangeduid als ongeldig. Hoe er rekening meer dat in deze casus bij het verhuisen of beindigen van een waardepapieren register alle daarin ogenomen waardepapieren niet langer te valideren zijn.

![Waardepapier Intrekken](https://raw.githubusercontent.com/CommonGateway/WaardepapierenBundle/main/docs/waardepapier_intrekken.svg)

## Varianten

Afhankelijk van de opzet van het zaaksysteem binnen de organisatie die waardepapieren wil creëren, zijn er verschillende varianten (of combinaties daarvan) mogelijk.

### ZGW / Zaakgericht Werken

Deze variant is specifiek ontworpen om te integreren met ZGW-gebaseerde zaaksystemen. Het maakt gebruik van de ZGW API's voor een naadloze interactie Het is tevens de **Basis variant** we gaan er namenlijk vanuit dat de gemeente zaakgericht werkt, dat betekend automatisch dat de afhandeling (en dus ook document creatie) plaatsvind op het zaaksysteem. Het is vanuit die visie dan ook onwensenlijk dat een formulieren applicatie of mijn omgeving zelf waardepapieren creert (als dan niet via de service). Hiermee zou immers de scheiding van functionele componenten worden gebroken.

![ZGW Architecture](https://raw.githubusercontent.com/CommonGateway/WaardepapierenBundle/main/docs/zgw_waardepapier_klein.svg)

**Referenties**

* [ZGW API's](https://www.vngrealisatie.nl/producten/api-standaarden-zaakgericht-werken)
*

### Direct gebruik

Naast de waardenpapieren service laten mee luisteren op een zaaksysteem is het ook mogenlijk om applicaties direct via de services een waardepapier te laten creëren (bijvoorbeeld omdat je een waardepapier nodig hebt los van een zaak). In dat geval kan de onderliggende applicatie een JSON bericht aan de waardepapieren service sturen de service creert vervolgens een waardepapier en geeft dit terug. Deze service dient alleen te worden gebruikt door componenten die een functionele taak hebben voor het creeren van waardepapieren (bijvoorbeeld een burgerzaken applicatie) en niet door formulieren en mijn omgevingen (zie ook ZGW / Zaakgericht werken)

![Direct Architecture](https://raw.githubusercontent.com/CommonGateway/WaardepapierenBundle/main/docs/direct_waardepapier.svg)

### Mijn Omgeving

Deze variant is bedoeld voor integratie met persoonlijke omgevingen waar burgers hun zaken kunnen inzien en beheren. Het laat de totale reis van aanvraag tot terugkoppeling zien. Let er hierbij op dat de creatie van een waardepapier via de zaak verloopt (en dus niet rechstreeks vanuit de mijn omgevign of formulieren toepassing)

![Mijn Omgeving  Architecture](https://raw.githubusercontent.com/CommonGateway/WaardepapierenBundle/main/docs/zgw_waardepapier_mijn-zaken.svg)

**Referenties**

* [MijnOverheid](https://www.mijnoverheid.nl/)

### Notify

Deze variant maakt gebruik van notificaties om gebruikers en systemen op de hoogte te stellen van de status van waardepapieren.

![Notify Architecture](https://raw.githubusercontent.com/CommonGateway/WaardepapierenBundle/main/docs/zgw_waardepapier_notify.svg)

**Referenties**

* [Notificatie API's](https://www.vngrealisatie.nl/producten/api-standaard-notificaties)

### StuF-zaken en ZDS

De waardenpapieren service zelf is gebaseerd op ZGW het is echter ook zeker mogelijk om waardepieren te gebruiken in combinatie met StuF of ZDS. In dat geval zal er gebruik moeten worden gemaakt van een "[Stuf brug]()" of "[ZDS brug]()". Beide zijn als open source plugin te downloaden en gebruiken op het zelfde framework.

#### StuF (Standaard Uitwisselings Formaat)

Deze variant is ontworpen voor systemen die gebruik maken van het StuF-protocol voor gegevensuitwisseling.

![Stuf  Architecture](https://raw.githubusercontent.com/CommonGateway/WaardepapierenBundle/main/docs/stuf_waardepapier.svg)

**Referenties**

* [StuF](https://www.gemmaonline.nl/index.php/StUF-gegevenswoordenboeken)

#### ZDS (Zaak- en Documentservices)

Deze variant is bedoeld voor organisaties die gebruik maken van de ZDS-standaard voor zaak- en documentbeheer.

![ZDS  Architecture](https://raw.githubusercontent.com/CommonGateway/WaardepapierenBundle/main/docs/zds_waardepapier.svg)

**Referenties**

* [ZDS](https://www.gemmaonline.nl/index.php/Zaak-_en_Documentservices)
