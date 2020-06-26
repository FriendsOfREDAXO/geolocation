# Contribution to a planned Geo Addon for [REDAXO](https://redaxo.org) 5.10+

## Verwendung:

- Ideen-Beitrag für das [Geo](https://github.com/FriendsOfREDAXO/friendsofredaxo.github.io/issues/124)-Projekt von [Thomas Blum](https://github.com/tbaddade)
    - Tile-Proxy mit Cache
    - Web-Componente
- Ideen-Beitrag für das [Experimental-Proxy](https://github.com/FriendsOfREDAXO/experimental/tree/master/plugins/proxy)-Projekt
    - Tile-Proxy mit Cache
- Ansonsten zur gefälligen Benutzung

>**nicht als [FOR](https://github.com/FriendsOfREDAXO-Addon)-Addon vorgesehen, es sollte m.E. nur __ein__ GEO-Addon bei FriendsOfREDAXO geben!**

## Quellen

Inspiriert von[Thomas Skerbis](https://github.com/skerbis) Addon "[osmproxy](https://github.com/FriendsOfREDAXO/osmproxy)" und gefüttert mit Ideen und Snippets aus anderen Addons und aus den Diskussionen dazu auf GitHub und im [Slack-Channel](https://friendsofredaxo.slack.com/).

Der rote Map-Marker stammt von [Thomas Pointhuber](https://github.com/pointhi/leaflet-color-markers).

Enthält [LeafletJS](https://leafletjs.com/) von [Vladimir Agafonkin](https://agafonkin.com/).

## Features:

- Backend
    - Tile-URLs zu Tile-Anbietern mit weiteren Parametern inkl. Sprachunterstützung und Cache-Verhalten
    - Kartensätze zusammenstellen verwalten, die auf einer oder mehreren Tile-Urls basieren
    - Datenverwaltung mit YForm
    - Proxy-Server für Tile-Abrufe vom Browser
    - Cache für Tile-Abrufe
    - Verschleierung der tatsächlichen Tile-Url ggü. dem Client / Schutz der ggf. kostenpflichtigen appId´s z.B. von Google


- Frontend
    - LeafletJS als Kartensoftware integriert
    - Karten-HTML als Web-Komponente `<rex-map .... ></rex-map>` (->[geolocation.js](assets/geolocation.js))


- Demo
    - [Stand-alone-Demo](assets/demo.html) zur Demonstration der Web-Komponente und des Cache
    - `/assets/addons/gelocation/demo.html`, bitte ins Redaxo-Root kopieren wg. der Pfade


- Cache
    - Je Tile-URL ein eigenes Verzeichnis, separat löschbar
    - Cronjob zum Aufräumen
        - Dateien löschen, die älter sind als die Time-to-live der Tile-URL
        - weitere ältere Dateien löschen wenn das Verzeichnis zu viele Dateien enthält

## Installation

In das Verzeichnis `redaxo/src/addons/geolocation` entpacken und in der Addon-Verwaltung die
Installation durchführen.

Dabei werden Demo-Daten (4 Links zu Tile-Servern und 2 Kartensätze installiert). Die Here-Kartenlinks
sind ohne die nötige "appId", die nach Registrierung bei [HERE](https://developer.here.com/) erzeugt werden kann.
Die vorgesehene Stelle in der URL ist mit `..........` markiert.

Der Cronjob für die Cache-Bereinigung ist mit den Einstellungen
- Einmal am Tag (04:30)
- Backend/Frontend
- Scriptanfang
- aktiviert


## Beispielmasken:

### Allgemeine Konfiguration

![Konfiguration](https://raw.githubusercontent.com/christophboecker/gelocation/assets/config.jpg)

### Kartensatz

![Kartensatz: Auflistung](https://raw.githubusercontent.com/christophboecker/gelocation/assets/maps_list.jpg)
![Kartensatz: Formular](https://raw.githubusercontent.com/christophboecker/gelocation/assets/maps_edit.jpg)

### Layer-/Tile-Server

![Tile-Layer: Auflistung](https://raw.githubusercontent.com/christophboecker/gelocation/assets/tiles_list.jpg)
![Tile-Layer: Formular](https://raw.githubusercontent.com/christophboecker/gelocation/assets/tiles_edit.jpg)

### `<rex-map .... ></rex-map>`

Darstellung der Karte im Browser über eine Web-Componente:

```html
<rex-map map="..." mapset="..." dataset="..."></rex-map>
```
Die Attribute sind zu Strings umgeformte Arrays/Objekte.

```HTML
<rex-map
    class="leaflet-map"
    map="{&quot;minZoom&quot;:3}"
    mapset="{
        &quot;default&quot;:{&quot;tile&quot;:1,&quot;label&quot;:&quot;Karte&quot;,&quot;type&quot;:&quot;b&quot;,&quot;attribution&quot;:&quot;Map Tiles &amp;copy; 2020 &lt;a href=\&quot;http:\/\/developer.here.com\&quot;&gt;HERE&lt;\/a&gt;&quot;},
        &quot;sat&quot;:{&quot;tile&quot;:2,&quot;label&quot;:&quot;Satelit&quot;,&quot;type&quot;:&quot;b&quot;,&quot;attribution&quot;:&quot;Map Tiles &amp;copy; 2020 &lt;a href=\&quot;http:\/\/developer.here.com\&quot;&gt;HERE&lt;\/a&gt;&quot;},
        &quot;hybrid&quot;:{&quot;tile&quot;:3,&quot;label&quot;:&quot;Hybrid&quot;,&quot;type&quot;:&quot;b&quot;,&quot;attribution&quot;:&quot;Map Tiles &amp;copy; 2020 &lt;a href=\&quot;http:\/\/developer.here.com\&quot;&gt;HERE&lt;\/a&gt;&quot;}
        }"
    dataset="{&quot;position&quot;:[47.516669,9.433338],&quot;bounds&quot;:[[47.5,9.3],[47.7,9.7]],&quot;marker&quot;:[[47.611593,9.296344],[47.586204,9.560653],[47.54378,9.686559]]}"
></rex-map>
```

Der JS-Code dazu ist ausbaufähig. Das HTML-Beispiel zeigt aber schon, wie mit setAttribute der Datensatz und die Kartenzusammenstellung ausgetauscht werden können.

Idee: komplexe interaktive Inputs für verschiedene Datensatz-Elemente in einer Web-Komponente gebündelt
```html
<rex-map-input class="..." name="..." value="«dataset»" tools="position,marker,bounds,..."></rex-map-input>
```


#### Datensatz

Die Karte wird über einen Datensatz gesteuert, der die relevanten Daten zur Karte enthält.

```javascript
datensatz = {
    position: [47.516669,9.433338],
    bounds: [[47.5,9.3],[47.7,9.7]],
    marker: [[47.611593,9.296344],[47.586204,9.560653],[47.54378,9.686559]],
}
```
Alle Angaben sind optional. Position ist der "Hauptmarker" in der Karte in rot. Alle anderen
zusätzlichen Marker sind blau. "Bounds" legt das Rechteck fest, dass auf jeden Fall in der
Karte sichtbar sein soll.

Grundidee: es gibt gleich strukturierte Klassen je "Tool", wobei alle Elemente im Datensatz (position,bounds,marker)
jeweils eine eigene Klasse haben. Die Klasse steuert die Anzeige auf der Karte toolspezifisch. Das System
ist einfach erweiterbar nur durch Bereitstellung einer neuen Toolklasse.


#### Kartensatz

Im Backend werden aus Layern(Tiles/Kacheln) Kartensätze zusammengestellt, die ihrerseits einen Datensatz bilden. Leaflet unterscheidet zwischen Basiskarten (type=b) und Overlays (type=o).
```javascript
kartensatz = [
    {
        "tile":"1",
        "label":"Karte",
        "type" :"b",
        "attribution":"Map Tiles &copy; 2020 <a href=\"http:\/\/developer.here.com\">HERE<\/a>"
    },
    {
        "tile":"2",
        "label":"Satelit",
        "type" :"b",
        "attribution":"Map Tiles &copy; 2020 <a href=\"http:\/\/developer.here.com\">HERE<\/a>"
    },
    {
        "tile":"3",
        "label":"Hybrid",
        "type" :"b",
        "attribution":"Map Tiles &copy; 2020 <a href=\"http:\/\/developer.here.com\">HERE<\/a>"
    }
]
```
