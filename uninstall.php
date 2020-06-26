<?php

$sql = rex_sql::factory();

// Tabellen löschen
foreach ( [ geolocation_layer::table()->getTableName(),geolocation_mapset::table()->getTableName() ] as $table ){

    $sql->setQuery('DELETE FROM `'.rex::getTable('yform_table').'` WHERE table_name = "'.$table.'"');
    $sql->setQuery('DELETE FROM `'.rex::getTable('yform_field').'` WHERE table_name = "'.$table.'"');
    $sql->setQuery('DELETE FROM `'.rex::getTable('yform_history').'` WHERE table_name = "'.$table.'"');

    rex_sql_table::get( $table )->drop();

}

// Cronjob löschen
$sql->setTable( rex::getTable( 'cronjob') );
$sql->setWhere( 'type=:type', [':type'=>'rex_cronjob_geolocation_cache'] );
$sql->delete();
