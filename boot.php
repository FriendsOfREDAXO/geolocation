<?php

define('GEOLOCATION_ADDON', 'geolocation');
define('GEOLOCATION_TTL_DEF', 10080);
define('GEOLOCATION_TTL_MIN', 1);
define('GEOLOCATION_TTL_MAX', 130000);
define('GEOLOCATION_CFM_DEF', 1000);
define('GEOLOCATION_CFM_MIN', 50);
define('GEOLOCATION_CFM_MAX', 100000);
define('GEOLOCATION_KEY_TILES', 'geotile');
define('GEOLOCATION_KEY_MAPSET', 'geomapset');

// prepare additinal functionality for YForm-tables

rex_yform_manager_dataset::setModelClass('rex_geolocation_maps', geolocation_mapset::class);
rex_yform_manager_dataset::setModelClass('rex_geolocation_tiles', geolocation_layer::class);

// proxy-request "geotile"
//
// if URL contains geotile=«tileId» the request is supposed to be a tile-request
// worked on by geolocation_proxy::sendTile
//

$tileLayer = rex_request( GEOLOCATION_KEY_TILES, 'integer', null );
if( null !== $tileLayer ) {
    geolocation_layer::sendTile( $tileLayer );
    exit();
}

// proxy-request "geomapset" ???
//
// if URL contains geomapset=«mapId» the request is supposed to be a mapset-request
// worked on by geolocation_proxy::sendMapset
//

$mapset = rex_request( GEOLOCATION_KEY_MAPSET, 'integer', null );
if( null !== $mapset ) {
    geolocation_mapset::sendMapset( $mapset );
    exit();
}

if( !rex::isSafeMode()) {
    rex_cronjob_manager::registerType('rex_cronjob_geolocation_cache');
}
