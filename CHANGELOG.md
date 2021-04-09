# Changelog

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
