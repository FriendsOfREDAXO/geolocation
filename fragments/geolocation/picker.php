<?php

/**
 * allgemeines Fragment zum Aufbau eines Location-Pickers.
 *
 * Dieses Fragment nicht mit einer normalen Instanz von rex_fragemnt aufrufen!
 * Dieses Fragment nur aus einer Instanz von PickerWidget (extends rex_fragment)
 * aufrufen. So was wie Defaults sicherstellen etc., also was noch mit Logik zu tun
 * hat, ist dort angesiedelt (na ja, 99%).
 *
 * - HTML-Attribute werden i.d.R. per rex_string::buildAttributes aufbereitet.
 * - Sub-Elemente wie Input-Groups oder Button werden über die Core-Fragmente erzeugt
 */

namespace FriendsOfRedaxo\Geolocation;

use FriendsOfRedaxo\Geolocation\Picker\PickerWidget;
use rex_i18n;
use rex_string;

/** @var PickerWidget $this */
if (!is_a($this, PickerWidget::class)) {
    throw new DeveloperException('This fragment ist designed to work only called by ' . PickerWidget::class);
}

/**
 * Werte aus dem Fragment: Für das Gesamt-Element (Klammer, <geolocation-geopicker>).
 */
$fieldId = $this->getContainerId();
$fieldClass = ['form-control'];

/**
 * Werte aus dem Fragment: Für die Darstellung der Karte an sich.
 */
$voidBounds = $this->getBaseBounds();

/**
 * Marker-Konfiguration.
 */
$radius = $this->getLocationMarkerRadius();
$style = $this->getLocationMarkerStyle();
$markerBounds = $this->getLocationMarkerRange();

/**
 * Code für den GeoCoder falls erforderlich.
 */
$geoCoderHtml = '';
if ($this->hasGeoCoding()) {
    $geoCoderHtml = $this->getSubfragment(
        'geolocation/geocoder.php',
        [
            'geo_coder' => $this->getGeoCoder(),
            'address_fields' => $this->getAddressFields(),
        ],
    );
}

/**
 * Optional notwendige Eingabefelder für Lat/Lng (type <> external) anlegen.
 */
$latLngInputHtml = '';
if (!$this->useExternalInput()) {
    $inputAttributes = [
        'id' => $this->getFieldId('lat'),
        'class' => 'form-control',
        'type' => 'number',
        'step' => 'any',
        'min' => $markerBounds['minLat'],
        'max' => $markerBounds['maxLat'],
        'value' => $this->getLocationMarkerLat(''),
        'name' => $this->getFieldName('lat'),
        'autocomplete' => 'off',
        'placeholder' => rex_i18n::msg('geolocation_picker_lat_placeholder'),
    ];
    if ($this->isInputRequired()) {
        $inputAttributes['required'] = 'required';
    }

    $labelAttributes = [
        'class' => 'control-label',
        'for' => $inputAttributes['id'],
    ];

    $fieldAttributes = [
        'class' => trim('form-group geolocation-geopicker-lat ' . $this->getLatErrorClass()),
    ];
    if ($this->hasLatError()) {
        $notice = '<p class="help-block small"><span class="text-warning">' . rex_i18n::translate($this->getLatErrorMsg()) . '</span></p>';
    } else {
        $notice = '';
    }
    $latLngInputHtml = '
        <div' . rex_string::buildAttributes($fieldAttributes) . '>
            <label' . rex_string::buildAttributes($labelAttributes) . '>' . rex_i18n::msg('geolocation_lat_label') . '</label>
            <input' . rex_string::buildAttributes($inputAttributes) . ' />
            ' . $notice . '
        </div>';

    $inputAttributes = [
        'id' => $this->getFieldId('lng'),
        'class' => 'form-control',
        'type' => 'number',
        'step' => 'any',
        'min' => $markerBounds['minLng'],
        'max' => $markerBounds['maxLng'],
        'value' => $this->getLocationMarkerLng(''),
        'name' => $this->getFieldName('lng'),
        'autocomplete' => 'off',
        'placeholder' => rex_i18n::msg('geolocation_picker_lng_placeholder'),
    ];
    if ($this->isInputRequired()) {
        $inputAttributes['required'] = 'required';
    }

    $labelAttributes = [
        'class' => 'control-label',
        'for' => $inputAttributes['id'],
    ];

    $fieldAttributes = [
        'class' => trim('form-group geolocation-geopicker-lng ' . $this->getLngErrorClass()),
    ];
    if ($this->hasLngError()) {
        $notice = '<p class="help-block small"><span class="text-warning">' . rex_i18n::translate($this->getLngErrorMsg()) . '</span></p>';
    } else {
        $notice = '';
    }
    $latLngInputHtml .= '
        <div' . rex_string::buildAttributes($fieldAttributes) . '>
            <label' . rex_string::buildAttributes($labelAttributes) . '>' . rex_i18n::msg('geolocation_lng_label') . '</label>
            <input' . rex_string::buildAttributes($inputAttributes) . ' />
            ' . $notice . '
        </div>';
}

/**
 * Die Karte mit einem Positionsmarker vorbereiten.
 */
$locationpickerDataset = [
    $this->getLocationMarkerLatLng(null),
    $radius,
    $this->getBaseBounds()->latLng(),
    0 === \count($style) ? null : $style,
];

$map = $this->getMapset()
    ->dataset('locationpicker', $locationpickerDataset);

/**
 * Attribute für <gelolocation-geopicker>, mit denen der Picker konfiguriert und
 * gesteuert wird.
 * Je nach Gesamtumfang werden andere Klassen zugeordnet, denen
 * entsprechende Grid-Strategien zugeordnet sind.
 */
$pickerGridClass = '';
if ('' !== $latLngInputHtml) {
    $pickerGridClass = 'geolocation-geopicker-gridcontainer';
    $fieldClass[] = 'geolocation-geopicker-grid';
}

$pickerAttributes = [
    'class' => $this->getContainerClass($fieldClass),
    'config' => json_encode([
        'coordFld' => $this->getFieldId(),
        'marker' => 'locationpicker',
    ]),
];

$fieldId = $this->getContainerId();
if ('' < $fieldId) {
    $pickerAttributes['id'] = $fieldId;
}

/**
 * Ausgabe des HTML-Codes.
 */
?>
<geolocation-geopicker <?= rex_string::buildAttributes($pickerAttributes) ?>>
    <?= $geoCoderHtml ?>
    <div class="<?= $pickerGridClass ?>">
        <?= $latLngInputHtml ?>
        <?= $map->parse() ?>
    </div>
</geolocation-geopicker>

