<?php
namespace Geolocation;

define('Geolocation\ADDON', $this->name);
define('Geolocation\TTL_MIN',0);

// start...end nicht löschen!! Wird bei der (Re-)Installation benötigt
//##start
define('Geolocation\TTL_DEF',43200);
define('Geolocation\TTL_MAX',130000);
define('Geolocation\CFM_DEF',1000);
define('Geolocation\CFM_MIN',50);
define('Geolocation\CFM_MAX',100000);
define('Geolocation\KEY_TILES','geolayer');
define('Geolocation\KEY_MAPSET','geomapset');
define('Geolocation\OUT','geolocation_rex_map.php');
define('Geolocation\LOAD',true);
//##end

// add additional functionality for YForm-tables

\rex_yform_manager_dataset::setModelClass('rex_geolocation_mapset', mapset::class);
\rex_yform_manager_dataset::setModelClass('rex_geolocation_layer', layer::class);

// proxy-request "geolayer" ???
//
// if URL contains geolayer=«tileId» the request is supposed to be a tile-request
// worked on by Geolocation\layer::sendTile
//
$tileLayer = \rex_request( KEY_TILES, 'integer', null );
if( null !== $tileLayer ) {
    layer::sendTile( $tileLayer );
    exit();
}

// proxy-request "geomapset" ???
//
// if URL contains geomapset=«mapId» the request is supposed to be a mapset-request
// worked on by Geolocation\mapset::sendMapset
//

$mapset = \rex_request( KEY_MAPSET, 'integer', null );
if( null !== $mapset ) {
    mapset::sendMapset( $mapset );
    exit();
}

// Start of Cronjob
if( !\rex::isSafeMode()) {
    \rex_cronjob_manager::registerType('Geolocation\cronjob');
}

// if BE: activate JS and CSS
if( \rex::isBackend() ){
    if( LOAD ){
        \rex_view::addCssFile($this->getAssetsUrl('geolocation.min.css'));
        \rex_view::addJsFile($this->getAssetsUrl('geolocation.min.js'));
    }
    \rex_view::addCssFile($this->getAssetsUrl('geolocation_be.min.css'));
}
