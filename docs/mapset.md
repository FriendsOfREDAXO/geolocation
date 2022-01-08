> - Installation und Einstellungen
>   - [Installation](install.md)
>   - [Einstellungen](settings.md)
> - Kartensätze verwalten
> - [Karten/Layer verwalten](layer.md)
> - [Karten-Proxy und -Cache](proxy_cache.md)
> - [Für Entwickler](devphp.md)
>   - [PHP](devphp.md)
>   - [Javascript](devjs.md)
>   - [JS-Tools](devtools.md)
>   - [geoJSON](devgeojson.md)
>   - [Rechnen](devmath.md)

# Verwaltung der Kartensätze

**Hinweis:** Wenn das Addon als reines [Proxy-Addon](install.md#proxy) konfiguriert ist, ist die
Verwaltung der Kartensätze ausgeblendet.

Kartensätze basieren auf einer oder mehreren Karten, die per Kartenanbieter-URLs bereitgestellt
werden. Jede URL bietet Zugriff auf einen Kartentyp, der auf der Karte als Layer erscheint.
Beispiel: Standardkarte, Satellitenbild, Kombination aus beidem.

URLs / Karten / Layer werden im Bereich [Karte](layer.md) verwaltet.

Auch wenn nachstehend Beispiele für PHP- und JS-Scripte stehen: meist werden die Daten im
Hintergrund benutzt. Details und weitere Beispiele finden sich in den [Entwicklerseiten](devphp.md).

## Auflistung der Kartensätze

Über die Auswahlliste werden die Karten verwaltet.

![Konfiguration](assets/maps_list.jpg)

- **Kartensatz anlegen**
  Plus(+)-Symbol in der Kopfzeile der ersten Spalte legt einen neuen Kartensatz an und öffnet ein
  leeres [Formular](#formular) zur Erfassung der Daten.

- **Kartensatz bearbeiten**
  Klick auf das Tabellen-Symbol in der linken Spalte oder auf den Button "editieren" erlaubt die
  Bearbeitung des Eintrags im [Formular](#formular).

- **Kartensatz löschen**
  Klick auf den Button "löschen" entfernt den Kartensatz. Die Daten dürfen nicht gelöscht werden,
  wenn der Kartensatz in Benutzung ist.

- **Kartensatz löschen**
    Klick auf den Button "Cache löschen" leert den Karten-Cache für alle Karten/Layer in diesen
    Kartensatz.

<a name="formular"></a>
## Kartensätze zusammenstellen/ändern

![Konfiguration](assets/maps_edit.jpg)

- **Name**  
    > Der Name wird in späteren Versionen evtl. genutzt für JS-Klassen. Er muss schon heute angegeben
    > und eindeutig sein.

- **Titel**  
    Der Titel dient in der Kartensatz-Übersicht oder in Auswahlboxen für Kartensätze als prägnante
    Unterscheidung. Er muss angegeben und eindeutig sein.

- **Basiskarten / Overlay-Karten**  
    Die Unterscheidung von Basiskarten und Overlay-Karten reflektiert das Leaflet-Konzept. Wird
    **Geolocation** als Proxy für Karten eines anderen JS-Umfeldes genutzt, ist die Unterscheidung
    möglicherweise hinfällig. Nur wenn hier mehrere Karten ausgewählt sind, steht auch das
    Layer-Control zur Kartenauswahl in der Leaflet-Map zur Verfügung.

    In einer Leaflet-Map kann immer nur ein Basiskarten-Layer aktiv sein. Im Unterschied dazu können
    mehrere Overlay-Karten auf dem aktuellen Basis-Layer übergeblendet werden.

    Das Widget bietet die Auswahl von als Basiskarte bzw. als Overlay-Karte markierten
    Karten und deren Anordnung (Reihenfolge). In genau der Reihenfolge würden die Layer auf der
    Leaflet-Map bereitgestellt, der erste Basis-Layer wird zuerst aktiviert.

    Deaktivierte Karten/Layer können ausgewählt werden, sind aber nicht im Datensatz enthalten

    ```php
    // Mapset abrufen (::get(id) bzw. ::take())
    $mapset = \Geolocation\mapset::get( $mapsetId );
    // Kartendaten (Layer) abrufen
    $layerset = $mapset->getLayerset();
    ```
    Die Layer (`$layerset`) werden als Array bereitgestell. Hier ein Beispiel:
    ```
    array:3 [▼
        0 => array:4 [▼
            "layer" => "geolayer=1"
            "label" => "Karte"
            "type" => "b"
            "attribution" => "Map Tiles &copy; 2020 <a href="http://developer.here.com">HERE</a>"
        ]
        1 => array:4 [▼
            "layer" => "geolayer=2"
            "label" => "Satelit"
            "type" => "b"
            "attribution" => "Map Tiles &copy; 2020 <a href="http://developer.here.com">HERE</a>"
        ]
        2 => array:4 [▼
            "layer" => "geolayer=3"
            "label" => "Hybrid"
            "type" => "b"
            "attribution" => "Map Tiles &copy; 2020 <a href="http://developer.here.com">HERE</a>"
        ]
    ]
    ```

- **Kartenoptionen**  
    Die von **Geolocation** erzeugten Karten können mit unterschiedlichen Zusatzfunktionen versehen
    werden. Die Standardwerte für alle Karten sind in den allgemeinen [Einstellungen](settings.md)
    vorbelegt.

    Hier kommt eine zusätzliche Checkbox **"Default; Werte aus Einstellungen"** in´s Spiel.
    Damit kann ausdrücklich die Grundeinstellung aktiviert werden; ändert sich die Grundeinstellung
    gilt es auch für diesen Kartensatz.

    ```php
    // Mapset abrufen (::get(id) bzw. ::take())
    $mapset = \Geolocation\mapset::take();
    // Liste der Optionen abrufen ('|fullscreen|gestureHandling|locateControl|')
    $mapOptions = $mapset->getMapOptions();
    ```


- <a name="mapset_out"></a>**Ausgabe-Fragment**  
    PHP-seitig verfügt **Geolocation** über einfach zu nutzende Methoden, das Karten-HTML zu
    generieren. Dabei wird i.d.R. das hier für diesen Kartensatz angegebene Fragment genutzt. Bleibt
    das Feld leer wird statt dessen das in den [allgemeinen Einstellungen](settings.md) hinterlegte
    Standard-Fragment genutzt.

    Der Name muss auf `.php` enden.

    ```php
    // Mapset abrufen (::get(id) bzw. ::take())
    $mapset = \Geolocation\mapset::take( $mapsetId );
    // Ausgabe-Fragment abrufen inkl. Fallback
    $outFragment = $mapset->getOutFragment();
    ```

<a name="cache"></a>
## Cache löschen

Der rote Button "Cache löschen" oben rechts in den Abbildungen ist i.R. nur für Administratoren
freigegeben. Klick auf den Button löscht alle Caches und hat damit Performance-Auswirkungen.
