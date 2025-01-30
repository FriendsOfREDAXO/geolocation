<?php

/**
 * API-Abrufe, Voreinstellungen.
 */

namespace FriendsOfRedaxo\Geolocation;

use rex;
use rex_addon;
use rex_be_controller;
use rex_cronjob_manager;
use rex_extension;
use rex_extension_point;
use rex_view;
use rex_yform;
use rex_yform_manager_dataset;

use function define;

/** @var rex_addon $this */

define('FriendsOfRedaxo\\Geolocation\\ADDON', $this->getName());
define('FriendsOfRedaxo\\Geolocation\\TTL_MIN', 0);

// start...end nicht löschen!! Wird bei der (Re-)Installation benötigt
// ##start
define('FriendsOfRedaxo\\Geolocation\\TTL_DEF', 43200);
define('FriendsOfRedaxo\\Geolocation\\TTL_MAX', 130000);
define('FriendsOfRedaxo\\Geolocation\\CFM_DEF', 1000);
define('FriendsOfRedaxo\\Geolocation\\CFM_MIN', 50);
define('FriendsOfRedaxo\\Geolocation\\CFM_MAX', 100000);
define('FriendsOfRedaxo\\Geolocation\\KEY_TILES', 'geolayer');
define('FriendsOfRedaxo\\Geolocation\\KEY_MAPSET', 'geomapset');
define('FriendsOfRedaxo\\Geolocation\\KEY_GEOCODER', 'geocoder');
define('FriendsOfRedaxo\\Geolocation\\OUT', 'geolocation_rex_map.php');
define('FriendsOfRedaxo\\Geolocation\\LOAD', true);
// ##end

// add additional functionality for YForm-tables

rex_yform_manager_dataset::setModelClass('rex_geolocation_mapset', Mapset::class);
rex_yform_manager_dataset::setModelClass('rex_geolocation_layer', Layer::class);
rex_yform::addTemplatePath($this->getPath('ytemplates'));

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

// proxy-request "geocoder"
//
// if URL contains geocoder=«geoCoderId» the request is supposed to be a geocoder-request
// worked on by Geolocation\GeoCoder::sendResponse
$geocoder = rex_request(KEY_GEOCODER, 'integer', null);
if (null !== $geocoder) {
    GeoCoder::sendResponse($geocoder);
}

// Start of Cronjob
rex_cronjob_manager::registerType(Cronjob::class);

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
            },
        );
    }

    Tools::echoAssetTags();
    rex_view::addCssFile($this->getAssetsUrl('geolocation_be.min.css'));
    rex_view::addJsFile($this->getAssetsUrl('geolocation_be.min.js'));
    if ('yform/manager/table_field' === rex_be_controller::getCurrentPage()) {
        rex_view::addJsFile($this->getAssetsUrl('tablemanager.min.js'));
    }
}
