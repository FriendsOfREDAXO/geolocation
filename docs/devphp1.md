> - [Installation und Einstellungen](install.md)
>   - [Installation](install.md)
>   - [Einstellungen](settings.md)
> - [Kartensätze verwalten](mapset.md)
> - [Karten/Layer verwalten](layer.md)
> - [Karten-Proxy und -Cache](proxy_cache.md)
> - Für Entwickler
>   - [PHP Basis](devphp.md)
>   - __PHP LocationPicker__
>   - [Javascript](devjs.md)
>   - [JS-Tools](devtools.md)
>   - [geoJSON](devgeojson.md)
>   - [Rechnen (PHP)](devmath.md)

# Für Entwickler &dash; PHP LocationPicker

Diese ausführliche Bescgreibung gilt einem Wisget, dass in verschiedenen Zusammenhängen als Eingabeelement
bzw. Auswahlelement für Koordinaten dient. Grundlage ist ein klassisches Redaxo-Fragment (Datei
`.../fragment/geolocation/picker.php`). Dazu gehört eine Klasse `PickerWidget`, die auf `rex_fragment`
basiert und zahlreiche Get- und Set-Methoden zur sicheren Eingabe der Parameter und adäquatem Fallback
auf Standardwerte bereithält.

Das Widget bietet
- Marker auf die aktuelle GPS-Position setzen (sofern im Browser freigegeben)
- Marker per Doppel-Klick auf die gewählte Position legen
- Marker per Maus verschieben
- direkte Koordinateneingabe in Eingabefeldern für Breitengrad und Längengrad
- Per Geocoding-Service Koordinaten zu Adressen suchen lassen (optional)
- Adressen für die Suche aus Adressfelern im Formular auslesen (optional)

Wie die Speicherung der Daten erfolgt, wie die Validierung und vieles mehr varriert je nach
Verwendungszweck und wird in den nachfolgenden Kapiteln beschrieben.

## Beispieldaten

Um die vielfältigen Konfigurationsvarienten anschaulich zeigen zu können, wird stets mit denselben
Beispieldaten gearbeitet. Hier die Beschreibung:

### Basiskartenauschnitt
Wenn keine gültige Koordinate vorliegt, wird ein Basiskartenauschnitt ausgewählt. Das ist entweder die
in Geolocation hinterlegte Basiskarte (z.B. "Europa") oder ein individueller anderer Auschnitt. Das Beipiel hier ist
Berlin:
```php
use FriendsOfRedaxo\Geolocation\Calc\Box;
use FriendsOfRedaxo\Geolocation\Calc\Point;

$baseBounds = Box::byCorner(
    Point::byLatLng([52.69235,13.05804]),
    Point::byLatLng([52.32282,13.79644])
);
```

### Koordinate

Als Beispielpunkt dient die Siegessäule in Berlin
```php
use FriendsOfRedaxo\Geolocation\Calc\Point;

$goldElse = Point::byLatLng([52.514516,13.350110]);
```

### Location-Picker umgestalten

An der grundsätlichen Auslegnung (Pin in der Mitte, Kreis drumherum) kann man nichts ändern. Aber die Farben lassen sich anpassen
bis hin zu einem unsichtbaren Kreis.
```php
$markerStyle = [
    'pin' => [
        'color' => 'OrangeRed',
    ],
    'circle' => [
        'color' => 'OrangeRed',
        'weight' => 1,
        'fillOpacity' => 0.1,
    ],
];
```
Mit `$markerStyle['circle']['weigt']=0` verschwindet der äußere Kreis und mit `$markerStyle['circle']['fillOpacity']=0`
verschwindet die Innenfläche.



<a name="fragment"></a>
## Basis: Das Fragment

Die Schnittstelle zum Fragment `.../fragment/geolocation/picker.php` bildet die Klasse `PickerWidget`. Im einfachsten Fall, also
unter Nutzung aller Default-Einstellungen, wird ein Widget erzeugt, dass über interne, selbst generierte Eingabefelder für
Breiten- und Längengrad verfügt. 

Die Minderstangabe ist der Typ (interne oder externe Eingabefelder) und entweder den Namen (intern: name-Attribut für den Input-Tag)
oder die HTML-Id (extern: ID als Referenz zum Aufinden der Felder)

```php
use FriendsOfRedaxo\Geolocation\PickerWidget;

$geoPicker = PickerWidget::factoryInternal (
    'koordinate[lat]', 
    'koordinate[lng]',
);
echo $geoPicker->parse();
```
![Konfiguration](assets/picker01.jpg)

Bei der Nutzung externer Felder ist es ähnlich, allerdings werden die Felder über ihre HTML-Id identifiziert und stehen außerhalb des Widgets

```php
use FriendsOfRedaxo\Geolocation\PickerWidget;

$geoPicker = PickerWidget::factoryExternal (
    'koordinate-lat', 
    'koordinaten-lng',
);
echo $geoPicker->parse();
```

Über weitere Methoden kann das Widget angepasst werden:

- `setLocation(?Point $location = null)` (dringend empfohlen)

   Setzt die initiale Position der Karte. Wenn es bereits eine gespeicherte Koordinate gibt,
   wird sie hierrüber und **nicht** über den Inhalt der Eingabefelder an das Widget gegeben.
   
- `setContainer(string $id = '', array|string $class = [])`

   Gibt dem  Widget-Container (Custom-HTML-Tag `<geolocation-geopicker>`) eine ID und eine Klasse zuweisen

- `setMapset(int $mapset = 0, string $id = '', array|string $class = [])`

   Hier wird die ID des Geolocation-Kartensatzes erwartet und ggf um eigene Kartenformatierungen (meist Höhe/Breite/Rand)
   eine Klasse. I.d.m. Fällen kann man auf den Default setzen.

- `setBaseBounds(?Box $voidBounds = null)`

   Setzt den initialen Kartenausschnitt

- `setLocationMarker(int $radius = 0, array $style = [])`

   Gibt dem Marker alternative Optionen mit, siehe Beispiel oben. Der Radius von 0 bedeutet "Standard"

- `setLocationRange(?Box $range = null, bool $required=false)`

   Gibt einen Kartenauschnitt an, innerhalb dessen am Ende die ausgewählte Koordinate liegen muss. Über den Parameter
   `$required` kann den Eingabefeldern auch das Required-Attribut hinzugefügt werden. In dem Fall MUSS eine Koordinate
   eingegeben werden.

- `setGeoCoder(?int $geoCoder = null)`

   Wenn die ID eines GeoCoders angegeben (egal ob existent oder nicht), wird die Funktion für das GeoCoding aktiviert.

- `setAdressFields(array $addressFields = [])`

   Wenn das GeoCoding aktiviert ist, kann über das Array eine Reihe von Eingabefeldern für Adress-Bestandteile angegeben werden.
   ([HTML-Id => Label]). In dem Fall erhält de GeoCoder einen Button, mit dem die Inhalte der Felder für die Suche benutzt werden.

- `setLatError(string $errorClass, string $error = '')`

   Relevant nach der Formularrückgabe. Hat die Rückgabeauswertung Breitengrad-Fehler ergeben, werden die Fehlerklasse (`has-error`) und ggf. ein
   Fehlertext bei der Wiget-Ausgabe berücksichtigt (nur sinnvoll bei internen Eingabefeldern; externe Felder müssen eigenständig formatiert werden). 

- `setLngError(string $errorClass, string $error = '')`

   siehe setLatError, aber für Längengrad-Fehler

Und hier ein Beispiel:

```php
use FriendsOfRedaxo\Geolocation\PickerWidget;

$geoPicker = PickerWidget
    ::factoryInternal ('koordinate[lat]', 'koordinate[lng]')
    ->setLocation($goldElse)
    ->setMapset(2)
    ->setBaseBounds($baseBounds)
    ->setLocationMarker(250,$markerStyle)
    ->setGeoCoder(0)
    ->setAdressFields(['field-01' => 'Straße', 'field-02' => 'Ort'])
    ;

echo $geoPicker->parse();
```
![Konfiguration](assets/picker02.jpg)


<a name="yform"></a>
## als YForm-Value `rex_yform_value_geolocation_geopicker`

### Konfiguration und Features

Das YForm-Value wird prinzipiell wie die beschrieben mittels des PickerWidget aufgebaut. Die auf der
Konfigurationsseite eingegebenen Parameter werden entweder direkt an das PickerWidget gegeben oder
den YForm-Kontext berücksichtigend Feld- und Formulardaten abgeleitet.

Z.B. werden die IDs externer Breitengrad/Längengrad-Felder nicht direkt eingegeben, sondern die Felder
aus der Feldliste ausgewählt. Die tatsächliche ID ermittelt das Value selbst

Um das Picker-Widget herum wird ein "übliches" YForm-Feld-HTML aufgebaut.

Die Daten können auf drei Arten gespeichert werden:
- intern im Format "lat,lng", also komma-separiert zwei Dezimalzahlen zunächst mit dem Breitengrad und danach
  mit dem Längengrad.
- intern im Format "lng,lat", also komma-separiert zwei Dezimalzahlen zunächst mit dem Längengrad und danach
  mit dem Breitengrad.
- in zwei externen Feldern; jeweils eines für den Längengrad und eines für den Breitengrad.

Die Validierung erfolgt für interne wie externe Felder innerhalb des YForm-Values. Weder das Picker-Value noch die
externen Koordinaten-Felder sollten daher mit weitern Validatoren versehen werden! Fehler bezgl. externer Felder
werden dem jeweiligen externen Feld zugeordnet, bei internen Feldern wird das jeweilige interne Feld markiert.

### Listen

Die Anzeige in Listen kann aktiviert werden. In dem Fall stehen drei Darstellungsvarianten für die Koordinaten
zur Verfügung. Dabei wird nicht zwischen internen oder externen Koordinatenfeldern unterschieden. Im Falle
externer Felder sollten diese für die Liste deaktiviert werden.

![Listenformat](assets/picker_yf_list.jpg)

### Suche

Im YForm-Suchfenster kann ein einfaches Eingabefeld für die Umkreissuche eingeblendet werden. Als Eingabe werden drei Werte erwartet:

- Breitengrad (als Dezimalwert)
- Längengrad (als Dezimalwert)
- Umkreis-Radius (in Meter)

Beispiel: `52.514516 13.350110 1000`

Anstelle eines Dezimalpunktes (PHP-internes Firmat) kann man auch ein Komma setzen (`52,514516 13,350110 1000`).

Auch hier übernimmt das Value die Suche in den externen Feldern.

> **Hinweis**: Die Suche nutzt die seit einnigen Jahren in Datenbankabfragen verfügbaren Spatial-Funktionen in [Mysql](https://dev.mysql.com/doc/refman/8.4/en/spatial-types.html)
(Version 5.7 seit 10/2015) und [MariaDB](https://mariadb.com/kb/en/geographic-geometric-features/) (Version 10.5.10 seit 12/2019). Bei älteren Versionen
ist die Umkreissuche nicht verfügbar.

Die GeoCoder-Klasse bietet statische Methoden an, die möglicherweise auch in eigenen Anwendungen hilffeich sind bim Aufbau von Umkreissuchen:

```php
use FriendsOfRedaxo\Feolocation\GeoCoder;

// Die YOrm-Query
$query = MyDatasetClass:.query();
$tableAlias = $query->getTableAlias();

// Die Suchdaten
$lat = 52.514516;
$lng = 13.350110;
$radius = 1000;

// Tabellenfeld mit der Koordinate im Format «breitengrad,längengrad»
$latLngField = 'location';
$where = GeoCoder::circleSearchLatLng($lat, $lng, $radius, $latLngField, $tableAlias);

// Alternativ: Tabellenfeld mit der Koordinate im Format «längengrad,breitengrad»
$lngLatField = 'location';
$where = GeoCoder::circleSearchLngLat($lat, $lng, $radius, $lngLatField, $tableAlias);

// Alternativ: Getrennte Tabellenfelder für Breiten- und Längengrad
$latField = 'latitude';
$lngField = 'longitude';
$where = GeoCoder::circleSearch($lat, $lng, $radius, $latField, $lngField, $tableAlias);

// Umkreissuche zur Query hinzufügen
$query->whereRaw($where);

```

<a name="rexform"></a>
## als RexForm-Element

RexForm-Formulare können um eigene Feldtypen ("Elemente") erweitert werden, auch wenn es etwas kompliziert erscheint.
Die Klasse `PickerElement' stellt ein solches Formular-Element zur Verfügung, dass ähnlich wie das [YForm-Value](#yform)
ausgestaltet ist:

- Speicherung der Koordinate intern als Array `['lat'=>...,'lng' => ...]`
- Speicherung der Koordinate extern in zwei Einzelfeldern
- Validierung inklusive (Bereich, nicht Leer)

Die Konfiguration des PickerWidget erfolgt im Kapitel zum [Fragment](#fragment) beschrieben. Der Picker wird über die
Methode `$field->setPickerWidget()` aktiviert. Je nach Parametrisierung entsteht ein Widget mit interner oder externer Speicherung.

**Beispiel: Interne Speicherung im eigenen Feld als Array**

```php
use FriendsOfRedaxo\Geolocation\PickerElement;

// Feld "koordinate" als Picker-Element anlegen
$pickerField = $this->addField('', 'koordinate', null, ['internal::fieldClass' => PickerElement::class], true);
$pickerField->setLabel('Location');

// PickerWidget für die interne Speiicherung aktivieren
$geoPicker = $pickerField->setPickerWidget();

// Picker-Widget konfogurieren
$geoPicker->setMapset(2);
$geoPicker->setGeoCoder(1);
$geoPicker->setBaseBounds($baseBounds);
```

**Beispiel: Externe Speicherung in separaten Feldern für Breitengrad und Längengrad**

Die Instanzen der beiden Felder werden mit `SetPickerWidget` an das Picker-Feld übergeben.
Daraus leitet der Picker die intern nötigen Daten (z.B. die ID) ab bzw. entnimmt den Wert
für die initialisierung der Karte. 

Optional werden die Felder ähnlich wie die internen Eingabefelder konfiguriert
(`type="number"`, `min="..."`, `max="..."` usw.).

```php
use FriendsOfRedaxo\Geolocation\PickerElement;

// Eingabefelder für Breiten- und Längengrad
$latField = $field = $this->addTextField('lat');
$field->setLabel('Breitengrad');

$lngField = $field = $this->addTextField('lng');
$field->setLabel('Längengrad');

// Feld "koordinate" als Picker-Element anlegen
$pickerField = $this->addField('', 'koordinate', null, ['internal::fieldClass' => PickerElement::class], true);
$pickerField->setLabel('Location');

// PickerWidget für die interne Speicherung aktivieren
$geoPicker = $pickerField->setPickerWidget($latField, $lngField, true);

// Picker-Widget konfigurieren
$geoPicker->setMapset(2);
$geoPicker->setGeoCoder(1);
$geoPicker->setBaseBounds($baseBounds);
```

<a name="modul"></a>
## In Modulen


<<<<< draft >>>>>


