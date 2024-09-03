<?php
/**
 * Allgemeine Addon-Einstiegsseite (page-Rahmen).
 */

namespace FriendsOfRedaxo\Geolocation;

use rex_addon;
use rex_api_function;
use rex_be_controller;
use rex_extension;
use rex_file;
use rex_path;
use rex_url;
use rex_view;

use function assert;
use function define;

/**
 *  @var rex_addon $this
 */

/**
 * Die im Backend relevanten Konfigurationen (Instanz und allgemein) werden eingelesen und daraus
 * weitere Hilfswerte für Formulare gesetzt.
 */
$config = array_merge(
    rex_file::getConfig(rex_path::addonData(ADDON, 'config.yml'), []),
    rex_file::getConfig(rex_path::addon(ADDON, '/install/config.yml'), []),
);
define('FriendsOfRedaxo\\Geolocation\\ZOOM_MIN', $config['zoom_min']);
define('FriendsOfRedaxo\\Geolocation\\ZOOM_MAX', $config['zoom_max']);
define('FriendsOfRedaxo\\Geolocation\\PROXY_ONLY', !$config['scope']['mapset']);

/**
 * für die nächsten Schritte vorab das Seitenobject bereitstellen.
 */
$thisPage = rex_be_controller::getCurrentPageObject();
assert(null !== $thisPage); // STAN: sonst "Cannot call method getParent() on rex_be_page|null."
$mainPage = $thisPage->getParent() ?? $thisPage;

/**
 * Bei Systemen, die auf "nur Proxy" konfiguriert sind, wird die Kartensatz-Seite ausgeblendet.
 */
if (PROXY_ONLY) {
    $subPage = $mainPage->getSubpage('mapset');
    if (null !== $subPage) {
        $subPage->setHidden(true);
    }
}

/**
 * Den Button für die Delete-Cache-Seite so konfigurieren, dass man anschließend wieder
 * auf diese Seite zurückkommt.
 */
$page = $mainPage->getSubpage('clear_cache');
if (null !== $page) {
    $href = rex_url::backendController([
        'page' => $thisPage->getFullKey(),
        'rex-api-call' => 'geolocation_clearcache',
    ], false);
    $page->setHref($href);
}

// Title und Submenü erzeugen/ausgeben
echo rex_view::title($this->i18n('geolocation_title'));

// ggf. vorhandene API Messages ausgeben
echo rex_api_function::getMessage();

// Liste 'rex_geolocation_mapset' um eine Action zum Löschen des Layer-Cache erweitern
rex_extension::register('YFORM_DATA_LIST_ACTION_BUTTONS', Mapset::epYformDataListActionButtons(...));

// Liste 'rex_geolocation_layer' um eine Action zum Löschen der Layer-Caches erweitern
rex_extension::register('YFORM_DATA_LIST_ACTION_BUTTONS', Layer::epYformDataListActionButtons(...));

// Liste 'rex_geolocation_layer' mit geänderter Sortierung
rex_extension::register('YFORM_DATA_LIST_QUERY', Layer::epYformDataListQuery(...));

// Und nun die aktuelle Seite anzeigen
rex_be_controller::includeCurrentPageSubPath();
