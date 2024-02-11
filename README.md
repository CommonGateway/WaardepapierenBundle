# Waardepapieren


## Waarom waardepapieren?

Soms moet je aantonen dat je in een gemeente woont. Bijvoorbeeld als een woningcorporatie daarom vraagt. Je gaat dan naar de gemeente om een uittreksel aan te vragen. De gemeentemedewerker zoekt jouw gegevens op in het burgerzakensysteem. Print een uittreksel. En zet daar een stempel op. Een uittreksel kan vaak ook online aangevraagd worden. Het duurt dan een aantal dagen voordat je het uittreksel thuis hebt. In beide gevallen is het proces arbeidsintensief, en klantonvriendelijk. Ook zijn de kosten voor zowel de gemeente als inwoner hoog. 

Deze dienstverlening aan inwoners en bedrijven moet verbeterd worden. Het liefst in combinatie met het bereiken van een efficiëntere bedrijfsvoering. Dit streven leidde bij de gemeente Haarlem tot de ontwikkeling van een Proof of Concept (PoC) voor het verstrekken van digitale waardepapieren. Een uittreksel uit de Basisregistratie Personen bijvoorbeeld. De klantreis voor uittreksels is in kaart gebracht om de behoefte aan deze oplossing bij zowel de gemeente als de inwoner te analyseren. Acht gemeenten hebben het prototype uitgewerkt tot een veilig product dat gemeenten nu zelf kunnen implementeren.

## Wat is nu eigenlijk een waardepapier?
In de technische zin is een waardepapier een claim die kan worden gecontroleerd, of in vakjargon [verifiable credentials](https://www.w3.org/TR/vc-data-model/) een [europese standaard](https://ec.europa.eu/digital-building-blocks/wikis/pages/viewpage.action?pageId=555222155) . Internationaal (en binnen de EU identywallet) gebruiken we als techniek daarvoor doorgaans [JSON WEB Tokens](https://en.wikipedia.org/wiki/JSON_Web_Token) maar in Nederland gebruiken we vaak [irma](https://irma.app/docs/schemes/#updating-and-signing-schemes-with-irma). De waardepapieren service ondersteund in princiepe bijde.

Een claim kan een data set zijn (zo als een volledig uittreksel) en een enkel feit (zo als een diploma), laten we die laatste eens als voorbeeld nemen

````json
{
  "@context": [
    "https://www.w3.org/2018/credentials/v1",
    "https://www.w3.org/2018/credentials/examples/v1"
  ],
  "id": "http://example.edu/credentials/3732",
  "type": ["VerifiableCredential", "UniversityDegreeCredential"],
  "issuer": "https://example.edu/issuers/565049",
  "issuanceDate": "2010-01-01T00:00:00Z",
  "credentialSubject": {
    "degree": {
      "type": "BachelorDegree",
      "name": "Bachelor of Science and Arts"
    }
  }
}
````
Het is met andere woorden niet veel meer dan een JSON object dat een aantal eigenschappen bevat, deze eigenschappen zouden overeen moeten komen met de eigenschappen van het document waarop de QR code gedrukt gaat worden.

Vervolgens onderteken we de credential met een certificaat zodat aan de hand van de public key kan worden gecontrolleerd of de credential
- Daadwerkenlijk afgegeven is door de partij die er op staat als issuer
- Niet is aangepast
- Niet is verlopen (optioneel)
- Niet is ingetrokken (optioneel)

En geeft de scan app de credential subject informatie terug te controlle (het papier waar de code op staat zou immers kunnen zijn aanepast).

Zo creëren we een waterdicht systeem waarbij een afnemende partij in één oogopslag kan zien dat de gegevens nu nog juist zijn.

## Met wie we samenwerken

Samenwerken met elkaar aan innovatie is nodig om zowel opbrengsten als investeringen te delen. We vinden het voor onze innovatiekracht belangrijk om onze netwerken met andere gemeenten in te zetten bij innovatie.
De betrokken gemeenten bij dit innovatieproject zijn:

* Haarlem
* Bloemendaal/Heemstede
* DUO+ (Diemen, Uithoorn, Ouder-Amstel)
* Enschede
* Harderwijk
* Rotterdam
* Hoorn
* Schiedam

Regiebureau Dimpact is namens deze gemeenten opdrachtgever naar ICTU, die als opdrachtnemer optreedt. Binnen Dimpact delen gemeenten kennis en inspiratie. Dimpact is het platform voor het verbeteren én hergebruiken van praktische oplossingen. We delen onze kennis, netwerken en oplossingen met elkaar.

## Wat we willen bereiken

Als gemeenten willen we zoveel mogelijk samen optrekken in het ontwikkelen van technologische oplossingen. We hebben dezelfde doelstelling: het verbeteren van de dienstverlening aan inwoners en bedrijven. De ontwikkeling en implementatie van de toepassing voor het verifiëren van digitale waardepapieren draagt bij aan het verbeteren van de klantreis van inwoners. En het levert een kostenbesparing voor zowel gemeenten als inwoners op.

De techniek die voor het digitale uittreksel ontwikkeld is, kent veel meer toepassingsmogelijkheden. Alle gemeentelijke informatie waarvan de echtheid en authenticiteit gecontroleerd moet worden, komen in aanmerking voor de techniek.

## Wat samenwerken ons oplevert

Door samenwerkingen ontstaan er betere oplossingen. Dat is ons uitgangspunt. Gemeenten kunnen bij het opschalen profiteren van het werk van deze acht koplopergemeenten. We delen successen, ervaringen en ontwikkelen samen door.

Door samenwerking kunnen we…

* inspiratie en kennisdeling stimuleren
* een grotere slagvaardigheid realiseren
* opschalen
* aansluiting zoeken bij landelijke ontwikkelingen
* schaalvoordelen realiseren
