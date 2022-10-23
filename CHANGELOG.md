# Changelog

## xx.xx.2022 2.0.0 (under construction)

Bearbeitung mit RexStan und Bereinigung diverser Fehler. Das alles führt zu einem BC.
(Das ist noch nicht der finale Text, erst mal nur die diversen PR auflisten)

- diverse Fehlerklassen in eigene Dateien ausgelagert (#48)
- Fehlerklasse `GeolocationInstallException` umbenannt in `Geolocation\InstallException` (#48)
- Dateinamen an die Schreibweise der Klassen angepasst (`Box.php`, `Math.php`, `Point.php`) (#50)
- Klasse `Geolocation\cache` umbenannt in `Geolocation\Cache`; Aufrufe und Doku angepasst. (#51)
- Klasse `Geolocation\cronjob` umbenannt in `Geolocation\Cronjob`; Aufrufe und Doku angepasst. (#52)
- Klasse `Geolocation\tools` umbenannt in `Geolocation\Tools`; Aufrufe und Doku angepasst. (#53)
- Klasse `Geolocation\config_form` umbenannt in `Geolocation\ConfigForm`; Aufrufe und Doku angepasst. (#66)
- Klasse `Geolocation\layer` umbenannt in `Geolocation\Layer`; Aufrufe, Dateinamen und Doku angepasst. (#86)
- Dateinamen `lib/exception.php` an den Klassennamen angeglichen (`lib/exception.php`) (#73)
- Datentyp der Tabellenspalte `rex_geolocation_layer.online` von `text` in `int` geändert. Ggf. müssen eigene Datasets angepasst werden. (#77)
- Workaround in `layer.php` für ein Typecast-Problem aus 'class dataset' (#79)
- Fehlermeldungen in `class InvalidParameters` gebündelt (#80,#81)
- RexStan-gesteuerte Überarbeitung aller PHP-Dateien (Level: 8, PHP: 8.0-8.2, Extensions: REDAXO Superglobals, Bleeding-Edge, Strict-Mode, Deprecation Warnings, phpstan-dba, dead code) und Code-Formatierung im REDAXO-Standard (#54…#62, #66, #68, #70…#72, #74…#76, #80…#82, #84…#85)
- Vendor-Update: phpGeo (#83)

## 06.05.2022 1.0.2

- Das Bugfix-Relase 1.0.1 ist zwar auf Github korrekt, nicht aber im Installer. Warum auch immer. 
  Mit 1.0.2 sollte auch die Installer-Version bzw.das Release selbst die korrekten Änderungen
  aufweisen. (Daniel Steffen via Slack)

## 19.04.2022 1.0.1

- Behebt einen Bug in der Zuweisung und Initialisierung der Konstanten KEY_MAPSET und KEY_TILES während
  der Installation. Bugs können dazu führen, dass Tiles nicht richtig abgerufen werden können, da in der
  URL das falsche Schlüsselwort verwendet wird. In dem Fall hilft das Update und ggf. eine manuelle
  Re-Installation. Zur Überprüfung: in assets/addons/geolocation/geolocation.min.js sollte die Zeichenfolge
  `var Geolocation={default:{keyMapset:'geomapset',keyLayer:'geolayer'` zu finden sein. 

## 29.03.2022 1.0.0

- Bisher durfte ein Tool nur einmal auf der Karte erscheinen. Die Namenskonvention ist nun geändert.
  Ein Tool-Name (z.B.`'position'`) kann durch ein per '|' angehängtes Suffix eindeutig gemacht
  werden (z.B.`'position|xyz'`).
- Der Event `Geolocation:dataset.ready` liefert jetzt auch die Referenz zu den Tools (`e.detail.tools`).
- Tools werden in einer Instanz der JS-Klasse `Map` verwaltet und sind nun über den Namen
  auffindbar.
- Die Daten in einem Tool können per abgerufen werden (`«tool».getValue()`).
- Dokumentation aktualisiert.

## 05.03.2022 Zweite 1.0 beta-2 

- Die Namen der Proxy-Aufrufe (geolayer, geomapset) sind im JS-Code abgelegt und werden JS-seitig in
  Abruf-URL eingebaut (vorher: in der Url-Definition mitgeliefert). Die URL-Definition im Mapset
  enthält nur noch die Karten-ID.
- Die Addons Geolocation und yform_geo_osm vertrugen sich nicht. `package.yml` um conflicts-Eintrag
  ergänzt; ebenso in `README.md` und `docs/install.md`. Lösung: yform_geo_osm > 1.2.5 installieren.

## 21.02.2022 Erste 1.0 beta

- Readme-Link fixed


## **20.02.2022 Version 0.15.0**

- Einbinden der Standard-Assets im Backend nun auch über `tools::echoAssetTags()`
- Event-Namen:
    - andere Struktur; Präfix nun mit Doppelpunkt als Trennzeichen (`geolocation:`) ähnlich `rex:`
    - alt: `geolocation.create` -> neu: `geolocation:map.ready`
    - alt: `geolocation.setData` -> neu: `geolocation:dataset.ready`
    - Dokumentation aktualisiert
- neue Methode `\Geolocation\mapset->getDefaultId()`
- In der Mapset-Liste wird der Aktions-Button "Löschen" für den Default-Mapset ausgeblendet
- package.yml,help.yml: Permissions in '' gesetzt
___


## **07.02.2022 Version 0.14.2**

- Deutlichere Hinweise in Handbuch und Installation, dass in den Beispieldaten bei der Karten-URL
  der persönliche API-Key einzutragen ist. Andernfalls können keine HERE-Karten abgerufen werden.
___


## **07.02.2022 Version 0.14.1**

- Bugfix in install.php: temporäre SQL-Datei konnte auf einem Linux-System nicht angelegt werden (@iceman-fx)
___


## **09.01.2022 Version 0.14.0**

- Erweitert um ein Anzeige-Tool für geoJSON-Datensätze
- Online-Dokumentation in den Entwicklerseiten bzgl. geoJSON erweitert
- PHP-seits um Klassen zum Rechnen mit Koordinaten erweitert, basierend auf
  [**phpGeo**](https://github.com/mjaschen/phpgeo)
  von [Markus Jaschen](https://github.com/mjaschen) => Point, Box, Math
- Online-Dokumentation in den Entwicklerseiten bzgl. Rechnen mit Koordinaten erweitert

- Bugfix in layer.php (getLabel), assetpacker.php (SCSS-Pfadname unter Windows) und devphp.md
  (Text) (@dtpop)
___


## **17.12.2021 Version 0.13.2**

- Bugfix in mapset.php: take() ohne Parameter führte zum Whoops
___


## **16.12.2021 Version 0.13.1**

- Bugfix in mapset.php, falsche Klassenbezeichnung
- Namespace für AssetPacker unter Geolocation eingeordnet
___


## **13.12.2021 Version 0.13.0**

- Umstellung der Aktions-Button in Listen auf Action-Dropdown (ab YForm 4.0-beta5)
___


## **12.12.2021 Version 0.12.1**

- Returntype `?self` für `mapset::get` funktioniert erst ab PHP 7.4. Statt dessen
  `?\rex_yform_manager_dataset`.
___


## **12.12.2021 Version 0.12.0**

- HELP.PHP
    - Angehoben auf Version 2.3 mit Darkmode und Berechtigungen
- Dokumentation aktualisiert und umstrukturiert
    - Texte neu aufgeteilt
    - Inhalte aktualisiert
    - Anzeigeumfang berücksichtigt die Addon-Berechtigungen (help.yml)
- Installation
    - Die JS/CSS-Dateien `geolocation*.*` in der Basisversion liegen nun im install-Verzeichnis und
      werden von dort herangezogen (vorher: .../assets), um Überschreiben zu vermeiden
    - Explizites `include` diverser Scripte entfernt, überflüssig
- Sonstiges
    - Code überarbeitet
        - zahlreiche kleinere Korrekturen
        - Inline-Dokumentation in fast allen PHP-Dateien überarbeitet und erweitert
        - Umstellung auf YForm 4.0 (BC), z.B. Dataset-Klassen)
- tableset.json
    - Feldzuweisung des Validators für Layer-Url/Layer-Subdomain korrigiert
- package.yml
    - Voraussetzungen angehoben auf REDAXO 5.13 und YForm 4
    - PHP-Voraussetzungen um cURL erweitert
___

## **09.04.2021 Version 0.10.0**

- Javascript
    - i18n jetzt mit Parametern zur Textersetzung
- php
    - Permissions eingeführt
    - Cache Löschen nun via API-CAll
    - yTemplate value.choice.check.tpl.php: Aufruf des Originaltemplates verbessert
- Doku
    - Menü im Header korrigiert
    - Texte überarbeitet
- Bugs
    - CSS verbessert
    - Installations-Fehler bei `importDump()` behoben (Dateiname ohne .sql)
___


## **15.02.2021 Version 0.9.0**

- Umgestellt auf `namespace Geolocation;`
- Assets auf drei reduziert (geolocation.min.js, geolocation.min.css, geolocation_be.min.css)
- Individuelle Konfiguration des Systems update-sicher via data-Verzeichnis
    - Installationsparameter überschreiben
    - BE-Layout (vollständig oder nur Proxy/Cache) konfigurieren
    - Eigenes, erweiterndes CSS und JS direkt in geolocation.min.js/css einbauen
    - Eigenes JS auf Leaflet basierend einbauen (statt Geolocation-Code)
    - Komplett eigenständigen JS-Code einsetzen statt Leaflet/Geolocation
    - Sprachanpassung
- Ausführliches Handbuch
- Zusätzliche Kartenfeatures
    - Aktuelle Position ausfindig machen (Locate-Control)
    - Karte gegen versehentliches Zoomen beim Scrollen geschützt und Gesten (Leaflet-Plugin)
- Viele kleine Details im PHP-Code verbessert
- viele kleine Bugs entfernt

___


## **20.09.2020 Version 0.2.0**

- Proxy auch ohne Cache möglich (nur Durchreichen), wenn die Aufbewahrungsdauer (TTL) null (0) ist
- Javascript
    - In der Karte das Zoom-Control ersetzt durch ein Control mit Buttons für Zoom, Fullscreen, Home
    - Kartenverwaltung aus dem Custom-HTML-Elenent `<rex-map>` verlagert in eine eigene Klasse.
    - Neue Funktion zum Initialisieren eines <div> statt Nutzung des Custom-HTML-Elenents
- Installation
    - tableset.json um unnötige Einträge erleichtert.
    - tableset.json lesbar formatiert
- Code-Struktur verändert
    - `geolocation_proxy` umbenannt in `geolocation_cache`, weil die Klasse Cache-Verwaltung betreibt.
    - Diverse Methoden bzgl. Datenübertragung von `geolocation_cache` nach `geolocation_tools` sowie
      nach `geolocation_mapset` und `geolocation_layer` verschoben.
    - Marker-Pins werden als SVG-Icon erzeugt (`Geolocation.svgIconPin(color,nr,nrColor)`)

___


## **26.06.2020 Version 0.1.0**

- Basisversion
