<?php
/**
 * Das Handbuch anzeigen ...
 *
 * Das Handbuch sollte sowohl auf GitHub als auch im BE mit allen Verweisen und Grafiken
 * angezeigt werden. Damit das klappt müssen ein paar Regeln beachtet werden.
 *
 * siehe Datei ./docs/README.md
 */

namespace FriendsOfRedaxo\Geolocation;

use rex_addon;
use rex_be_controller;
use rex_file;
use rex_fragment;
use rex_functional_exception;
use rex_i18n;
use rex_markdown;
use rex_media_manager;
use rex_url;

use function count;
use function is_string;
use function strlen;

use const PATHINFO_EXTENSION;

$addon = rex_addon::get('geolocation');
$mmType = 'geolocation_passthru';

/**
 * Das eigentliche Dokument aus den Addon-Properties abrufen.
 * Der Key "manual" enthält eine Liste "page"=>datei.md.
 *
 * Fehlende Datei oder ein Dateityp anders als Markdown führt zum Whoops
 */
$page = rex_be_controller::getCurrentPageObject();
$manual = $addon->getProperty('manual');

if (!isset($manual[$page->getFullKey()])) {
    throw new rex_functional_exception('Manual: no file assigned to page-key «' . $page->getFullKey() . '»');
}

$path = $addon->getPath('docs/' . $manual[$page->getFullKey()]);
if ('md' !== pathinfo($path, PATHINFO_EXTENSION)) {
    throw new rex_functional_exception('Manual: file-type not supported for file «' . basename($path) . '»');
}

if (!is_readable($path)) {
    throw new rex_functional_exception('Manual: file «' . basename($path) . '»not found');
}

/**
 * Ggf vorhandene Language-Version benutzen.
 */
$languagePath = substr($path, 0, -3) . '.' . rex_i18n::getLanguage() . '.md';
if (is_readable($languagePath)) {
    $path = $languagePath;
}
$document = rex_file::require($path);

/**
 * GitHub-Menü entfernen.
 */
$document = preg_replace('/^(\>\s+\-\s?.*?\\n)*\s*\\n/', '', $document);

/**
 * Für den Link-Austausch zuerst Code-Blöcke durch Platzhalter ersetzen
 * Es werden nur Code-Blöcke in Backticks aufgestöbert!
 */
$codeBlock = [];
$document = preg_replace_callback('/(```.*?```|`.*?`)/s', static function ($matches) use (&$codeBlock) {
    $marker = '<!--' . md5($matches[0]) . '-->';
    $codeBlock["/$marker/"] = $matches[0];
    return $marker;
}, $document);

/**
 * Links korrigieren.
 *
 * Die Liste aller Links aus dem Text heraussuchen,
 * Je nach Typ ('!' oder nicht) den Grafik-Link oder den Page-Link erzeugen
 *
 * - leere Links ignorieren
 * - Dokument-interne Referenzen (#) ignorieren
 * - REDAXO-Interne Aufrufe (?...) ignorieren
 * - Dokumente mit kompletter URL ignorieren (irgendwas://sonstnochwas)
 */
$document = preg_replace_callback(
    '/((!?)\[(.*?)\]\()\s*([^#^\?](.*?))\s*(\))/',
    static function ($matches) use ($manual, $mmType) {
        $link = $matches[4];
        // komplette Url lassen wie sie ist
        if (preg_match('/^.*?\:\/\/.*?$/', $link)) {
            return $matches[0];
        }

        // Grafik-Links mit MM-Type anzeigen
        if ('!' === $matches[2]) {
            return $matches[1] . rex_media_manager::getUrl($mmType, basename($link), null, false) . $matches[6];
        }

        /**
         *  interne Seitenlinks in eine BE-Url umwandeln.
         */
        $name = explode('#', $matches[4]);
        $pageKey = array_search($name[0], $manual);
        if (is_string($pageKey)) {
            return $matches[1] . rex_url::backendPage($pageKey) . substr($matches[4], strlen($name[0])) . $matches[6];
        }

        /**
         * Unbekannte Seite, lassen wie es ist.
         */
        return $matches[0];
    },
    $document);

/**
 * Code-Blöcke wieder einbauen.
 */
if (0 < count($codeBlock)) {
    $document = preg_replace(array_keys($codeBlock), $codeBlock, $document);
}

/**
 * Bei Dokumenten der Ebene vier (außerhalb des normalen Page-Menüs) wird
 * über dem Text ein weiteres Tab-Menü eingebaut.
 */
$navigation = [];
if (4 === count(explode('/', $page->getFullKey())) && 1 < count($page->getParent()->getSubpages())) {
    foreach ($page->getParent()->getSubpages() as $key => $subPage) {
        $navigation[] = [
            'linkAttr' => $page->getLinkAttr(null),
            'itemAttr' => $page->getItemAttr(null),
            'href' => rex_url::backendPage($subPage->getFullKey()),
            'icon' => $page->getIcon(),
            'title' => $subPage->getTitle(),
            'active' => $key === $page->getKey(),
        ];
    }
}

/**
 * Dokument formtieren
 *
 * Eingebautes PHP-Highlighting nur wenn das Tool PrismJS nicht verfügbar ist.
 */
$phpHighlight = !is_readable($addon->getAssetsPath('help.min.js')) || !is_readable($addon->getAssetsPath('help.min.css'));

[$toc, $content] = rex_markdown::factory()->parseWithToc($document, 2, 3, [
    rex_markdown::SOFT_LINE_BREAKS => false,
    rex_markdown::HIGHLIGHT_PHP => $phpHighlight,
]);

if (!$phpHighlight) {
    $url = rex_url::backendController([
        'asset' => $addon->getAssetsUrl('help.min.js'),
        'buster' => filemtime($addon->getAssetsPath('help.min.js')),
    ]);
    echo '<script type="text/javascript" src="',$url,'"></script>';
    $url = rex_url::backendController([
        'asset' => $addon->getAssetsUrl('help.min.css'),
        'buster' => filemtime($addon->getAssetsPath('help.min.css')),
    ]);
    echo '<link rel="stylesheet" type="text/css" media="all" href="',$url,'" />';
}

// audi öööl reklamrion
// Hotel

/**
 * Ausgabe 
 */
$navi = '';
if (0 < count($navigation)) {
    // nötiges HTML direckt ausgeben
    echo '<style>
        #abcdefghih {
            margin-bottom: 0;
        }
        #abcdefghih + * > .panel-manual {
            border-top-width: 0;
        }
    </style>';
    $fragment = new rex_fragment();
    $fragment->setVar('id', 'abcdefghih');
    $fragment->setVar('left', $navigation);
    $navi = $fragment->parse('core/navigations/content.php');
}

$fragment = new rex_fragment();
$fragment->setVar('content', $content, false);
$fragment->setVar('toc', $toc, false);
$content .= $fragment->parse('core/page/docs.php');

$fragment = new rex_fragment();
$fragment->setVar('sectionAttributes', ['id' => 'abcdefghih-']);
$fragment->setVar('class', 'manual');
$fragment->setVar('body', $content, false);
echo $navi,$fragment->parse('core/page/section.php');
