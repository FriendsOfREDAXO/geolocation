<?php

/**
 * Konfiguration - Basisdaten und Defaults.
 */

namespace FriendsOfRedaxo\Geolocation;

use FriendsOfRedaxo\Geolocation\AssetPacker\AssetPacker;
use rex_config;
use rex_config_form;
use rex_file;
use rex_i18n;
use rex_path;

use function defined;

class ConfigForm extends rex_config_form
{
    /**
     * Initialisiert das Formular selbst.
     */
    public function init(): void
    {
        parent::init();

        if (!PROXY_ONLY) {
            $this->addFieldset(rex_i18n::msg('geolocation_config_map'));

            $field = $this->addSelectField('default_map', $value = null, ['class' => 'form-control']);
            $field->setLabel(rex_i18n::msg('geolocation_config_map_default'));
            $select = $field->getSelect();
            $select->addSqlOptions('SELECT concat(title," [id=",id,"]") as name,id FROM ' . Mapset::table()->getTableName() . ' ORDER BY title');

            $field = $this->addTextField('map_bounds');
            $field->setLabel(rex_i18n::msg('geolocation_form_map_bounds'));
            $field->setNotice(rex_i18n::msg('geolocation_form_map_bounds_notice'));
            $errorMsg = rex_i18n::msg('geolocation_form_map_bounds_error');
            $field->getValidator()
                  ->add('notEmpty', $errorMsg)
                  ->add('match', $errorMsg . '#', '/^\s*\[[+-]?\d+(\.\d+)?,\s*[+-]?\d+(\.\d+)?\],\s*[[+-]?\d+(\.\d+)?,\s*[+-]?\d+(\.\d+)?\]\s*$/');

            $field = $this->addTextField('map_zoom');
            $field->setLabel(rex_i18n::msg('geolocation_form_map_zoom'));
            $field->setNotice(rex_i18n::msg('geolocation_form_map_zoom_notice', ZOOM_MIN, ZOOM_MAX));
            $errorMsg = rex_i18n::msg('geolocation_form_map_zoom_error');
            $field->getValidator()
                  ->add('notEmpty', $errorMsg)
                  ->add('type', $errorMsg, 'integer')
                  ->add('min', $errorMsg, ZOOM_MIN)
                  ->add('max', $errorMsg, ZOOM_MAX);

            $field = $this->addCheckboxField('map_components');
            $field->setLabel(rex_i18n::msg('geolocation_form_mapoptions'));
            foreach (Mapset::$mapoptions as $k => $v) {
                $field->addOption(rex_i18n::translate($v), $k);
            }

            $field = $this->addTextField('map_outfragment');
            $field->setLabel(rex_i18n::msg('geolocation_form_outfragment'));
            $field->setNotice(rex_i18n::msg('geolocation_form_map_outfragment_notice', OUT));
            $errorMsg = rex_i18n::msg('geolocation_form_map_outfragment_error');
            $field->getValidator()
                  ->add('notEmpty', $errorMsg)
                  ->add('match', $errorMsg, '/^.*?\.php$/');

            $this->addFieldset(rex_i18n::msg('geolocation_config_geopicker'));

            $minRadius = (int) rex_config::get(ADDON, 'picker_min_radius');
            $field = $this->addTextField('picker_radius');
            $field->setLabel(rex_i18n::msg('geolocation_form_geopicker_radius'));
            $field->setAttribute('type', 'number');
            $field->setAttribute('min', $minRadius);
            $field->setNotice(rex_i18n::rawMsg('geolocation_form_geopicker_radius_notice', $minRadius));
            $errorMsg = rex_i18n::msg('geolocation_config_geocoding_radius_error', rex_i18n::msg('geopicker_radius'), $minRadius);
            $field->getValidator()
                ->add('notEmpty', $errorMsg)
                ->add('type', $errorMsg, 'integer')
                ->add('min', $errorMsg, $minRadius);
        }

        $this->addFieldset(rex_i18n::msg('geolocation_config_proxycache'));

        $field = $this->addTextField('cache_ttl');
        $field->setLabel(rex_i18n::msg('geolocation_form_proxycache_ttl'));
        $field->setNotice(rex_i18n::rawMsg('geolocation_form_proxycache_ttl_notice', TTL_MAX));
        $errorMsg = rex_i18n::msg('geolocation_form_proxycache_ttl_error', TTL_MAX);
        $field->getValidator()
              ->add('notEmpty', $errorMsg)
              ->add('type', $errorMsg, 'integer')
              ->add('min', $errorMsg, TTL_MIN)
              ->add('max', $errorMsg, TTL_MAX);

        $field = $this->addTextField('cache_maxfiles');
        $field->setLabel(rex_i18n::msg('geolocation_form_proxycache_maxfiles'));
        $field->setNotice(rex_i18n::msg('geolocation_form_proxycache_maxfiles_notice'));
        $errorMsg = rex_i18n::msg('geolocation_form_proxycache_maxfiles_error', CFM_MIN);
        $field->getValidator()
              ->add('notEmpty', $errorMsg)
              ->add('type', $errorMsg, 'integer')
              ->add('min', $errorMsg, CFM_MIN);
    }

    /**
     * Feldwerte abrufen - Sonderfall 'outfragment'.
     *
     * Das Feld muss immer gefüllt wein, daher wird hier wenn es leer ist der Fallback-Wert
     * eingetragen.
     */
    protected function getValue($name): mixed
    {
        $value = parent::getValue($name);
        if ('outfragment' === $name && '' === $value) {
            $value = OUT;
        }
        return $value;
    }

    /**
     * Speichert die Änderungen in der Datenbank.
     *
     * Das formal kritische Freitextfeld map_bounds wird normalisiert
     * Auf Basis der Setttings werden die Assets neu kompiliert.
     */
    protected function save(): bool|string|int
    {
        // Bounds-Koordinaten um Leerzeichen erleichtern (nur wenn Karten-Fieldset aktiviert)
        $bounds = $this->getElement(rex_i18n::msg('geolocation_config_map'), 'map_bounds');
        if (null !== $bounds) {
            $value = (string) $bounds->getValue();
            $value = str_replace(' ', '', $value);
            $bounds->setValue($value);
        }

        $ok = parent::save();

        // Die Änderungen auch nach /assets/addons/geolocation/ schreiben
        if (true === $ok) {
            self::compileAssets();
        }

        return $ok;
    }

    /**
     * Compiliert die Assets (CSS/JS) basierend auf den Settings neu.
     *
     * Die Angabe des AddonDir ist nur notwendig, wenn aus dem Update-
     * oder mögicherweise auch Installation-Kontext heraus aufgerufen.
     *
     * Analog gilt dies für das Array mit Konstanten.
     *
     * Siehe Dokumentation
     *
     * @param array<string,string|int|bool> $constant
     */
    public static function compileAssets(?string $addonDir = null, array $constant = []): void
    {
        $addonDir = null === $addonDir ? rex_path::addon(ADDON) : $addonDir;
        $dataDir = rex_path::addonData(ADDON);
        $assetDir = rex_path::addonAssets(ADDON);

        // Die folgenden Konstanten müssen angegeben sein. In der (Re-)Installation aus $constant,
        // sonst in der boot.php definiert.
        // Entweder (install) sind sie noch nicht gesetzt (kein define) oder beim reinstall sind sie
        // via boot.php gesetzt, werden aber durch neuere Werte aus der Reinstallation überschrieben
        // leer geht gar nicht: das muss ein Fehler ein.
        $keyMapset = $constant['FriendsOfRedaxo\\Geolocation\\KEY_MAPSET'] ?? (defined('FriendsOfRedaxo\\Geolocation\\KEY_MAPSET') ? KEY_MAPSET : null);
        if (null === $keyMapset) {
            throw new DeveloperException('Constant "FriendsOfRedaxo\Geolocation\\KEY_MAPSET" missing. Check your boot.php or config.yml (install)', 1);
        }
        $keyTiles = $constant['FriendsOfRedaxo\\Geolocation\\KEY_TILES'] ?? (defined('FriendsOfRedaxo\\Geolocation\\KEY_TILES') ? KEY_TILES : null);
        if (null === $keyTiles) {
            throw new DeveloperException('Constant "Geolocation\\KEY_TILES" missing. Check your boot.php or config.yml (install)', 1);
        }

        // AssetPacker-Instanzen für die Asset-Dateien öffnen
        // Kartensoftware

        $css = AssetPacker::target($assetDir . 'geolocation.min.css')
            ->overwrite();
        $js = AssetPacker::target($assetDir . 'geolocation.min.js')
            ->overwrite();
        // CCS für Backend-Formulare
        $be_css = AssetPacker::target($assetDir . 'geolocation_be.min.css')
            ->overwrite()
            ->addFile($addonDir . 'install/geolocation_be.scss');
        // JS für Backend-Formulare
        $be_js = AssetPacker::target($assetDir . 'geolocation_be.min.js')
            ->overwrite()
            ->addFile($addonDir . 'install/geolocation_be.js');

        // JS für den YForm-Tablemanager (Feldkonfiguration)
        $be_js_tablemanager = AssetPacker::target($assetDir . 'tablemanager.min.js')
            ->overwrite()
            ->addFile($addonDir . 'install/tablemanager.js');

        // Leaflet und Co wird nur eingebaut, wenn auch angefordert
        if (0 < rex_config::get(ADDON, 'compile', 2)) {
            // der Leaflet-Core
            $css
                ->addFile($addonDir . 'install/vendor/leaflet/leaflet.css');
            $js
                ->addFile($addonDir . 'install/vendor/leaflet/leaflet.js', true)
                ->replace('//# sourceMappingURL=leaflet.js.map', '');
        }
        if (1 < rex_config::get(ADDON, 'compile', 2)) {
            // Zusätzlich die von Geolocation benötigten Plugins und der Geolocation-Code
            $css
                ->addFile($addonDir . 'install/vendor/Leaflet.GestureHandling/leaflet-gesture-handling.min.css')
                ->addFile($addonDir . 'install/geolocation.scss');
            $js
                ->addFile($addonDir . 'install/vendor/Leaflet.GestureHandling/leaflet-gesture-handling.min.js')
                ->replace('//# sourceMappingURL=leaflet-gesture-handling.min.js.map', '')
                ->addFile($addonDir . 'install/geolocation.js')
                ->replace('%keyMapset%', '\'' . $keyMapset . '\'')
                ->replace('%keyLayer%', '\'' . $keyTiles . '\'')
                ->replace('%defaultBounds%', rex_config::get(ADDON, 'map_bounds'))
                ->replace('%defaultZoom%', rex_config::get(ADDON, 'map_zoom'))
                ->replace('%zoomMin%', rex_config::get(ADDON, 'map_zoom_min'))
                ->replace('%zoomMax%', rex_config::get(ADDON, 'map_zoom_max'))
                ->replace('%defaultFullscreen%', !str_contains(rex_config::get(ADDON, 'map_components'), '|fullscreen|') ? 'false' : 'true')
                ->replace('%defaultGestureHandling%', !str_contains(rex_config::get(ADDON, 'map_components'), '|gestureHandling|') ? 'false' : 'true')
                ->replace('%defaultLocateControl%', !str_contains(rex_config::get(ADDON, 'map_components'), '|locateControl|') ? 'false' : 'true')
                ->replace('%i18n%', rex_file::get($dataDir . 'lang_js', rex_file::get($addonDir . 'install/lang_js', '')));
        }

        // Die optionalen individuellen Komponenten aus dem data-Verzeichnis holen
        // ggf. zusätzliche komplexe Elemente dazuladen
        if (is_readable($dataDir . 'load_assets.php')) {
            include $dataDir . 'load_assets.php';
        } else {
            $cssFile = $dataDir . 'geolocation.scss';
            if (!is_file($cssFile)) {
                $cssFile = $dataDir . 'geolocation.css';
            }
            $css
                ->addOptionalFile($cssFile);
            $js
                ->addOptionalFile($dataDir . 'geolocation.js')
                ->regReplace('%//#\s+sourceMappingURL=.*?$%im', '//');
            $cssFile = $dataDir . 'geolocation_be.scss';
            if (!is_file($cssFile)) {
                $cssFile = $dataDir . 'geolocation_be.css';
            }
            $be_css
                ->addOptionalFile($cssFile);
        }
        // Zieldateien final erstellen
        $js->create();
        $css->create();
        $be_css->create();
        $be_js->create();
        $be_js_tablemanager->create();
    }
}
