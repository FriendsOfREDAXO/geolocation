<?php
/**
 * Ruft Seiten des YForm-Tablemanagers als native Addon-Seiten auf.
 *
 * Dies ist eine allgemeine Routine und bildet eine Klammer um den Aufruf der data_edit.php
 * aus yform. Details sind in den FOR-Tricks zu finden.
 *
 * Die Seiten sind in der package.yml des Addons definiert:
 *
 *      ...
 *      subpage:
 *          mytable1:
 *              title: 'Cest moi'
 *              subPath: pages/yform.php
 *
 *      yform:
 *          «addon»/mytable1:
 *              table_name: mytable_a         mandatory; ohne Prefix «rex_»!!!
 *              show_title: FALSE/true        optional; default ist false!
 *              wrapper_class: myclass        optional
 *
 * @see https://friendsofredaxo.github.io/tricks/addons/yform/im-addon
 * @var \rex_addon $this
 */

$yform = $this->getProperty('yform', []);
$yform = $yform[\rex_be_controller::getCurrentPage()] ?? [];

if( isset($yform['table_name']) ) {
    $table_name = rex::getTable($yform['table_name']);
} else {
    $table_name = '';
}

$table_name = rex_request('table_name', 'string', $table_name);
$show_title = true === ($yform['show_title'] ?? false);
$wrapper_class = $yform['wrapper_class'] ?? '';

if ('' !== $table_name) {
    /**
     * STAN: Using $_REQUEST is forbidden, use rex_request::request() or rex_request() instead.
     * Hierfür gibt es keinen Ersatz durch eine REX-Methode/Funktion.
     * @phpstan-ignore-next-line
     */
    $_REQUEST['table_name'] = $table_name;
}

if (!$show_title) {
    \rex_extension::register(
        'YFORM_MANAGER_DATA_PAGE_HEADER',
        static function (rex_extension_point $ep) {
            if ($ep->getParam('yform')->table->getTableName() === $ep->getParam('table_name')) {
                return '';
            }
        },
        \rex_extension::EARLY, ['table_name' => $table_name]
    );
}

if ('' !== $wrapper_class) {
    echo '<div class="',$wrapper_class,'">';
}

include \rex_path::plugin('yform', 'manager', 'pages/data_edit.php');

if ('' !== $wrapper_class) {
    echo '</div>';
}
