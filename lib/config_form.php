<?php
namespace Geolocation;

/**
 * Konfiguration - Basisdaten und Defaults
 *
 * @package geolocation
 */
class config_form extends \rex_config_form
{

    public $addon = '';

    protected function __construct($namespace, $fieldset = null, $debug = false)
    {
        parent::__construct($namespace, $fieldset, $debug);
        $this->addon = $namespace;

        if( !PROXY_ONLY ){
            $this->addFieldset( \rex_i18n::msg('geolocation_config_map') );

                $field = $this->addSelectField('default_map',$value = null,['class'=>'form-control']);
                $field->setLabel( \rex_i18n::msg('geolocation_config_map_default') );
                $select = $field->getSelect();
                $select->addSqlOptions( 'SELECT concat(title," [id=",id,"]") as name,id FROM '.mapset::table()->getTableName().' ORDER BY title' );

                $field = $this->addTextField( 'map_bounds' );
                $field->setLabel( \rex_i18n::msg('geolocation_form_map_bounds') );
                $field->setNotice( \rex_i18n::msg('geolocation_form_map_bounds_notice') );
                $errorMsg = \rex_i18n::msg('geolocation_form_map_bounds_error');
                $field->getValidator()
                      ->add( 'notEmpty', $errorMsg )
                      ->add( 'match', $errorMsg.'#', '/^\s*\[[+-]?\d+(\.\d+)?,\s*[+-]?\d+(\.\d+)?\],\s*[[+-]?\d+(\.\d+)?,\s*[+-]?\d+(\.\d+)?\]\s*$/');

                # geolocation_form_map_zoom
                # geolocation_form_map_zoom_error
                $field = $this->addTextField( 'map_zoom' );
                $field->setLabel( \rex_i18n::msg('geolocation_form_map_zoom') );
                $field->setNotice( \rex_i18n::msg('geolocation_form_map_zoom_notice',ZOOM_MIN,ZOOM_MAX) );
                $field->getValidator()
                      ->add( 'notEmpty', \rex_i18n::msg('geolocation_form_map_zoom_error'))
                      ->add( 'type', \rex_i18n::msg('geolocation_form_map_zoom_error'), 'integer')
                      ->add( 'min', \rex_i18n::msg('geolocation_form_map_zoom_error'), ZOOM_MIN)
                      ->add( 'max', \rex_i18n::msg('geolocation_form_map_zoom_error'),ZOOM_MAX);

                $field = $this->addCheckboxField('map_components');
                $field->setLabel(\rex_i18n::msg('geolocation_form_mapoptions'));
                foreach( mapset::$mapoptions as $k=>$v ) {
                    $field->addOption(\rex_i18n::translate($v), $k);
                }

                $field = $this->addTextField( 'map_outfragment' );
                $field->setLabel( \rex_i18n::msg('geolocation_form_outfragment') );
                $field->setNotice( \rex_i18n::msg('geolocation_form_map_outfragment_notice',OUT) );
                $field->getValidator()
                    ->add( 'notEmpty', \rex_i18n::msg('geolocation_form_map_outfragment_error'))
                    ->add( 'match', \rex_i18n::msg('geolocation_form_map_outfragment_error'), '/^.*?\.php$/');
        }

        $this->addFieldset( \rex_i18n::msg('geolocation_config_proxycache') );

            $field = $this->addTextField( 'cache_ttl' );
            $field->setLabel( \rex_i18n::msg('geolocation_form_proxycache_ttl') );
            $field->setNotice( \rex_i18n::rawMsg('geolocation_form_proxycache_ttl_notice',TTL_MAX) );
            $errorMsg = \rex_i18n::msg('geolocation_form_proxycache_ttl_error',TTL_MAX);
            $field->getValidator()
                  ->add( 'notEmpty', $errorMsg )
                  ->add( 'type', $errorMsg, 'integer' )
                  ->add( 'min', $errorMsg, TTL_MIN )
                  ->add( 'max', $errorMsg, TTL_MAX );

            $field = $this->addTextField( 'cache_maxfiles' );
            $field->setLabel( \rex_i18n::msg('geolocation_form_proxycache_maxfiles') );
            $field->setNotice( \rex_i18n::msg('geolocation_form_proxycache_maxfiles_notice') );
            $errorMsg = \rex_i18n::msg('geolocation_form_proxycache_maxfiles_error',CFM_MIN);
            $field->getValidator()
                  ->add( 'notEmpty', $errorMsg )
                  ->add( 'type', $errorMsg, 'integer' )
                  ->add( 'min', $errorMsg, CFM_MIN );
    }

    protected function getValue($name)
    {
        $value = parent::getValue($name);
        if( !$value && 'outfragment' === $name ) {
            $value = OUT;
        }
        return $value;
    }

    protected function save() : bool
    {
        // Bounds-Koordinaten um Leerzeichen erleichtern (nur wenn Karten-Fieldset aktiviert)
        $bounds = $this->getElement(\rex_i18n::msg('geolocation_config_map'),'map_bounds');
        if( $bounds ){
            $value = $bounds->getValue();
            $value = str_replace(' ','',$value);
            $bounds->setValue($value);
        }

        $ok = parent::save();

        // Die Änderungen auch nach /assets/addons/geolocation/ schreiben
        if( $ok ) {
            self::compileAssets( );
        }

        return $ok;
    }

    static function compileAssets( $addonDir=null ) : void
    {
        $addonDir = $addonDir ?: \rex_path::addon(ADDON);
        $dataDir = \rex_path::addonData(ADDON);
        $assetDir = \rex_path::addonAssets(ADDON);

        // AssetPacker-Instanzen für die Asset-Dateien öffnen
        // Kartensoftware
        $css = \AssetPacker\AssetPacker::target( $assetDir.'geolocation.min.css')
            ->overwrite();
        $js = \AssetPacker\AssetPacker::target( $assetDir.'geolocation.min.js')
            ->overwrite();
        // CCS für Backend-Formulare
        $be_css = \AssetPacker\AssetPacker::target( $assetDir.'geolocation_be.min.css')
            ->overwrite()
            ->addFile( $addonDir.'install/geolocation_be.css' );

        // Leaflet und Co wird nur eingebaut, wenn auch angefordert
        if( 0 < \rex_config::get(ADDON,'compile',2) ){
            // der Leaflet-Core
            $css
                ->addFile( $addonDir.'install/vendor/leaflet/leaflet.css' );
            $js
                ->addFile( $addonDir.'install/vendor/leaflet/leaflet.js',true )
                ->replace( '//# sourceMappingURL=leaflet.js.map','' );
        }
        if( 1 < \rex_config::get(ADDON,'compile',2) ){
            // Zusätzlich die von Geolocation benötigten Plugins und der Geolocation-Code
            $css
                ->addFile( $addonDir.'install/vendor/Leaflet.GestureHandling/leaflet-gesture-handling.min.css' )
                ->addFile( $addonDir.'install/geolocation.css' );
            $js
                ->addFile( $addonDir.'install/vendor/Leaflet.GestureHandling/leaflet-gesture-handling.min.js' )
                ->replace( '//# sourceMappingURL=leaflet-gesture-handling.min.js.map','' )
                ->addFile( $addonDir.'install/geolocation.js' )
                ->replace( '%defaultBounds%', \rex_config::get(ADDON,'map_bounds') )
                ->replace( '%defaultZoom%', \rex_config::get(ADDON,'map_zoom') )
                ->replace( '%zoomMin%', \rex_config::get(ADDON,'map_zoom_min') )
                ->replace( '%zoomMax%', \rex_config::get(ADDON,'map_zoom_max') )
                ->replace( '%defaultFullscreen%', (false === strpos(\rex_config::get(ADDON,'map_components'),'|fullscreen|') ? 'false' : 'true') )
                ->replace( '%defaultGestureHandling%', (false === strpos(\rex_config::get(ADDON,'map_components'),'|gestureHandling|') ? 'false' : 'true') )
                ->replace( '%defaultLocateControl%', (false === strpos(\rex_config::get(ADDON,'map_components'),'|locateControl|') ? 'false' : 'true') )
                ->replace( '%i18n%', \rex_file::get($dataDir.'lang',\rex_file::get($addonDir.'install/lang','')) );
        }

        // Die optionalen individuellen Komponenten aus dem data-Verzeichnis holen
        // ggf. zusätzliche komplexe Elemente dazuladen
        if( is_readable($dataDir.'load_assets.php') ) {
            include $dataDir.'load_assets.php';
        } else {
            $css
                ->addOptionalFile( $dataDir.'geolocation.css');
            $js
                ->addOptionalFile( $dataDir.'geolocation.js')
                ->regReplace( '%//#\s+sourceMappingURL=.*?$%im','//' );
            $be_css
                ->addOptionalFile( $dataDir.'geolocation_be.css');
        }

        // Zieldateien final erstellen
        $js->create();
        $css->create();
        $be_css->create();

    }

}
