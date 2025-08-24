<?php

/**
 *  De-Installations-Script.
 *
 *  @package geolocation
 *
 *  Zusätzlich zu den von REDAXO selbst durchgeführten Aktivitäten:
 *
 *  Entfernt die Tabellen rex_geolocation_mapset und rex_geolocation_layer.
 *  Löscht die YForm-Tablemanager-Einträge für die Tabellen
 *  Löscht Cronjobs vom Typ Geolocation\Cronjob
 *  Löscht den Metafeld-Typ
 *
 *  Das Verzeichnis redaxo/data/addons/geolocaton wird nicht gelöscht, da hier Instanz-spezifische
 *  Konfigurationsbestandteile vom Admin update-sicher abgelegt werden können.
 *
 *  @var rex_addon $this
 */

use FriendsOfRedaxo\Geolocation\Picker\PickerMetafield;

try {
    $tables = [
        'layer' => rex::getTable('geolocation_layer'),
        'mapset' => rex::getTable('geolocation_mapset'),
    ];
    $sql = rex_sql::factory();

    /**
     * Tabellen löschen
     * 
     * Aus unbekannten Gründen verbleiben nach dem Löschen der Formulardaten aus rex_yform_field
     * für die Tabelle rex_geolocation_mapset die Feldeinträge vom Typ geolocation_layerselect
     * Und nur die; sie werden sogar neu angelegt wenn zuvor das Formulat manuell gelöscht wurde
     * Wie schräg ist das denn .... Lösung als Workaround: die Felder manuell löschen
     */
    foreach ($tables as $table) {
        rex_yform_manager_table_api::removeTable($table);
        rex_sql_table::get($table)->drop();
    }
    $sql->setTable(rex::getTable('yform_field'));
    $sql->setWhere(
        'type_name=:type_name AND table_name=:table_name',
        [
            'table_name' => $tables['mapset'],
            'type_name' => 'geolocation_layerselect'
        ]
    );
    $sql->delete();
    rex_logger::factory()->log('info', 'Geolocation: Tabellen gelöscht; ' . $sql->getRows() . ' Felder aus yform_field entfernt');

    /**
     * Cronjobs löschen
     */
    $sql->setTable(rex::getTable('cronjob'));
    $sql->setWhere('type=:type', [':type' => 'FriendsOfRedaxo\\Geolocation\\Cronjob']);
    $sql->delete();

    /**
     * Meta-Feldtyp löschen
     * Falls es Metafelder mit diesem Typ gibt .... die hängen sonst im luftleeren Raum.
     * Die Metafelder selbst werden nicht gelöscht, da sie ja auch in anderen Addons genutzt werden könnten.
     *
     * Falls das Addon bereits dealtiviert ist, kann die Klasse PickerMetafield nicht ausgelesen werden.
     * Daher wird ggf. die Klasse wieder geladen
     */
    if (!class_exists(PickerMetafield::class)) {
        require_once __DIR__ . '/lib/Picker/PickerMetafield.php';
    }
    $sql->setTable(rex::getTable('metainfo_type'));
    $sql->setWhere(['label' => PickerMetafield::META_FIELD_TYPE]);
    $sql->delete();

} catch (RuntimeException $e) {
    $this->setProperty('installmsg', $e->getMessage());
}
