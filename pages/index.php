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


// Button "Delete Cache" konfigurieren (Referenziere auf die aktuelle Seite)
$page = \rex_be_controller::getPageObject('geolocation');
if( $page ){
    $page = $page->getSubpage('clear_cache');
    if( $page ){
        $href = \rex_url::backendController([
            'page' => \rex_be_controller::getCurrentPageObject()->getFullKey(),
            'rex-api-call' => 'clear_cache'
        ], false);
        $page->setHref( $href );
    }
}

// Title und Submenü
echo \rex_view::title( $this->i18n('geolocation_title') );

// API Messages
echo rex_api_function::getMessage();

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
