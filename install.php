<?php

/** @var rex_addon $this */

// YForm-Tabellen importieren und anlegen
rex_yform_manager_table::deleteCache();
$content = rex_file::get( __DIR__ . '/install/tableset.json' );
rex_yform_manager_table_api::importTablesets($content);

// Cache-Verzeichnis  anlegen
rex_dir::create( $this->getCachePath( ) );

// Cronjon anlegen
$intervall = [
    'minutes'=>['30'],
    'hours'=>['05'],
    'days'=>'all',
    'weekdays'=>'all',
    'months'=>'all',
];
$timestamp = rex_cronjob_manager_sql::calculateNextTime($intervall);
$sql = rex_sql::factory();
$sql->setTable( rex::getTable( 'cronjob') );
$sql->setValue( 'name', 'Geolocation: Cleanup Cache' );
$sql->setValue( 'description', '' );
$sql->setValue( 'type', 'rex_cronjob_geolocation_cache' );
$sql->setValue( 'parameters', '[]' );
$sql->setValue( 'interval', json_encode( $intervall ) );
$sql->setValue( 'nexttime', rex_sql::datetime($timestamp) );
$sql->setValue( 'environment', '|frontend|backend|' );
$sql->setValue( 'execution_moment', 1 );
$sql->setValue( 'execution_start', '0000-00-00 00:00:00' );
$sql->setValue( 'status', 1 );
$sql->addGlobalUpdateFields( rex::getUser()->getLogin() );
$sql->addGlobalCreateFields( rex::getUser()->getLogin() );
$sql->insert();
dump(get_defined_vars());

// Musterdaten importieren
rex_sql_util::importDump( __DIR__ . '/install/dataset.sql' );

//System_Cache löschen
rex_delete_cache();
rex_yform_manager_table::deleteCache();
