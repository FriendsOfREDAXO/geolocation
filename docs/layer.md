> **Hauptmenü**
> - [Installation und Einstellungen](install.md)
>   - [Installation](install.md)
>   - [Einstellungen](settings.md)
> - [Kartensätze verwalten](mapset.md)
> - __Karten/Layer verwalten__
> - [Proxy und -Cache](proxy_cache.md)
> - [Für Entwickler](devphp.md)
>   - [PHP Basis](devphp.md)
>   - [PHP LocationPicker](devphp1.md)
>   - [Javascript](devjs.md)
>   - [JS-Tools](devtools.md)
>   - [geoJSON](devgeojson.md)
>   - [Rechnen (PHP)](devmath.md)

# Verwaltung der Karten/Layer

Diverse Kartenanbieter stellen URLs zur Verfügung, über die Karten unterschiedlichster Inhalte
abgerufen werden können. In diesem Formular werden solche URLs mit begleitenden Parametern erfasst.
Basierend darauf werden über eine Koordinatenangabe (x, y) und dem Zoom-Faktor (z) die Kacheln bzw.
Tiles genannten Kartenschnipsel abgerufen.

Das jeweilige Präsentationsmodul (z.B. LeafletJS) errechnet x, y und z und fügt sie in die
[Proxy-URL](proxy_cache.md#url) ein. Der interne Proxy übernimmt die Umsetzung in die Abruf-URL des
Kartenanbieters.

Auch wenn nachstehend Beispiele für PHP- und JS-Scripte stehen: meist werden die Daten im
Hintergrund benutzt. Details und weitere Beispiele finden sich in den [Entwicklerseiten](devphp.md).

<a name="list"></a>
## Auflistung der Karten/Layer

Über die Auswahlliste werden die Karten verwaltet.

![Konfiguration](assets/tiles_list.jpg)

- **Karte/Layer anlegen**
  Plus(+)-Symbol in der Kopfzeile der ersten Spalte legt einen neuen Karten-Datensatz an und öffnet
  ein leeres [Formular](#formular) zur Erfassung der Daten.

- **Karte/Layer bearbeiten**
  Klick auf das Tabellen-Symbol in der linken Spalte oder auf den Button "editieren" erlaubt die
  Bearbeitung des Eintrags im [Formular](#formular).

- **Karte/Layer löschen**
  Klick auf den Button "löschen" entfernt die Karte/Layer. Die Daten dürfen nicht gelöscht werden,
  wenn der Eintrag einem [Kartensatz](mapset.md) zugeordnet, also in Benutzung ist.

- **Karten/Layer-Cache löschen**
    Klick auf den Button "Cache löschen" leert den Karten-Cache für genau diesen Kartentyp.

<a name="formular"></a>
## Kartenparameter erfassen/ändern

![Konfiguration](assets/tiles_edit.jpg)

### Karten-Parameter

- **Bezeichnung**  
    Die Bezeichnung dient als prägnante Unterscheidung z.B. in der Karten-Übersicht oder in
    Auswahlboxen für Karten. Sie muss angegeben und eindeutig sein.

- **URL**  
    Der konkrete Aufbau der URL wird vom Kartenanbieter auf dessen Webseiten kommuniziert. Innerhalb
    der URL werden die Stellen, an denen die Parameter x, y und z stehen, mit `{x}`, `{y}` und `{z}`
    markiert. Sofern der Anbieter URLs mit Sub-Domänen verarbeitet, wird die entsprechende
    Stelle mit `{s}` markiert.

    Sofern die URL die Apple-Notation für Retina-URLs (`@2x`) unterstützt, kann die Position in der URL mit `{r}`
    markiert werden. Dadurch wird automatisch die jeweils für den Browser passende Version benutzt.

    Der Test-Button ermöglicht den direkten Test der eingegebenen Url. Sofern die eingegebene Url
    formal grundlegend korrekt ist und - falls erforderlich - die Sub-Domänen angegeben sind, wird der
    Button aktiviert. Klick auf den Button sendet die Feldinhalte zum Server, der als Proxy die eigentliche
    Abfrage mit Test-Koordinaten (Konstanz am Bodensee) beim Kartenanbieter durchführt. Das Ergebnis wird
    wird in einem modalen Fenster angezeigt.
    ![Url-Test](assets/tiles_test.jpg)

- **Retina-URL**
    Falls die Standard-URL die Apple-Notation nicht unterstützt, kann in diesem Feld eine zusätzliche
    Retina-URL angegeben werden. Je nach Anbieter haben URLs z.B. 256 als Standard-Auflösung in der URL
    stehen und 512 für hochauflösende Tiles.

    Wenn bereits die Standard-Karte hoch auflösend ist, sollte keine zusätzliche Retina-Url angegeben werden.

- **Sub-Domänen**  
    Die Sub-Domänen sind einzelne Ziffern oder Buchstaben, die als Zeichenfolge eingegeben werden.
    Beim Abruf eines Tiles mit der URL wird `{s}` durch eines der Zeichen, zufällig ausgewählt,
    ersetzt.

- **Copyright**  
    Der Copyright-Text wird i.d.R. vom Kartenanbieter vorgegeben und sollte übernommen werden. In
    den von **Geolocation** erzeugten Karten wird der Text unten rechts eingeblendet.

- **Label**  
    In der Layer-Auswahl der Karte werden die Namen der Karte angezeigt. Für die verschiedenen
    Sprachen können hier die Texte eingegeben werden. Alle für Backenend (BE) bzw. Frontend (FE)
    verfügbaren Sprachen sind auswählbar. Jede Sprache darf nur einmal belegt werden.
    Wurde kein Sprachcode angegeben (z.B. null oder ''), wird zunächst versucht, nach der aktuellen
    Systemsprache (`rex_clang::getCurrent()->getCode()`) gesucht. Gibt es die Sprache nicht in der Liste,
    wird als erstes Fallback die erste Sprache in der Liste herangezogen und im Falle einer leeren Liste
    der aktuelle Name des Karten-Layers.

    ```php
    // Layer-Datensatz aufrufen
    $layer = \FriendsOfRedaxo\Geolocation\Layer::get( $layerId );
    // Label passen zur aktuellen FE/BE-Sprache ermitteln
    $label = $layer->getLabel()
    ```

- **Layer-Kategorie**  
    LeafletJS unterscheidet bei den Karten-Layern zwischen Basiskarten und Overlay-Karten.
    Der Typ der Karte wird hier ausgewählt, was zugleich die Auswahlzuordnung beim Kartensatz
    steuert.

Die Daten für den Kartenaufbau werden für eine einzelne Karte oder für einen Kartensatz (z.B. von
`$mapset->getLayerset()` so abgerufen:

```php
// Layer-Datensatz aufrufen
$layer = \FriendsOfRedaxo\Geolocation\Layer::get($id);
// Layer-Konfiguration zum Kartenaufbau abrufen
$layerConfig = $layer->getLayerConfig();
// oder Layer-Konfiguration für mehrere Layer zum Kartenaufbau abrufen (nur online)
$layerConfigSet = \FriendsOfRedaxo\Geolocation\Layer::getLayerConfigSet( [1,2,3] );
```
```
array:4 [▼
    "layer" => "1"
    "label" => "Karte"
    "type" => "b"
    "attribution" => "Map Tiles &copy; 2020 <a href="http://developer.here.com">HERE</a>"
]
```

### Cache-Parameter

Die Werte dienen bei der Steuerung des Cache-Verhaltens für den Proxy. Die Default-Werte für neue
Karten sind in den [Einstellungen](settings.md#cache) vorbelegt.

- **Aufbewahrungsdauer im Cache (TTL)**  
    In Minuten wird angegeben, wie lange eine Datei im Cache verbleibt, bevor sie gelöscht wird.
    Dabei entsprechen
    - **0 = Cache ist deaktiviert**
    - 1440 = 1 Tag
    - 10080 = 1 Woche
    - 43200 = 1 Monat (30 Tage)

    Der Maximalwert ist 130000, also ein Quartal.

- **Maximale Anzahl Dateien je Tile-Cache**  
    Um nicht insgesamt zu viel Plattenplatz mit Kartenbildern zu belegen, kann der Platz begrenzt
    werden über die Anzahl Dateien. Erst der [Cron-Job](proxy_cache.md#cron) löscht überzählige
    Dateien. Ein voller Cache (kein Plattenplatz) würde dazu führen, dass eine Datei nicht neu im
    Cache gespeichert wird. Sie wird dennoch an den Client ausgeliefert; die Proxy-Funktionalität
    ist nicht beeinträchtigt (nur [Proxy](proxy_cache.md#proxy) ohne [Cache](proxy_cache.md#cache)).  

### Weitere Parameter

- **Freigabe**  
    Über den Parameter wird festgelegt, ob die Karte freigegeben ist zur Nutzung oder nicht.
    Der Status kann mit `\FriendsOfRedaxo\Geolocation\Layer::get($id)->isOnline()` abgefragt werden. Abrufe des
    Layerset mit `\FriendsOfRedaxo\Geolocation\Layer::get($id)->getLayerConfigSet(...)` bzw. mit indirekt über
    `\FriendsOfRedaxo\Geolocation\Mapset::take( $mapsetId )->getLayerset()` berücksichtigt den Status.

    Auch deaktivierte Karten können dem Kartensatz zugeordnet sein!

<a name="cache"></a>
## Cache löschen

Der rote Button "Cache löschen" oben rechts in den Abbildungen ist i.R. nur für Administratoren
freigegeben. Klick auf den Button löscht alle Caches und hat damit Performance-Auswirkungen.
