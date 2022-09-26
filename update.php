<?php
/**
 * Ab dieser Version ist der Cronjob-Type in rex_cronjob von `Geolocation\cronjob` in `Geolocation\Cronjob` geändert.
 * Datenbank anpassen, wenn die aktuelle Version kleiner ist diese.
 */

namespace Geolocation;

// TODO: Funktionstest

use rex;
use rex_addon;
use rex_sql;
use rex_version;

/**
 * @var rex_addon $this
 */

if (rex_version::compare('2.0.0', $this->getVersion(), '>')) {
    $sql = rex_sql::factory();
    $sql->setTable(rex::getTable('cronjob'));
    $sql->setValue('type', 'Geolocation\\Cronjob');
    $sql->setWhere('BINARY type=?', ['Geolocation\\cronjob']);
    $user = rex::getUser();
    if (null !== $user) {
        $sql->addGlobalUpdateFields($user->getLogin());
    }
    $sql->update();
}
