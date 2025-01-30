# Changelog

## xx-xx-2025 2.5.0

- Neu: YForm-Value "geolocation_picker" (in Arbeit)
- Update: Vendor mjaschen/phpgeo (geplant)

## 06-12-2024 2.4.0

- kleinere interne Modifikationen
  - YForm-Tabellennamen für _pages/yform.php: via _package.yml_ in zusätzlichen Schreibvarianten:
    - als reiner Tabellenname Prefix-neutral angeben (ohne rex_) (#163)
    - indirekt über den Namen der ModelClass
    - als Tabellenname inkl. Prefix
  - EP-Callbacks: 
    - Aufruf in der "First class callable syntax"
    - Methoden-Namen gem. PSR angepasst

- Bugfix
  - Fehlendes `exit` in _manual.php_ führte bei der Bilderdurchleitung zu Warnings in Systemlog. Behoben.
  - Update-Aktionen in _install.php_ wueden nicht korrekt Versions-abhängig angesteuert. Umgestellt auf "immer prüfen und ggf. ausführen"
  - Typo korrigiert (Democode in _devmath.md_)


## 25-07-2024 2.3.1

- BugFix
  - Behebt einen Fehler beim Speichern einer Karte (Layer), die ohne
    Label-Angabe ist. (danke @dtpop) (#162),


## 01-07-2024 2.3.0

- Anpassungen für YForm ab 4.2.1
  - Im Kartensatz-Formular erfolgt die Layerauswahl mit einem modifizierten Widget
    (Select durch eine List-group ersetzt) Da sich mit YForm 4.2 der Aufbau des
    Original-Widgets geändert hat, muss die Modofikation anders erfolgen. (#159)
  - Die Funktionsweise der Spracheingabe in einem feld vom Typ be_table wurde
    angepasst, da be_table seit 4.2.1 intern Daten anders vearbeitet. (#160)
  - Beide Änderungen sind weitgehed abwärtskompatibel zur YForm-Mindestversion 4.0
    (ausgenommen generiertes HTML).
    
- BugFix
  - JS: Die Verlinkung der internen Geolocation-Strukturen im Karten-Container
    erfolgte zu spät. `map._conainer.__rmMap` ist nun bei der Tool-initialisierung
    wie geplant früher verfügbar. (#158)


## 26-02-2024 2.2.0

- Umbau (#152)
  - Die Handbuchseiten sind technisch etwas anders realisiert.
    - help.php/help.yml aufgelöst - kaum Chancen für die Zulassung durch RexStan :-)
    - statt dessen eine vereinfachte Lösung als page/manual.php
    - Handbuch-Menü komplett über package.yml realisiert
  - Inhalte der Doku angepasst und formal optimiert (z.B. ```php statt ```)
  - Beautifier für Code-Blöcke (PrismJS) aktualisiert (als help.min.js/css), anderes Farbschema (hell)
  - Handbuch-Berechtigungen um "geolocation[developer]" erweitert

- BugFix
  - Typos in package.yml korrigiert (#154 @TobiasKrais)



## 25.06.2023 2.1.3

- BugFix:
  - Fehler in der Namespace-Unterstützung (Dateistruktur, Cronjob-Registrierung) bereinigt.
  - behebt [#143](https://github.com/FriendsOfREDAXO/geolocation/issues/143) (@tyrant88)

## 25.06.2023 2.1.2

- BugFix:
  - ";" in Layer.php#43 ergänzt (#148)

## 25.06.2023 2.1.0

- BugFix:
  - Im vorigen Release ("Fehler in der cUrl-Kommunikation mit dem Tile-Server werden in das Logfile geschrieben") fehlten use-Statements (Korrektur durch @TobiasKrais, #146)

## 25.06.2023 2.1.0

- Erweiterung:
  - Fehler in der cUrl-Kommunikation mit dem Tile-Server werden in das Logfile geschrieben. (#145)

## 28.05.2023 2.0.1

- Bugfix:
  - Behebt einen Fehler `Unable to rollback, no transaction started before` beim Speichern eines Mapset-Formulars mit "Übernehmen" statt "Speichern" (#144)

## 21.03.2023 2.0.0

- Bugfix:
  - Tastatursteuerung in der Baisiskarten-Auswahl der Kartensätze korrigiert. (#139)
  - Namespace-Fehler in Layer.php korrigiert (#140)

Beta3: 

- Neu
  - Per Config (z.B. im project AddOn) kann ein Socket-Proxy angegeben werden, falls erforderlich. Die config für den namespace geolocation lautet: 'socket_proxy' (#131) - Siehe (https://curl.se/libcurl/c/CURLOPT_PROXY.html).
- Fixed
  - Probleme mit der Einbindung der Action-Buttons in YForm 4.1.0 wurden behoben (#130). 

Beta2:

- Neu:
  - In zukünftigen Versionen von YForm (post 4.0.4) werden Action-Buttons anders verwaltet. Geolocation ist so mgestellt,
    dass beide Varianten unterstützt werden. Das gilt bereits für die aktulle Github-Version Stand 07.03.2023. (#135)
- Bugfix:
  - Fehler bei nicht initialisiertem Choice-Array, wenn keine Choices ausgewählt wurden, behoben (@dpf-dd Stefan Dannfald, #126)
  - Mapset: Listeformatierung der Spalte "Funktion" via `SetColumnFormat()` berücksichtigt ein vorher gesetztes CustomCallback (#134)

Beta1:

Diese Version enthält **Breacking Changes!**
- Mindestversionen: REDAXO 5.14 und **PHP 8**
- Umstellung des Namespace von `Geolocation` auf `FriendsOfRedaxo\Geolocation` (#113). **Referenzen in eigenem Code auf `Gelocation\xxx` müssen angepasst werden.**
- Klassen umbenannt (Großbuchstabe am Anfang). **Referenzen in eigenem Code auf die Klassennamen müssen angepasst werden.**
  - Klasse `Geolocation\cache` umbenannt in `Geolocation\Cache`; Aufrufe und Doku angepasst. (#51)
  - Klasse `Geolocation\cronjob` umbenannt in `Geolocation\Cronjob`; Aufrufe und Doku angepasst. (#52)
  - Klasse `Geolocation\tools` umbenannt in `Geolocation\Tools`; Aufrufe und Doku angepasst. (#53)
  - Klasse `Geolocation\config_form` umbenannt in `Geolocation\ConfigForm`; Aufrufe und Doku angepasst. (#66)
  - Klasse `Geolocation\layer` umbenannt in `Geolocation\Layer`; Aufrufe, Dateinamen und Doku angepasst. (#86, #88)
  - Klasse `Geolocation\mapset` umbenannt in `Geolocation\Mapset`; Aufrufe, Dateinamen und Doku angepasst. (#87)
  - Dateinamen an die Schreibweise der Klassen angepasst: `Box.php`, `Math.php`, `Point.php` (#50) und `Exception.php` (#73)
- Fehlerklassen neu strukturiert. **Referenzen in eigenem Code z.B. in Try-Catch müssen ggf. angepasst werden.***
  - diverse Fehlerklassen in eigene Dateien ausgelagert (#48)
  - Fehlerklasse `GeolocationInstallException` umbenannt in `Geolocation\InstallException` (#48)
  - Fehlermeldungen in `class InvalidParameters` gebündelt (#80, #81)
- Datenbank-Tabellen sind geändert. **Eigene Dataset-Dateien in `data/addons/geolocation`müssen angepasst werden**
  - Datentyp der Tabellenspalte `rex_geolocation_layer.online` von `text` in `int` geändert. Ggf. müssen eigene Datasets angepasst werden. (#77)
  - Feld "attribution" im Layer-Formular von `varchar(191` in `text` geändert. (#105)
  - zusätzliche URL für HiRes-Karten/Retina-Karten-URLs ohne '@2x'-support. (#118)
- RexStan-gesteuerte Überarbeitung aller PHP-Dateien, wodurch sich teilweise die Methoden_Aufrufe der Klassen geändert haben.
  (Level: 8, PHP: 8.0-8.2, Extensions: REDAXO Superglobals, Bleeding-Edge, Strict-Mode, Deprecation Warnings, phpstan-dba, dead code)
  und Code-Formatierung im REDAXO-Standard (#54…#62, #66, #68, #70…#72, #74…#76, #80…#82, #84, #85)
  **Referenzen in eigenem Code auf die KMethoden müssen überprüft und ggf. angepasst werden.**

Weitere Änderungen:
- Vendor-Updates:
  - phpGeo 4.2.0 (#83)
  - Leaflet 1.9.3 (#99)
  - Leaflet.GestureHandling 1.2.2 (#98)
  - AssetPacker 1.3.2 (#108)
- Neu:
  - Test der Layer-URLs interaktiv im Eingabeformular (#100)
  - Individuelles CSS (`redaxo/data/geolocation/geolocation.css') kann auch in SCSS-Dateien stehen (Editor-freundlich) (#104).
    Daher die CSS-Assets `install/geolocation_be.css` und `install/geolocation.css`in `.scss` umbenannt. (#104)
  - Für Basiskarten im Kartensatz/Mapset kann die aktive Karte unabhängig von der Reihenfolge (bisher immer die erste) per Radio-Button aktiviert werden (#107)
  - Für Overlay-Karten im Kartensatz/Mapset können sofort sichtbare Overlays aktiviert werden (Checkbox); bisher waren die Karten immer initial ausgeblendet (#107, #115)
  - Karten-URLs nun auch mit @2x-Zusatz möglich (by @xong Robert Rupf) (#110)
  - Retina-Unterstützung: Parameter `{r}` als Platzhalter für `@2x`-Kartenanforderung; zusätzliche URL für HiRes-Karten/Retina-Karten-URLs ohne '@2x'-support. (#118)  
  - `install.php`vereinfacht; nutzt nun ausschließlich `%table_prefix%` beim Import (#106) 
- Bugfix:
  - Workaround in `layer.php` für ein Typecast-Problem aus 'class dataset' (#79)
  - Farbcodes (#123456) in `Geolocation.svgIconPin(..)` jetzt korrekt URI-escaped (#69⇒#94)
  - Feld "attribution" im Layer-Formular von `varchar(191` in `text` geändert. Das Feld war zu klein. Beim Speichern gekapptes HTML kann zu Darstellungsproblemen führen. 
  - Demo-Datensätze aktuaisiert (CyclOSM-Link tot und ausgetauscht), OSM nun als Mapset "1" default statt HERE. (#105) 
- Dokumentation (/docs) aktualisiert (#92, #93)

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
