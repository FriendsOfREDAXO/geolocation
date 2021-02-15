<?php
/**
 *  Installations-Script
 *
 *  Install:    Installiert die Tabellen und YForm-Formulare
 *  Re-Install: Repariert die Einstellungen
 *
 *  Zusätzlich zu den von REDAXO selbst durchgeführten Aktivitäten:
 *
 *  Tabellen werden auf die notwendigen Felder und Feldtypen eingestellt.
 *  Zusätzliche Benutzerdefinierte Felder bleiben beim Re-Install erhalten.
 *  Datensätze bleiben beim Re-Install erhalten, es sei denn Zurücksetzen eines
 *  Feldes auf den Datentyp führt zu Verlusten.
 *
 *  Die Formulare im YForm-Tablemanager werden zuerst gelöscht (falls vorhanden)
 *  und dann neu angelegt. Die Formulare sind "Programmcode" und daher nicht Update-sicher!
 *
 *  Der Cronjob wird - sofern es keinen Cronjob dieses Namens gibt - neu angelegt.
 *  Beim Re-Install bleiben also die eigenen Einstellungen erhalten.
 *
 *  @var \rex_addon $this
 */

// Interne Exception-Klasse
class GeolocationInstallException extends \RuntimeException {}

// benötigt Support aus ...
include( __DIR__ . '/lib/cronjob.php' );
include( __DIR__ . '/lib/yform/dataset/mapset.php' );
include( __DIR__ . '/lib/config_form.php' );

// das bin ich ...
if( !defined('Geolocation\ADDON') ) define( 'Geolocation\ADDON', $this->getName() );
if( !defined('Geolocation\TTL_MIN') ) define( 'Geolocation\TTL_MIN', 0 );

// Das sind die Tabellen
$layer = \rex::getTable('geolocation_layer');
$mapset = \rex::getTable('geolocation_mapset');

// Meldungen sammeln
// for future use (https://github.com/redaxo/redaxo/issues/3961)
$msg = [];

try {

    $sql = \rex_sql::factory();

    // Vorgabewerte aus der install/config.yml einlesen.
    // Falls es eine instanzbezogenen config.yml in Data-Verzeichnis gibt, wird diese
    // benutzt und nur fehlende Werte aus der install/config.yml gezogen.
    $systemConfig = \rex_file::getConfig( $this->getDataPath('config.yml'), [] ) ?: [];
    $config = \rex_file::getConfig( __DIR__ . '/install/config.yml', [] );
    if( !$config ) {
        throw new \GeolocationInstallException($this->i18n('install_missing','install/config.yml'), 1);
    }
    $config = array_merge( $config, $systemConfig );

    // Sicherstellen, dass die Tabellen in der Datenbank existieren und die erwarteten Felder haben.
    \rex_sql_table::get($layer)
        ->ensurePrimaryIdColumn()
        ->ensureColumn(new \rex_sql_column('name', 'varchar(191)'))
        ->ensureColumn(new \rex_sql_column('url', 'text'))
        ->ensureColumn(new \rex_sql_column('subdomain', 'varchar(191)'))
        ->ensureColumn(new \rex_sql_column('attribution', 'varchar(191)'))
        ->ensureColumn(new \rex_sql_column('lang', 'text'))
        ->ensureColumn(new \rex_sql_column('layertype', 'text'))
        ->ensureColumn(new \rex_sql_column('ttl', 'int(11)', true))
        ->ensureColumn(new \rex_sql_column('cfmax', 'int(11)', true))
        ->ensureColumn(new \rex_sql_column('online', 'text'))
        ->ensure();
    $msg[] = $this->i18n( 'install_table_prepared',$layer);

    \rex_sql_table::get($mapset)
        ->ensurePrimaryIdColumn()
        ->ensureColumn(new \rex_sql_column('name', 'varchar(191)'))
        ->ensureColumn(new \rex_sql_column('title', 'varchar(191)'))
        ->ensureColumn(new \rex_sql_column('layer', 'text'))
        ->ensureColumn(new \rex_sql_column('overlay', 'text'))
        ->ensureColumn(new \rex_sql_column('mapoptions', 'varchar(191)'))
        ->ensureColumn(new \rex_sql_column('outfragment', 'varchar(191)'))
        ->ensure();
    $msg[] = $this->i18n( 'install_table_prepared',$mapset);

    // Tabellen vorbefüllen
    if( $config['dataset']['load'] ){

        if( !$config['dataset']['overwrite'] ){
            $config['dataset']['overwrite'] =
                ( 0 == $sql->setQuery('SELECT 1 FROM '.$layer.' LIMIT 1')->getRows() )
                &&
                ( 0 == $sql->setQuery('SELECT 1 FROM '.$mapset.' LIMIT 1')->getRows() );
        }

        if( $config['dataset']['overwrite'] ){
            $datasetfile = $this->getDataPath('dataset.sql');
            if( !is_readable($datasetfile) ){
                $datasetfile = __DIR__ . '/install/dataset.sql';
            }
            $dataset = \rex_file::get( $datasetfile );
            if( !$dataset ) {
                throw new \GeolocationInstallException($this->i18n('install_missing','dataset.sql'), 1);
            }
            // Falls in der REX-Instanz ein anderes TablePrefix als "rex" eingestellt ist: anpassen
            if( 'rex' != rex::getTablePrefix() ) {
                $dataset = str_replace(
                    ['`rex_geolocation_layer`','`rex_geolocation_mapset`'],
                    ['`'.$layer.'`','`'.$mapset.'`'],
                    $dataset
                );
            }
            $filename = tempnam( sys_get_temp_dir(), '' );
            file_put_contents( $filename, $dataset );
            \rex_sql_util::importDump( $filename );
            unlink( $filename );
            $msg[] = $this->i18n( 'install_table_filled');
        }
    }

    // YForm-Formulare im Tablemanager anlegen
    // GGf. noch vorhandene Reste aus fehlerhaften Installationen vorher löschen
    $tableset = \rex_file::get( __DIR__ . '/install/tableset.json' );
    if( !$tableset ) {
        throw new \GeolocationInstallException($this->i18n('install_missing','install/tableset.json'), 1);
    }
    // Falls in der REX-Instanz ein anderes TablePrefix als "rex" eingestellt ist: anpassen
    if( 'rex' != \rex::getTablePrefix() ) {
        $tableset = str_replace(
            ['"rex_geolocation_layer"','"rex_geolocation_mapset"'],
            ['"'.$layer.'"','"'.$mapset.'"'],
            $tableset
        );
    }
    \rex_yform_manager_table_api::removeTable($layer);
    \rex_yform_manager_table_api::removeTable($mapset);
    \rex_yform_manager_table_api::importTablesets($tableset);
    $msg[] = $this->i18n( 'install_tableset_prepared');

    // Cronjob anlegen falls es den Cronjob noch nicht gibt
    //  - Neuinstallation
    //  - Re-Installation wenn gelöscht oder umbenannt
    if( !$sql->getArray('SELECT id FROM '.\rex::getTable( 'cronjob').' WHERE name = ?',[\Geolocation\cronjob::LABEL]) ) {
        $timestamp = \rex_cronjob_manager_sql::calculateNextTime($config['job_intervall']);
        $sql->setTable( \rex::getTable( 'cronjob') );
        $sql->setValue( 'name', \Geolocation\cronjob::LABEL );
        $sql->setValue( 'description', '' );
        $sql->setValue( 'type', 'Geolocation\\cronjob' );
        $sql->setValue( 'parameters', '[]' );
        $sql->setValue( 'interval', json_encode( $config['job_intervall'] ) );
        $sql->setValue( 'nexttime', \rex_sql::datetime($timestamp) );
        $sql->setValue( 'environment', $config['job_environment'] );
        $sql->setValue( 'execution_moment', $config['job_moment'] );
        $sql->setValue( 'execution_start', '0000-00-00 00:00:00' );
        $sql->setValue( 'status', 1 );
        $sql->addGlobalUpdateFields( \rex::getUser()->getLogin() );
        $sql->addGlobalCreateFields( \rex::getUser()->getLogin() );
        $sql->insert();
        $msg[] = $this->i18n( 'install_cronjob_prepared');
    }

    // rex_config: Default-Werte eintragen bzw. sicherstellen
    $ct = $sql->getArray('SELECT id FROM '.$mapset.' ORDER BY id ASC LIMIT 1');
    $this->setConfig('default_map',$this->getConfig('default_map',$ct ? $ct[0]['id'] : 0 ));
    $this->setConfig('map_components',$this->getConfig('map_components','|'.implode('|',array_keys(\Geolocation\mapset::$mapoptions)).'|'));
    $this->setConfig('map_bounds',$this->getConfig('map_bounds',$config['bounds']));
    $this->setConfig('map_zoom',$this->getConfig('map_zoom',$config['zoom']));
    $this->setConfig('map_zoom_min',$this->getConfig('map_zoom_min',$config['zoom_min']));
    $this->setConfig('map_zoom_max',$this->getConfig('map_zoom_max',$config['zoom_max']));
    $this->setConfig('map_outfragment',$this->getConfig('map_outfragment',$config['Geolocation\\OUT']));
    $this->setConfig('cache_ttl',$this->getConfig('ttl',$config['Geolocation\\TTL_DEF']));
    $this->setConfig('cache_maxfiles',$this->getConfig('maxfiles',$config['Geolocation\\CFM_DEF']));
    $this->setConfig('compile',$this->getConfig('compile',$config['scope']['compile']));

    // Ausgewählte Vorgabewerte als "define(...)" in die boot.php schreiben
    $defines = PHP_EOL;
    $config['Geolocation\\LOAD'] = $config['scope']['load'];
    foreach( $config as $k=>$v ) {
        if( 'Geolocation\\' !== substr($k,0,12) ) continue;
        if( is_string($v) ) {
            $v = "'$v'";
        } elseif( is_bool($v) ) {
            $v = $v ? 'true' : 'false';
        }
        $defines .= "define('$k',$v);".PHP_EOL;
    }
    $boot_php = rex_file::get( __DIR__.'/'.self::FILE_BOOT );
    if( $boot_php ){
        $boot_php = preg_replace( '%(//##start)(.*?)(//##end)%s', '$1'.$defines.'$3',$boot_php, 1 );
        \rex_file::put( __DIR__.'/'.self::FILE_BOOT, $boot_php );
    }

    // Die JS/CSS-Dateien neu kompilieren, um Instanz-eigene Erweiterungen und Parameter
    // aus data/addons/geolocation einzubinden
    \Geolocation\config_form::compileAssets( __DIR__.'/' );
    $msg[] = $this->i18n( 'install_assets_prepared' );

    // Den Ordner 'data/addons/assets' falls vorhanden in den Ordner 'assets/addons/geolocation' kopieren
    $copyDir =  \rex_path::addonData(\Geolocation\ADDON,'assets');
    if( is_dir( $copyDir ) ) {
        \rex_dir::copy( $copyDir, \rex_path::addonAssets(\Geolocation\ADDON) );
    }


    //System_Cache löschen
    \rex_delete_cache();
    \rex_yform_manager_table::deleteCache();

    if( $msg ){
        $this->setProperty('successmsg','<ul><li>'.implode('</li><li>',$msg).'</li></ul>');
    }

} catch (\GeolocationInstallException $e) {

    $this->setProperty('installmsg', $e->getMessage() );

} catch (\Exception $e) {

    $this->setProperty('installmsg', $e->getMessage().' (file '.$e->getFile().' line '.$e->getLine().')' );

} finally {

    if( isset($filename) ) @unlink( $filename );

}
