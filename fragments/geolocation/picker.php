<?php

/**
 * allgemeines Fragment zum Aufbau eines Location-Pickers.
 *
 * Der Picker bietet:
 * -Teil des Geolocation-Addons und daher Nutzung der Tools und Einstellungen
 * - Geolocation-Kartensätze für die Kartendarstellung
 * - Ein Eingabefeld für die Suche nach Adressen
 * - Eingabefelder für die Eingabe bzw. Sichtbarmachung der Koordinaten (Lat,Lng)
 * - Außerhalb dieses Values liegende Lat-/Lng-Felder nutzen
 * - optische Gestaltungsmöglichkeiten (Pin-Farbe, Kreis um den Marker)
 *
 * - HTML-Attribute werden i.d.R. per rex_string::buildAttributes aufbereitet.
 * - Sub-Elemente wie Input-Groups oder Button werden über die Core-Fragmente erzeugt
 *
 * Daten im Fragment:
 * 
 * Für das Gesamt-Element (Klammer, <geolocation-geopicker>)
 *  - field_id              Optional: HTML-ID der Klammer
 *  - field_class           Optional: HTML-Klasse für die Klammer
 * 
 * Für die Darstellung der Karte an sich
 *  - mapset_id             Optional. ID des Kartensatzes (Geolocation/Mapset bzw. Mapset-Default)
 *  - mapset_class          Optional. Falls eine andere Kartengröße (Höhe/Breite etc.) gewünscht ist
 *  - radius            *   Optional. Radius um eine konkrete Position. Def. aus Config «picker_radius»
 *  - void_bounds       *   Optional. Kartenausschnitt wenn es keine gültige Position gibt, Def aus Config «picker_bounds»
 *                          => Box !!!
 *
    * //kann weg!! Für die Kartenpositionierung beim Aufbau der Karte (wird nicht im Fragment berechnet!)
    *  - bounds            ?   Optional. Array [[NW],[SE]]. Fallback auf void_bounds
    *  - marker            ?   Optional. Wenn angegeben den Marker auf diese Position initialisieren.
 *                                       In dem Fall über Radius die Bounds ermitteln
 *
 * Für die Zuordnung bzw. den Aufbau auf Lat/Lng-Felder
 *  - type                  Optional: externe Felder mit LatLng nutzen ('external'); default: intern
 *  - latlng_id         *   Array [lat=>,lng=>] mit den Feld-IDs der externen LatLng-Felder; bei internen Feldwern optional
 *  - latlng_name           Array [lat=>,lng=>] mit dem name-Attribut für interne Felder. Bei externen Feldern irrelevant.
 *  - latlng_value          Array [lat=>,lng=>] mit dem Value für interne Felder. Bei externen Feldern irrelevant.
 *                          => Point !!!
 *
 * Für die Suche nach Adressen via Url (nicht anzeigen wenn resolver_url kein String)
 *  - resolver              Optional. Url zur Adressauflösung oder '' für Def-Url aus Config «resolver_url»
 *  - resolver_mapping      Optional. Ergebnisdaten in Template-Felder (lat, lng, label) unsetzen. Default aus Config «picker_bounds»
 * ==> jetzt nur noch "geo_coder. Wenn der fehlt: default-GeoCoder laden
 *  - address_fields        Optional. ID und Label (Key/Value) von Feldern mit Adress-Teilen
 *
 * * => wird als Konfiguration an <geolocation-geopicker> weitergegeben
 * ? => prüfen, ob der wieklich benötigt wird (Verweis auf latlng_value)
 * 
 * Wenn «latlng_value» konkrete Koordinaten hat, müssen sich diese auch in «bounds» und «marker» wiederfinden.
 * Bounds muss zudem 
 */


            /**
             * TODO: Prüfen wohin damit. Kann das Weg oder muss das in das Fragment?
             * /*
             * Den Location-Picker als zentralen Feldbestandteil generieren
             * Für das Gesamt-Element (Klammer, <geolocation-geopicker>)
             *  - field_id              Optional: HTML-ID der Klammer
             *  - field_class           Optional: HTML-Klasse für die Klammer.
             *
             * Für die Darstellung der Karte an sich
             *  - mapset_id             Optional. ID des Kartensatzes (Geolocation/Mapset bzw. Mapset-Default)
             *  - mapset_class          Optional. Falls eine andere Kartengröße (Höhe/Breite etc.) gewünscht ist
             *  - radius            *   Optional. Radius um eine konkrete Position. Def. aus Config «picker_radius»
             *  - void_bounds       *   Optional. Kartenausschnitt wenn es keine gültige Position gibt, Def aus Config «picker_bounds»
             *
             * //kann weg!! Für die Kartenpositionierung beim Aufbau der Karte (wird nicht im Fragment berechnet!)
             *  - bounds            ?   Optional. Array [[NW],[SE]]. Fallback auf void_bounds
             *  - marker            ?   Optional. Wenn angegeben den Marker auf diese Position initialisieren.
             *
             * Für die Zuordnung bzw. den Aufbau auf Lat/Lng-Felder
             *  - type                  Optional: externe Felder mit LatLng nutzen ('external'); default: intern
             *  - latlng_id         *   Array [lat=>,lng=>] mit den Feld-IDs der externen LatLng-Felder; bei internen Feldwern optional
             *  - latlng_name           Array [lat=>,lng=>] mit dem name-Attribut für interne Felder. Bei externen Feldern irrelevant.
             *  - latlng_value          Array [lat=>,lng=>] mit dem Value für interne Felder. Bei externen Feldern irrelevant.
             *
             * Für die Suche nach Adressen via Url (nicht anzeigen wenn resolver_url kein String)
             *  - resolver              Optional. Url zur Adressauflösung oder '' für Def-Url aus Config «resolver_url»
             *  - resolver_mapping      Optional. Ergebnisdaten in Template-Felder (lat, lng, label) unsetzen. Default aus Config «picker_bounds»
             *  - address_fields        Optional. ID und Label (Key/Value) von Feldern mit Adress-Teilen
             */


namespace FriendsOfRedaxo\Geolocation;

use FriendsOfRedaxo\Geolocation\Calc\Box;
use FriendsOfRedaxo\Geolocation\Calc\Point;
use rex_config;
use rex_fragment;
use rex_i18n;
use rex_string;

/** @var rex_fragment $this */

/**
 * Werte aus dem Fragment: Für das Gesamt-Element (Klammer, <geolocation-geopicker>)
 */
$fieldId = (string) $this->getVar('field_id', '');
$fieldClass = (array) $this->getVar('field_class', []);
$fieldClass = array_merge($fieldClass, ['form_control']);
$fieldClass = array_unique($fieldClass);

/**
 * Werte aus dem Fragment: Für die Darstellung der Karte an sich.
 */
$mapsetId = (int) $this->getVar('mapset_id', 0);
$mapsetClass = (string) $this->getVar('mapset_class', '');
$radius = (int) $this->getVar('radius', 0);
if (0 === $radius) {
    $radius = (int) rex_config::get('geolocation', 'picker_radius');
}


/*
$voidBounds = (array) $this->getVar('void_bounds', []);
if ([] === $voidBounds) {
    $v = sprintf('[%s]',rex_config::get('geolocation', 'map_bounds'));
    $voidBounds = json_decode($v ,true);
}
*/
/** @var ?Box $voidBounds */
$voidBounds = $this->getVar('void_bounds', null);
if (null === $voidBounds) {
    try {
        $v = sprintf('[%s]',rex_config::get('geolocation', 'map_bounds'));
        $b = json_decode($v ,true);
        $voidBounds = Box::byCorner(Point::byLatLng($b[0]),Point::byLatLng($b[1])); 
    } catch (\Throwable $th) {
        $voidBounds = null;
    }
}

/**
 * Werte aus dem Fragment: Für die Zuordnung auf Lat/Lng-Felder.
 *
 *  - latlng_id             Array [lat=>,lng=>] mit den Feld-IDs der externen LatLng-Felder; bei internen Feldwern optional
 *  - latlng_name           Array [lat=>,lng=>] mit dem name-Attribut für interne Felder. Bei externen Feldern leer.
 *  - latlng_value          Array [lat=>,lng=>] mit dem Value für interne Felder. Bei externen Feldern leer.
 *                          Point !!!!
 * 
 * @var ?Point $latLngValue
 */
$latLngValue = $this->getVar('latlng_value', null); // [];
$type = (string) $this->getVar('type', 'intern');
$latLngId = (array) $this->getVar('latlng_id', []);
if ('external' === $type) {
    $latLngName = [];
} else {
    if (0 === \count($latLngId)) {
        $base = '' === $fieldId ? uniqid('geolocation-') : $fieldId;
        $latLngId = [
            'lat' => $base . '-lat',
            'lng' => $base . '-lng',
        ];
    }
    $latLngName = (array) $this->getVar('latlng_name', []);
}

/**
 * Bei internen Feldern für LatLng ($type != extern) werden hier zwei Felder aufgebaut und befüllt.
 * Die Felder werden in einer Zeile (row) angeordnet.
 */
$latLngInputFields = '';
if ('external' !== $type) {
    $latLngFields = [];
    $error = (array) $this->getVar('error', []);

    $fieldAttributes = [
        'id' => $latLngId['lat'],
        'class' => 'form-control',
        'type' => 'number',
        'step' => 'any',
        'min' => -90,
        'max' => 90,
        'value' => null === $latLngValue ? '' : $latLngValue->lat(), //$latLngValue['lat'],
        'name' => $latLngName['lat'],
        'autocomplete' => 'off',
        'placeholder' => rex_i18n::msg('geolocation_picker_lat_placeholder'),
    ];

    $n = [
        'left' => rex_i18n::msg('geolocation_lat_label'),
        'field' => '<input' . rex_string::buildAttributes($fieldAttributes) . '/>',
    ];
    if( isset($error['lat']) ) {
        $n['class'] = '" style="border:1px solid red';
    }

    $fragment = new rex_fragment();
    $fragment->setVar('elements', [$n], false);
    $latLngFields[] = $fragment->parse('core/form/input_group.php');

    $fieldAttributes = [
        'id' => $latLngId['lng'],
        'class' => 'form-control',
        'type' => 'number',
        'step' => 'any',
        'min' => -180,
        'max' => 180,
        'value' => null === $latLngValue ? '' : $latLngValue->lng(), //$latLngValue['lng'],
        'name' => $latLngName['lng'],
        'autocomplete' => 'off',
        'placeholder' => rex_i18n::msg('geolocation_picker_lng_placeholder'),
    ];

    $n = [
        'left' => rex_i18n::msg('geolocation_lng_label'),
        'field' => '<input' . rex_string::buildAttributes($fieldAttributes) . '/>',
    ];
    if( isset($error['lng']) ) {
        $n['class'] = '" style="border:1px solid red';
    }

    $fragment->setVar('elements', [$n], false);
    $latLngFields[] = $fragment->parse('core/form/input_group.php');
    // $latLngInputFields .= \sprintf('<div class="col-lg-6">%s</div></div>', $fragment->parse('core/form/input_group.php'));

    $fragment = new rex_fragment();
    $fragment->setVar('content', $latLngFields, false);
    $latLngInputFields = $fragment->parse('core/page/grid.php');
}

/**
 * Das Element zur Eingabe einer Suchadresse bzw. deren Übernahme aus Adress-Feldern.
 * Wenn nicht "string", dann keinen Address-Resolver einbauen.
 * Die Angaben werden an das Sub-Fragment durchgeleitet, da die relevanten Variablen identisch sind.
 */

$geoCoder = '';
if (\is_string($this->getVar('resolver', false))) {
    $geoCoder = $this->getSubfragment('geolocation/geocoder.php');
}

/**
 * Die Karte mit einem Positionsmarker vorbereiten.
 * Es gibt zwei Varianten:
 * - Marker-Position ist bekannt ($latLngValue) als Point-Objekt
 *   Position als Array [lat,lng] weiterreichen an das JS
 *   Der Marker sollte unbedingt auf diese Position gestellt werden
 * - Marker-Position ist nicht bekannt (null)
 *   Null an das JS weiterreichen, dass dann die Karte auf den Default setzt.
 * 
 * Der Default-Kartenausschnitt wird entweder durch das Box-Objekt $voidBounds
 * definiert oder ist null. In dem Fall Fallback des JS auf die Default-Karte.
 */
$markerStyle = $this->getVar('marker_style','');
$dataset = [
    $latLngValue === null ? null : $latLngValue->latLng(),
    $radius,
    $voidBounds === null ? null : $voidBounds->latLng(),
    $markerStyle === '' ? null : json_decode($markerStyle,true),
];

$map = Mapset::take($mapsetId)
    ->dataset('locationpicker',$dataset)
    ->attributes('class', $mapsetClass);

/**
 * Attribute für <gelolocation-geopicker>, mit denen der Picker
 * konfiguriert und gesteuert wird.
 */
$pickerAttributes = [
    'class' => implode(' ', $fieldClass),
    'config' => json_encode([
        'coordFld' => [
            'lat' => $latLngId['lat'],
            'lng' => $latLngId['lng'],
        ],
        'marker' => 'locationpicker',
    ]),
];
if ('' < $fieldId) {
    $pickerAttributes['id'] = $fieldId;
}

/**
 * Ausgabe des HTML-Codes.
 */
?>
<geolocation-geopicker <?= rex_string::buildAttributes($pickerAttributes) ?>>
    <?= $geoCoder ?>
    <?= $latLngInputFields ?>
    <?= $map->parse() ?>
</geolocation-geopicker>

