<?php

echo \rex_view::title( $this->i18n('geolocation_title') );

// Je nach Anforderung den Gesamt-Cache oder den Layer-Cache löschen
//
//      index.php?....func=clear_cache                  Gesamt-Cache
//      index.php?....func=clear_cache&layer_id=«id»    Layer-Cache

$func = rex_request('func', 'string');
if ('clear_cache' == $func) {
    $layerId = rex_request('layer_id', 'integer', 0);
    $c = $layerId
         ? geolocation_proxy::clearLayerCache( $layerId )
         : geolocation_proxy::clearCache();
    echo \rex_view::info(rex_i18n::msg('geolocation_cache_files_removed', $c));
}

// Liste 'rex geolocation_tiles' um einen Button zum Löschen des Layer-Cache erweitern

\rex_extension::register('YFORM_DATA_LIST',
    function( \rex_extension_point $ep )
    {
        geolocation_layer::listAddCacheButton( $ep->getSubject(), $ep->getParam( 'table' )->getTableName() );
    }
);

\rex_extension::register('YFORM_DATA_LIST_SQL',
    function( \rex_extension_point $ep )
    {
        return geolocation_layer::listSort( $ep->getSubject(), $ep->getParam( 'table' )->getTableName() );
    }
);


\rex_be_controller::includeCurrentPageSubPath();
