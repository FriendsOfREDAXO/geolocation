<?php
/**
 * Allgemeine Add-Einstiegsseite (page-Rahmen)
 *
 *  @package geolocation
 *
 *  @var \rex_addon $this
 */

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
            'rex-api-call' => 'geolocation_clearcache'
        ], false);
        $page->setHref( $href );
    }
}

// Title und Submenü erzeugen/ausgeben
echo \rex_view::title( $this->i18n('geolocation_title') );

// ggf. vorhandene API Messages ausgeben
echo rex_api_function::getMessage();

// Liste 'rex_geolocation_mapset' um eine Action zum Löschen des Layer-Cache erweitern
\rex_extension::register('YFORM_DATA_LIST_ACTION_BUTTONS','\Geolocation\mapset::YFORM_DATA_LIST_ACTION_BUTTONS');

// Liste 'rex_geolocation_layer' um eine Action zum Löschen des Layer-Cache erweitern
\rex_extension::register('YFORM_DATA_LIST_ACTION_BUTTONS','\Geolocation\layer::YFORM_DATA_LIST_ACTION_BUTTONS');

// Liste 'rex_geolocation_layer' mit geänderter Sortierung
\rex_extension::register('YFORM_DATA_LIST_QUERY','\Geolocation\layer::YFORM_DATA_LIST_QUERY');
#\rex_extension::register('YFORM_DATA_LIST_QUERY',
#    function( \rex_extension_point $ep )
#    {
#        return \Geolocation\layer::listSort( $ep->getSubject() );
#    }
#);

// Und nun die aktuelle Seite anzeigen
\rex_be_controller::includeCurrentPageSubPath();
