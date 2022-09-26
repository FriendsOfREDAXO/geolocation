<?php

namespace Geolocation;

/**
 * Ab dieser Version ist der Cronjob-Type in rex_cronjob von `Geolocation\cronjob` in `Geolocation\Cronjob` geändert.
 * Datenbank anpassen, wenn die aktuelle Version kleiner ist diese.
 */

// TODO: Funktionstest

use rex;
use rex_addon;
use rex_file;
use rex_sql;
use rex_version;

/**
 * @var rex_addon $this
 */

$conf = rex_file::getConfig(__DIR__ . '/package.yml');
$newVersion = $conf['version'] ?? '2.0.0';

if (rex_version::compare($newVersion, $this->getVersion(), '>')) {
    $sql = rex_sql::factory();
    $sql->setTable(rex::getTable('cronjob'));
    $sql->setValue('type', 'Geolocation\\Cronjob');
    $sql->setWhere('BINARY type=?', ['Geolocation\\cronjob']);
    $sql->addGlobalUpdateFields(rex::getUser()->getLogin());
    $sql->update();
}
