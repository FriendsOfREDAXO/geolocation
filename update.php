<?php
/**
 * Änderungen ab Version 2.0 bedingen Anpassungen an den Datenbank-Tabellen:
 *  - der Cronjob-Typ in rex_cronjob von `Geolocation\cronjob` in `Geolocation\Cronjob`geändert
 *      => Tabelle rex_cronjob anpassen
 *  - Layer-Klasse von `layer` in `Layer`
 *      => Tabelle rex_yform_field anpassen.
 *  - Layer-Feld "url" erhält den Feldtyp rex_yform_value_geolocation_url
 *      => Tabelle rex_yform_field anpassen.
 *  - Mapset-Felder "layer" und "overlay" erhalten den Feldtyp rex_yform_value_geolocation_layerselect
 *      => Tabelle rex_yform_field anpassen.
 *      => zusätzliche Felder in rex_geolocation_mapset anlegen und füllen.
 */

namespace Geolocation;

// # TODO: Funktionstest via Installer vor endgültiger Freigabe und diesen Text entfernen

use rex;
use rex_addon;
use rex_sql;
use rex_sql_column;
use rex_sql_table;
use rex_version;

/**
 * @var rex_addon $this
 */

// einmalig beim Umstieg auf 2.0
if (rex_version::compare('2.0.0', $this->getVersion(), '>')) {
    $sql = rex_sql::factory();

    /**
     * Geolocation\Cronjob statt Geolocation\cronjob.
     */
    $sql->setTable(rex::getTable('cronjob'));
    $sql->setValue('type', 'Geolocation\\Cronjob');
    $sql->setWhere('BINARY type=?', ['Geolocation\\cronjob']);
    $user = rex::getUser();
    if (null !== $user) {
        $sql->addGlobalUpdateFields($user->getLogin());
    }
    $sql->update();

    /**
     * Felder in rex_yform_field sicherstellen
     *  - für "rex_yform_value_geolocation_url" das Feld "field"
     *  - für "geolocation_layerselect" das Feld "format".
     */
    rex_sql_table::get('yform_field')
        ->ensureColumn(new rex_sql_column('field', 'text'))
        ->ensureColumn(new rex_sql_column('format', 'text'))
        ->ensure();

    /**
     * Mapset (rex_geolocation_mapset) wurde geändert
     *  - "layer_selected": neues Feld
     *  - "overlay_selected": neues Feld
     *  - Felder auf var(191) setzen, das reicht.
     */
    rex_sql_table::get(rex::getTable('geolocation_mapset'))
        ->ensureColumn(new rex_sql_column('layer', 'varchar(191)'))
        ->ensureColumn(new rex_sql_column('layer_select', 'varchar(191)'))
        ->ensureColumn(new rex_sql_column('overlay', 'varchar(191)'))
        ->ensureColumn(new rex_sql_column('overlay_select', 'varchar(191)'))
        ->ensure();

    /**
     * Informatonen in rex_yform_field anpassen
     *  - "Geolocation\Layer" statt "Geolocation\layer"
     *  - "geolocation_url" als Feldtyp für die URL-Eingabe mit Test-Button
     *  - "geolocation_layerselect" als Feldtyp für die Mapset-Layer-Auswahl statt "be_manager_relation"
     *  - daraus folgende Parameter-Felder anpassen / befüllen.
     */
    // - "Geolocation\Layer" statt "Geolocation\layer"
    $sql->setTable(rex::getTable('yform_field'));
    $sql->setValue('function', 'Geolocation\\Layer::verifyUrl');
    $sql->setWhere('`function`=:old', [':old' => 'Geolocation\\layer::verifyUrl']);
    $sql->update();

    $sql->setTable(rex::getTable('yform_field'));
    $sql->setValue('function', 'Geolocation\\Layer::verifySubdomain');
    $sql->setWhere('`function`=:old', [':old' => 'Geolocation\\layer::verifySubdomain']);
    $sql->update();

    $sql->setTable(rex::getTable('yform_field'));
    $sql->setValue('function', 'Geolocation\\Layer::verifyLang');
    $sql->setWhere('`function`=:old', [':old' => 'Geolocation\\layer::verifyLang']);
    $sql->update();

    // - "geolocation_url" als Feldtyp für die URL-Eingabe mit Test-Button
    $sql->setTable(rex::getTable('yform_field'));
    $sql->setValue('type_name', 'geolocation_url');
    $sql->setValue('field', 'subdomain');
    $sql->setWhere(
        '`table_name`=:table AND `name`=:field',
        [
            ':table' => rex::getTable('geolocation_layer'),
            ':field' => 'url',
        ]);
    $sql->update();

    // - "geolocation_layerselect" als Feldtyp für die Mapset-Layer-Auswahl statt "be_manager_relation"
    $sql->setTable(rex::getTable('yform_field'));
    $sql->setValue('type_name', 'geolocation_layerselect');
    $sql->setValue('notice', 'translate:geolocation_mapset_layer_notice');
    $sql->setValue('format', 'radio');
    $sql->setWhere(
        '`table_name`=:table AND `name`=:field',
        [
            ':table' => rex::getTable('geolocation_mapset'),
            ':field' => 'layer',
        ]);
    $sql->update();

    $sql->setTable(rex::getTable('yform_field'));
    $sql->setValue('type_name', 'geolocation_layerselect');
    $sql->setValue('notice', 'translate:geolocation_mapset_overlay_notice');
    $sql->setValue('format', 'checkbox');
    $sql->setWhere(
        '`table_name`=:table AND `name`=:field',
        [
            ':table' => rex::getTable('geolocation_mapset'),
            ':field' => 'overlay',
        ]);
    $sql->update();

    /**
     * In den bestehenden Datensätzen von rex_geolocation_mapset
     * die neuen Zusatzfelder befüllen. Die Werte entsprechen dem
     * bisherigen Verhalten vor Version 2.0.0
     *  - "layer_selected" => id des ersten Layers im Feld "layer"
     *  - overlay_selected => keine Auswahl, leer.
     */
    $sql->setTable(rex::getTable('geolocation_mapset'));
    $sql->setRawValue('layer_selected', 'SUBSTRING_INDEX(`layer`,\',\', 1)');
    $sql->setValue('overlay_selected', '');
    $sql->update();
}

$this->includeFile('install.php');
