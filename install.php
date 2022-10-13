<?php
/**
 * Installations-Script.
 *
 * Install:    Installiert die Tabellen und YForm-Formulare
 * Re-Install: Repariert die Einstellungen
 *
 * Zusätzlich zu den von REDAXO selbst durchgeführten Aktivitäten:
 *
 * Tabellen werden auf die notwendigen Felder und Feldtypen eingestellt.
 * Zusätzliche Benutzerdefinierte Felder bleiben beim Re-Install erhalten.
 * Datensätze bleiben beim Re-Install erhalten, es sei denn Zurücksetzen eines
 * Feldes auf den Datentyp führt zu Verlusten.
 *
 * Die Formulare im YForm-Tablemanager werden zuerst gelöscht (falls vorhanden)
 * und dann neu angelegt. Die Formulare sind "Programmcode" und daher nicht Update-sicher!
 *
 * Der Cronjob wird - sofern es keinen Cronjob dieses Namens gibt - neu angelegt.
 * Beim Re-Install bleiben also die eigenen Einstellungen erhalten.
 */

namespace Geolocation;

use Exception;
use rex;
use rex_addon;
use rex_cronjob_manager_sql;
use rex_dir;
use rex_file;
use rex_path;
use rex_sql;
use rex_sql_column;
use rex_sql_table;
use rex_sql_util;
use rex_yform_manager_table;
use rex_yform_manager_table_api;

use function define;
use function defined;
use function is_bool;
use function is_string;

/**
 * @var rex_addon $this
 */

// das bin ich ...
if (!defined('Geolocation\ADDON')) {
    define('Geolocation\ADDON', $this->getName());
}
if (!defined('Geolocation\TTL_MIN')) {
    define('Geolocation\TTL_MIN', 0);
}

// Das sind die Tabellen
$layer = rex::getTable('geolocation_layer');
$mapset = rex::getTable('geolocation_mapset');

// Meldungen sammeln
$msg = [];

try {
    $sql = rex_sql::factory();

    // Vorgabewerte aus der install/config.yml einlesen.
    // Falls es eine instanzbezogene config.yml in Data-Verzeichnis gibt, wird diese
    // benutzt und nur fehlende Werte aus der install/config.yml gezogen.
    $systemConfig = rex_file::getConfig($this->getDataPath('config.yml'), []);
    $config = rex_file::getConfig(__DIR__ . '/install/config.yml', null);
    if (null === $config) {
        throw new InstallException($this->i18n('install_missing', 'install/config.yml'), 1);
    }
    $config = array_merge($config, $systemConfig);

    // Sicherstellen, dass die Tabellen in der Datenbank existieren und die erwarteten Felder haben.
    rex_sql_table::get($layer)
        ->ensurePrimaryIdColumn()
        ->ensureColumn(new rex_sql_column('name', 'varchar(191)'))
        ->ensureColumn(new rex_sql_column('url', 'text'))
        ->ensureColumn(new rex_sql_column('subdomain', 'varchar(191)'))
        ->ensureColumn(new rex_sql_column('attribution', 'varchar(191)'))
        ->ensureColumn(new rex_sql_column('lang', 'text'))
        ->ensureColumn(new rex_sql_column('layertype', 'text'))
        ->ensureColumn(new rex_sql_column('ttl', 'int(11)', true))
        ->ensureColumn(new rex_sql_column('cfmax', 'int(11)', true))
        ->ensureColumn(new rex_sql_column('online', 'text'))
        ->ensure();
    $msg[] = $this->i18n('install_table_prepared', $layer);

    rex_sql_table::get($mapset)
        ->ensurePrimaryIdColumn()
        ->ensureColumn(new rex_sql_column('name', 'varchar(191)'))
        ->ensureColumn(new rex_sql_column('title', 'varchar(191)'))
        ->ensureColumn(new rex_sql_column('layer', 'text'))
        ->ensureColumn(new rex_sql_column('overlay', 'text'))
        ->ensureColumn(new rex_sql_column('mapoptions', 'varchar(191)'))
        ->ensureColumn(new rex_sql_column('outfragment', 'varchar(191)'))
        ->ensure();
    $msg[] = $this->i18n('install_table_prepared', $mapset);

    // Tabellen vorbefüllen
    if ($config['dataset']['load']) {
        if (!$config['dataset']['overwrite']) {
            $config['dataset']['overwrite'] =
                (0 === $sql->setQuery('SELECT 1 FROM '.$layer.' LIMIT 1')->getRows())
                &&
                (0 === $sql->setQuery('SELECT 1 FROM '.$mapset.' LIMIT 1')->getRows());
        }

        if ($config['dataset']['overwrite']) {
            $demoDataset = false;
            $datasetfile = $this->getDataPath('dataset.sql');
            if (!is_readable($datasetfile)) {
                $datasetfile = __DIR__ . '/install/dataset.sql';
                $demoDataset = true;
            }
            $dataset = rex_file::get($datasetfile);
            if (null === $dataset) {
                throw new InstallException($this->i18n('install_missing', 'dataset.sql'), 1);
            }

            /**
             * Falls in der REX-Instanz ein anderes TablePrefix als "rex_" eingestellt ist: anpassen.
             *
             * STAN: If condition is always true.
             * In der Praxis wird das wohl so sein, aber formal ist der Fehler falsch.
             * @phpstan-ignore-next-line
             */
            if ('rex_' != rex::getTablePrefix()) {
                $dataset = str_replace(
                    ['`rex_geolocation_layer`', '`rex_geolocation_mapset`'],
                    ['`'.$layer.'`', '`'.$mapset.'`'],
                    $dataset
                );
            }
            // etwas kompliziert, da importDump unbedingt eine .sql-Datei will.
            $sqlfile = __DIR__ . '/tmp' . random_int(10000000, 99999999) . '.sql';
            file_put_contents($sqlfile, $dataset);
            rex_sql_util::importDump($sqlfile);
            unlink($sqlfile);
            // Hinweis auf ggf. auszutauschenden HERE-Key in Demo-Daten
            if ($demoDataset) {
                $msg[] = $this->i18n('geolocation_install_table_demo').
                        '<p class="alert alert-warning" style="margin:0">'.$this->i18n('geolocation_install_table_demo_api').'</p>';
            } else {
                $msg[] = $this->i18n('install_table_filled');
            }
        }
    }

    // YForm-Formulare im Tablemanager anlegen
    // GGf. noch vorhandene Reste aus fehlerhaften Installationen vorher löschen
    $tableset = rex_file::get(__DIR__ . '/install/tableset.json');
    if (null === $tableset) {
        throw new InstallException($this->i18n('install_missing', 'install/tableset.json'), 1);
    }
    /**
     * Falls in der REX-Instanz ein anderes TablePrefix als "rex_" eingestellt ist: anpassen.
     *
     * STAN: If condition is always true.
     * In der Praxis wird das wohl so sein, aber formal ist der Fehler falsch.
     * @phpstan-ignore-next-line
     */
    if ('rex_' != rex::getTablePrefix()) {
        $tableset = str_replace(
            ['"rex_geolocation_layer"', '"rex_geolocation_mapset"'],
            ['"'.$layer.'"', '"'.$mapset.'"'],
            $tableset
        );
    }

    try {
        rex_yform_manager_table_api::importTablesets($tableset);
        $msg[] = $this->i18n('install_tableset_prepared');
    } catch (Exception $e) {
        throw new InstallException($this->i18n('install_tableset', $e->getMessage()), 1);
    }

    // Cronjob anlegen falls es den Cronjob noch nicht gibt
    //  - Neuinstallation
    //  - Re-Installation wenn gelöscht oder umbenannt
    if (0 < $sql->setQuery('SELECT id, `type` FROM '.rex::getTable('cronjob').' WHERE name = ?', [Cronjob::LABEL])->getRows()) {
        $timestamp = rex_cronjob_manager_sql::calculateNextTime($config['job_intervall']);
        $sql->setTable(rex::getTable('cronjob'));
        $sql->setValue('name', Cronjob::LABEL);
        $sql->setValue('description', '');
        $sql->setValue('type', 'Geolocation\\Cronjob');
        $sql->setValue('parameters', '[]');
        $sql->setValue('interval', json_encode($config['job_intervall']));
        $sql->setValue('nexttime', rex_sql::datetime($timestamp));
        $sql->setValue('environment', $config['job_environment']);
        $sql->setValue('execution_moment', $config['job_moment']);
        $sql->setValue('execution_start', '0000-00-00 00:00:00');
        $sql->setValue('status', 1);
        $user = rex::getUser();
        if (null !== $user) {
            $sql->addGlobalUpdateFields($user->getLogin());
            $sql->addGlobalCreateFields($user->getLogin());
        }
        $sql->insert();
        $msg[] = $this->i18n('install_cronjob_prepared');
    }

    // rex_config: Default-Werte eintragen bzw. sicherstellen
    $ct = $sql->getArray('SELECT id FROM '.$mapset.' ORDER BY id ASC LIMIT 1');
    $this->setConfig('default_map', $this->getConfig('default_map', $ct[0]['id'] ?? 0));
    $this->setConfig('map_components', $this->getConfig('map_components', '|'.implode('|', array_keys(mapset::$mapoptions)).'|'));
    $this->setConfig('map_bounds', $this->getConfig('map_bounds', $config['bounds']));
    $this->setConfig('map_zoom', $this->getConfig('map_zoom', $config['zoom']));
    $this->setConfig('map_zoom_min', $this->getConfig('map_zoom_min', $config['zoom_min']));
    $this->setConfig('map_zoom_max', $this->getConfig('map_zoom_max', $config['zoom_max']));
    $this->setConfig('map_outfragment', $this->getConfig('map_outfragment', $config['Geolocation\\OUT']));
    $this->setConfig('cache_ttl', $this->getConfig('ttl', $config['Geolocation\\TTL_DEF']));
    $this->setConfig('cache_maxfiles', $this->getConfig('maxfiles', $config['Geolocation\\CFM_DEF']));
    $this->setConfig('compile', $this->getConfig('compile', $config['scope']['compile']));

    // Ausgewählte Vorgabewerte als "define(...)" in die boot.php schreiben
    $defines = PHP_EOL;
    $config['Geolocation\\LOAD'] = $config['scope']['load'];
    $definedValues = [];
    foreach ($config as $k => $v) {
        if ('Geolocation\\' !== substr($k, 0, 12)) {
            continue;
        }
        $definedValues[$k] = $v;
        if (is_string($v)) {
            $v = "'$v'";
        } elseif (is_bool($v)) {
            $v = $v ? 'true' : 'false';
        }
        $defines .= "define('$k',$v);".PHP_EOL;
    }
    $boot_php = rex_file::get(__DIR__.'/boot.php');
    if (null !== $boot_php) {
        $boot_php = preg_replace('%(//##start)(.*?)(//##end)%s', '$1'.$defines.'$3', $boot_php, 1);
        rex_file::put(__DIR__.'/boot.php', (string) $boot_php);
    }

    // Die JS/CSS-Dateien neu kompilieren, um Instanz-eigene Erweiterungen und Parameter
    // aus data/addons/geolocation einzubinden
    ConfigForm::compileAssets(__DIR__.'/', $definedValues);
    $msg[] = $this->i18n('install_assets_prepared');

    // Den Ordner 'data/addons/geolocation/assets' falls vorhanden in den Ordner 'assets/addons/geolocation' kopieren
    $copyDir = rex_path::addonData(ADDON, 'assets');
    if (is_dir($copyDir)) {
        rex_dir::copy($copyDir, rex_path::addonAssets(ADDON));
    }

    // System_Cache löschen
    rex_delete_cache();
    rex_yform_manager_table::deleteCache();

    // Ergebnis übermitteln
    $this->setProperty('successmsg', '<ul><li>'.implode('</li><li>', $msg).'</li></ul>');
} catch (InstallException $e) {
    $this->setProperty('installmsg', $e->getMessage());
} catch (Exception $e) {
    $this->setProperty('installmsg', $e->getMessage().' (file '.$e->getFile().' line '.$e->getLine().')');
} finally {
    // Sicher ist sicher
    if (isset($filename)) {
        @unlink($filename);
    }
    if (isset($sqlfile)) {
        @unlink($sqlfile);
    }
}
