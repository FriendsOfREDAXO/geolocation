<?php

//nur hier im BE relevante Konfigurationen einlesen und daraus weitere Hilfswerte für Formulare setzen
$config = array_merge(
    \rex_file::getConfig( \rex_path::addonData(\Geolocation\ADDON,'config.yml'), [] ),
    \rex_file::getConfig( \rex_path::addon(\Geolocation\ADDON,'/install/config.yml'), [] ),
);
define( 'Geolocation\ZOOM_MIN', $config['zoom_min'] );
define( 'Geolocation\ZOOM_MAX', $config['zoom_max'] );
define( 'Geolocation\PROXY_ONLY', !$config['scope']['mapset'] );

// Anzeige / Scope beschränkt auf Proxy/Cache?
if( \Geolocation\PROXY_ONLY ){
    $subpages = \rex_be_controller::getPageObject(\rex_be_controller::getCurrentPagePart(1))->getSubpages();
    if( isset($subpages['mapset']) ){
        $subpages['mapset']->setHidden(true);
    }
}

// Title und Submenü
echo \rex_view::title( $this->i18n('geolocation_title') );

// Je nach Anforderung den Gesamt-Cache oder den Layer-Cache löschen
//
//      index.php?....func=clear_cache                  Gesamt-Cache
//      index.php?....func=clear_cache&layer_id=«id»    Layer-Cache

$func = \rex_request('func', 'string');
if ('clear_cache' == $func) {
    $layerId = \rex_request('layer_id', 'integer', 0);
    $c = $layerId
         ? \Geolocation\cache::clearLayerCache( $layerId )
         : \Geolocation\cache::clearCache();
    echo \rex_view::info($this->i18n('geolocation_cache_files_removed', $c));
}

// Liste 'rex_geolocation_layer' um einen Button zum Löschen des Layer-Cache erweitern
\rex_extension::register('YFORM_DATA_LIST',
    function( \rex_extension_point $ep )
    {
        \Geolocation\layer::listAddCacheButton( $ep->getSubject(), $ep->getParam('table')->getTableName() );
    }
);

// Liste 'rex_geolocation_layer' mit geänderter Sortierung
\rex_extension::register('YFORM_DATA_LIST_SQL',
    function( \rex_extension_point $ep )
    {
        return \Geolocation\layer::listSort( $ep->getSubject(), $ep->getParam('table')->getTableName() );
    }
);

// Und nun die aktuelle Seite anzeigen
\rex_be_controller::includeCurrentPageSubPath();
