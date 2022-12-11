<?php
/**
 *  De-Installations-Script
 *
 *  @package geolocation
 *
 *  Zusätzlich zu den von REDAXO selbst durchgeführten Aktivitäten:
 *
 *  Entfernt die Tabellen rex_geolocation_mapset und rex_geolocation_layer.
 *  Löscht die YForm-Tablemanager-Einträge für die Tabellen
 *  Löscht Cronjobs vom Typ Geolocation\Cronjob
 *
 *  Das Verzeichnis redaxo/data/addons/geolocaton wird nicht gelöscht, da hier Instanz-spezifische
 *  Konfigurationsbestandteile vom Admin update-sicher abgelegt werden können.
 *
 *  @var rex_addon $this
 */

try {

    $tables = [
        \rex::getTable('geolocation_layer'),
        \rex::getTable('geolocation_mapset')
    ];

    // Tabellen löschen
    foreach ( $tables as $table ){
        \rex_yform_manager_table_api::removeTable($table);
        \rex_sql_table::get( $table )->drop();
    }

    // Cronjobs löschen
    $sql = \rex_sql::factory();
    $sql->setTable( \rex::getTable( 'cronjob') );
    $sql->setWhere( 'type=:type', [':type'=>'FriendsOfRedaxo\\Geolocation\\Cronjob'] );
    $sql->delete();

} catch (\RuntimeException $e) {

    $this->setProperty('installmsg', $e->getMessage() );

}
