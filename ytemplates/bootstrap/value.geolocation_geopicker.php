<?php

/**
 * Das YFragment baut das zu geopicker gehörende Value-HTML YForm-üblich zusammen.
 * Die eigentlichen Bestandteile zur Karte werden dann über das Fragment
 * geolocation/picker.php erzeugt.
 */

namespace FriendsOfRedaxo\Geolocation;

use FriendsOfRedaxo\Geolocation\Calc\Box;
use FriendsOfRedaxo\Geolocation\Calc\Point;
use FriendsOfRedaxo\Geolocation\Picker\PickerWidget;
use rex_i18n;
use rex_yform_value_geolocation_geopicker;

/**
 * @var int $mapsetId           Id des Kartensatzes; leere oder unbekannte ID -> Default-Mapset
 * @var string $mapsetClass     Klasse zur Formatierung der <rex-map>,
 * @var string $type            Darstellunsgvarianten (extern, latlng, lnglat)
 * @var int $radius             Der Radius um den Marker, Angabe in Meter
 * @var ?Box $defaultBounds     Koordinaten für die Default-Karte wenn es keinen gültigen Punkt gibt
 * @var array{lat: string, lng:string} $latLngId    feld-ID für die Lat/Lng-Felder
 * @var array{lat: string, lng:string} $latLngName  HTML-Input-Name der LatLng-Felder
 * @var ?Point $latLngValue     aktuelle Koordinate oder nul für leeres Feld
 * @var array<string> addressFields  IDs der Felder mit Adress-Teilen
 * @var array $markerStyle      Array oder JSON-String mit Formatierungsinfos (Leaflet-Options für Marker und Circle)
 * @var ?GeoCoder $geocoder     Die GeoCoder-Informationen bzw. null für "keinen GeoCoder
 *
 * Weitere Daten werden aus dem Value-Object ($this) entnommen
 */

/** @var rex_yform_value_geolocation_geopicker $this */

\assert(isset($mapsetId));
\assert(isset($mapsetClass));
\assert(isset($type));
\assert(isset($radius));
\assert(isset($defaultBounds));
\assert(isset($latLngId));
\assert(isset($latLngName));
\assert(isset($latLngValue) || null === $latLngValue);
\assert(isset($addressFields));
\assert(isset($markerStyle));
\assert(null === $geoCoder || isset($geoCoder));
\assert(\is_array($error));
\assert(null === $markerRange || isset($markerRange));

/**
 * Die Standardelementen eines Values.
 */
$localErrorMsg = false;
$notice = [];
if ('' !== $this->getElement('notice')) {
    $notice[] = rex_i18n::translate($this->getElement('notice'), false);
}

if (isset($this->params['warning_messages'][$this->getId()]) && !$this->params['hide_field_warning_messages']) {
    // tatsächlich werden die Meldungen gezielt bei den Eingabefeldern angezeigt.
    // Externe Felder sind hier eh außen vor
    $localErrorMsg = true;
}
if (\count($notice) > 0) {
    $notice = '<p class="help-block small">' . implode('<br />', $notice) . '</p>';
} else {
    $notice = '';
}

$class_group = [];
$class_group['form-group'] = 'form-group';

$class_label = [];
$class_label[] = 'control-label';

/**
 * GeoPicker für externe oder interne Felder einrichten (Basis-Konfiguration).
 */
if ('external' === $type) {
    $geoPicker = PickerWidget::factoryExternal(
        $latLngId['lat'],
        $latLngId['lng'],
    );
} else {
    $geoPicker = PickerWidget::factoryInternal(
        $latLngName['lat'],
        $latLngName['lng'],
        $latLngId['lat'],
        $latLngId['lng'],
    );
}

/**
 * Die weiteren Parameter.
 */
$geoPicker
    ->setContainer($this->getFieldId(), $this->getHTMLClass())
    ->setMapset($mapsetId, '', $mapsetClass)
    ->setBaseBounds($defaultBounds)
    ->setGeoCoder($geoCoder)
    ->setLocationMarker($radius, $markerStyle)
    ->setLocation($latLngValue)
    ->setAdressFields($addressFields)
    ->setLocationRange($markerRange)
;

if ('' < $this->getWarningClass()) {
    if (isset($error['lat'])) {
        $geoPicker->setLatError($this->getWarningClass(), $localErrorMsg ? $error['lat'] : '');
    }
    if (isset($error['lng'])) {
        $geoPicker->setLngError($this->getWarningClass(), $localErrorMsg ? $error['lng'] : '');
    }
}

/**
 * Das Value-HTML zusammenbauen.
 */
?>
<div class="<?= implode(' ', $class_group) ?>" id="<?= $this->getHTMLId() ?>">
    <label class="<?= implode(' ', $class_label) ?>"><?= $this->getElement('label') ?></label>
    <?= $geoPicker->parse() ?>
    <?= $notice ?>
 </div>

