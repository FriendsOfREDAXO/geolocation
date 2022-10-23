<?php
/**
 * API-Aprufe, Voreinstellungen.
 */

namespace Geolocation;

use rex;
use rex_addon;
use rex_cronjob_manager;
use rex_extension;
use rex_extension_point;
use rex_view;
use rex_yform_manager_dataset;

use function define;

/**
 * @var rex_addon $this
 */

define('Geolocation\ADDON', $this->getName());
define('Geolocation\TTL_MIN', 0);

// start...end nicht löschen!! Wird bei der (Re-)Installation benötigt
// ##start
define('Geolocation\TTL_DEF', 43200);
define('Geolocation\TTL_MAX', 130000);
define('Geolocation\CFM_DEF', 1000);
define('Geolocation\CFM_MIN', 50);
define('Geolocation\CFM_MAX', 100000);
define('Geolocation\KEY_TILES', 'geolayer');
define('Geolocation\KEY_MAPSET', 'geomapset');
define('Geolocation\OUT', 'geolocation_rex_map.php');
define('Geolocation\LOAD', true);
// ##end

// add additional functionality for YForm-tables

rex_yform_manager_dataset::setModelClass('rex_geolocation_mapset', Mapset::class);
rex_yform_manager_dataset::setModelClass('rex_geolocation_layer', Layer::class);

// proxy-request "geolayer" ???
//
// if URL contains geolayer=«tileId» the request is supposed to be a tile-request
// worked on by Geolocation\Layer::sendTile
//
$tileLayer = rex_request(KEY_TILES, 'integer', null);
if (null !== $tileLayer) {
    Layer::sendTile($tileLayer);
}

// proxy-request "geomapset" ???
//
// if URL contains geomapset=«mapId» the request is supposed to be a mapset-request
// worked on by Geolocation\Mapset::sendMapset

$mapset = rex_request(KEY_MAPSET, 'integer', null);
if (null !== $mapset) {
    Mapset::sendMapset($mapset);
}

// Start of Cronjob
rex_cronjob_manager::registerType('Geolocation\Cronjob');

// if BE: activate JS and CSS
if (rex::isBackend()) {
    // Im Fall "User != Admin" greifen Permissions. Falls der Zugriff auf den Kartensatz erlaubt ist
    // (Perm: geolocation[mapset]), aber nicht auf die Karten selbst (Perm: geolocation[layer]),
    // entsteht ein Konflikt: über das Kartensatz-Formular werden Layer-Liste und -Formulare als
    // Popup aufgerufen. Liste ist gewünscht, sonst nichts. Daher hier die Berechtigung auf Listen
    // prüfen und ggf. die Add/Edit-Spalte in der Layer-Liste entfernen (Spalte 0).
    if (null !== ($user = rex::getUser()) && !$user->hasPerm('geolocation[layer]')) {
        rex_extension::register('YFORM_DATA_LIST',
            static function (rex_extension_point $ep) {
                if ('rex_geolocation_layer' === $ep->getParam('table')->getTableName()) {
                    $list = $ep->getSubject();
                    $colNames = $list->getColumnNames();
                    $list->removeColumn($colNames[0]);
                }
            }
        );
    }

    Tools::echoAssetTags();
    rex_view::addCssFile($this->getAssetsUrl('geolocation_be.min.css'));
}
