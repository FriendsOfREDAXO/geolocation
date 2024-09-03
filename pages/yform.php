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
 *              table_name: mytable_a         mandatory; sieh Anmerkung unten!!!
 *              show_title: FALSE/true        optional; default ist false!
 *              wrapper_class: myclass        optional
 *
 * "table_name" entweder als Tabellenname ohne den Prefix angegeben werden oder
 * als Model-Class/Dataset-Class:
 *      tabelle:            wird über rex::getTable($table_name) zu rex_tabelle
 *      Namespace\Tabelle:  wird über $table_name::table()->getTableName() zu rex_tabelle
 * 
 * @see https://friendsofredaxo.github.io/tricks/addons/yform/im-addon
 * @var \rex_addon $this
 */

$yform = $this->getProperty('yform', []);
$yform = $yform[\rex_be_controller::getCurrentPage()] ?? [];

if( isset($yform['table_name']) ) {
    $table_name = $yform['table_name'];
    if( is_subclass_of($table_name,rex_yform_manager_dataset::class)) {
        // table_name ist eine Dataset-Klasse
        $table_name = $table_name::table()->getTableName();
    } else {
        // table_name ist ein Tabellenname
        $table_name = rex::getTable($table_name);
    }
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
