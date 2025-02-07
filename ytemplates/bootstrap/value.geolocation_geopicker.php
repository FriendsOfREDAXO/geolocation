<?php

/**
 * Das YFragment baut das zu geopicker gehörende Value-HTML YForm-üblich zusammen.
 * Die eigentlichen Bestandteile zur Karte werden dann über das Fragment
 * geolocation/picker.php erzeugt.
 */

namespace FriendsOfRedaxo\Geolocation;

use FriendsOfRedaxo\Geolocation\Calc\Box;
use FriendsOfRedaxo\Geolocation\Calc\Point;
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
\assert(isset($geoCoder));

/**
 * Die Standardelementen eines Values.
 */
$notice = [];
if ('' !== $this->getElement('notice')) {
    $notice[] = rex_i18n::translate($this->getElement('notice'), false);
}

// TODO: Das Warning-Thema noch mal beleucheten. Bei internen feldern sollte die Warnung direkt am Lat/Lng-Feld erscheinen
if (isset($this->params['warning_messages'][$this->getId()]) && !$this->params['hide_field_warning_messages']) {
    $notice[] = '<span class="text-warning">' . rex_i18n::translate($this->params['warning_messages'][$this->getId()]) . '</span>';
}
if (\count($notice) > 0) {
    $notice = '<p class="help-block small">' . implode('<br />', $notice) . '</p>';
} else {
    $notice = '';
}

$class_group = [];
$class_group['form-group'] = 'form-group';
if ('' < $this->getWarningClass()) {
    $class_group[$this->getWarningClass()] = $this->getWarningClass();
}

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
// dd(get_defined_vars());

$geoPicker
    ->setContainer($this->getFieldId(), $this->getHTMLClass())
    ->setMapset($mapsetId, '', $mapsetClass)
    ->setBaseBounds($defaultBounds)
    ->setGeoCoder($geoCoder)
    ->setLocationMarker($radius, $markerStyle)
    ->setLocation($latLngValue)
    ->setAdressFields($addressFields)

//    ->setLocationRange($markerRange)
;

if (isset($error['lat'])) {
    $geoPicker->setLatError($error['lat']);
}
if (isset($error['lng'])) {
    $geoPicker->setLngError($error['lng']);
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

