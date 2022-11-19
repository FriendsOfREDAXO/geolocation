<?php
/**
 * Ab der Version 2.0.0 sind Klassennamen geändert:
 *  - der Cronjob-Typ in rex_cronjob von `Geolocation\cronjob` in `Geolocation\Cronjob`
 *      => Tabelle rex_cronjob anpassen
 *  - Layer-Klasse von `layer` in `Layer`
 *      => Tabelle rex_yform_field anpassen.
 *  - Layer-Feld "url" erhält den Datentyp rex_yform_value_geolocation_url
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
     * Geolocation\Layer statt Geolocation\layer.
     */
    $sql->setTable(rex::getTable('yform_field'));
    $sql->setValue('function', 'Geolocation\\Layer::verifyUrl');
    $sql->setWhere('`function`=:old', [':old' => 'Geolocation\\Layer::verifyUrl']);
    $sql->update();

    $sql->setTable(rex::getTable('yform_field'));
    $sql->setValue('function', 'Geolocation\\Layer::verifySubdomain');
    $sql->setWhere('`function`=:old', [':old' => 'Geolocation\\Layer::verifySubdomain']);
    $sql->update();

    $sql->setTable(rex::getTable('yform_field'));
    $sql->setValue('function', 'Geolocation\\Layer::verifyLang');
    $sql->setWhere('`function`=:old', [':old' => 'Geolocation\\Layer::verifyLang']);
    $sql->update();

    /**
     * Layer-Feld "url" erhält den Datentyp rex_yform_value_geolocation_url
     * und in 'field' den Namen des Subdomain-Feldes.
     * Spalte 'field' ggf. anlegen
     */
    rex_sql_table::get('yform_field')
        ->ensureColumn(new rex_sql_column('field', 'text'))
        ->ensure();

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
}

$this->includeFile('install.php');
