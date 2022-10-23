<?php
/**
 * Ab der Version 2.0.0 sind Klassennamen geändert:
 *  - der Cronjob-Type in rex_cronjob von `Geolocation\cronjob` in `Geolocation\Cronjob`
 *      => Tabelle rex_cronjob anpassen
 *  - Layer-Klasse von layer in Layer
 *      => Tabelle rex_yform_field anpassen.
 */

namespace Geolocation;

// # TODO: Funktionstest via Installer vor endgültiger Freigabe und diesen Text entfernen

use rex;
use rex_addon;
use rex_sql;
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
}

$this->includeFile('install.php');
