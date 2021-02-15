<?php
namespace Geolocation;

/*
yform-dataset to enhance rex_geolocation_mapset:

    - dataset-spezifisch

        get             Modifiziert um anschließend Initialisierungen durchzuführen
        getForm         baut einige Felder im Formular aus aktuelle Daten um
        delete          Löschen nur wenn nicht in Benutzung z.B. in Slices/Modulen etc.
                        Abfrage durch EP GEOLOCATION_MAPSET_DELETE, der entsprechend belegt werden
                        muss

    - Formularbezogen

        executeForm     stellt aktuelle Konfig-Daten als Vorbelegung in ein Add-Formular

    - Listenbezogen

    - AJAX-Abrufe

        sendMapset      Kartensatz-Abruf vom Client beantworten

    - Support

        getLayerset     Array z.B. für <rex-map mapset=...> bereitstellen (Kartensatzparameter)
        getMapOptions   Array z.B. für <rex-map map=...> bereitstellen (Kartenoptionen)
        getOutFragment  das für Kartendarstellung vorgesehene Fragment (outfragment) mit Fallback
                        falls outfragment leer ist
        getDefaultOutFragment   Default-Ausgabefragment aus Config und Fallback
        take            Alternative zu get, aber mit Fallback auf die Default-Karte
        attributes      sammelt die sonstigen HTML-Attribute ein
        dataset         sammelt die Karteninhalte ein (siehe Geolocation.js -> Tools)
        parse           erzeugt Karten-HTML gem. vorgegeneben Fragment

*/

class mapset extends \rex_yform_manager_dataset
{

    static $mapoptions = [
        'fullscreen' => 'translate:geolocation_form_mapoptions_fullscreen',
        'gestureHandling' => 'translate:geolocation_form_mapoptions_zoomlock',
        'locateControl' => 'translate:geolocation_form_mapoptions_location',
    ];

    protected $mapDataset = [];
    protected $mapAttributes = [];
    protected $mapJS = [];

    # dataset-spezifisch

    /**
     * @param int         $id    Dataset ID
     * @param null|string $table Table name
     *
     * @return null|static
     */
    public static function get($id, $table = null)
    {
        $dataset = parent::get( $id, $table );
        if( $dataset ){
            $dataset->mapDataset = [];
            $dataset->mapAttributes = [];
            $dataset->mapJS = [];
        }
        return $dataset;
    }

    # liefert das Formular und stellt bei ...
    #   mapoptions die aktuell zulässigen Optionen bereit
    #   outfragment einen angepassten Hinweistext
    public function getForm( )
    {
        $yform = parent::getForm();
        $yform->objparams['form_class'] .= ' geolocation-yform';

        foreach( $yform->objparams['form_elements'] as $k=>&$fe ){
            if( 'validate' == $fe[0] ) continue;
            if( 'action' == $fe[0] ) continue;
            if( 'mapoptions' == $fe[1] ){
                $fe[3] = array_merge( ['default'=>\rex_i18n::rawMsg('geolocation_mapset_mapoptions_default')],self::$mapoptions);
                continue;
            }
            if( 'outfragment' == $fe[1] ){
                $fe[6] = \rex_i18n::msg( 'geolocation_mapset_outfragment_notice', OUT );
                continue;
            }
        }
        
        $yform->objparams['hide_top_warning_messages'] = true;
        $yform->objparams['hide_field_warning_messages'] = false;

        return $yform;
    }

    # Möglichkeit zum Einklinken um z.B. zu prüfen, ob der Mapset in REX_VALUEs vorkommt.
    # Der Mapset darf nicht gelöscht werden, wenn er der Default-Mapset ist
    public function delete() : bool
    {
        if( $this->id === \rex_config::get(ADDON,'default_map',0) ) {
            return false;
        }

        $delete = \rex_extension::registerPoint(new \rex_extension_point(
                'GEOLOCATION_MAPSET_DELETE',
                true,
                ['id'=>$this->id,'mapset'=>$this]
            ));

        return $delete && parent::delete();
    }

    # Formularbezogen

    public function executeForm(\rex_yform $yform, callable $afterFieldsExecuted = null)
    {

        \rex_extension::register('YFORM_DATA_ADD', function( \rex_extension_point $ep ){
            // nur abarbeiten wenn es um diese Instanz geht
            if( $this !== $ep->getParam('data') ) return;
            $objparams = &$ep->getSubject()->objparams;

            // Bug im Ablauf der EPs: YFORM_DATA_ADD wird in Version 3.3.1 vor
            // YFORM_DATA_ADDED noch mal ausgeführt und dadurch werden die Eingaben wieder mit
            // den Vorgaben überschrieben. Autsch!
            // Lösung: Wenn im REQUEST Daten zum Formular liegen => Abbruch.
            if( isset($_REQUEST['FORM'][$objparams['form_name']]) ) return;

            $value = explode('|', trim(\rex_config::get(ADDON,'map_components'),'|'));
            $objparams['data'] = [
                'outfragment' => \rex_config::get(ADDON,'map_outfragment',OUT),
                'mapoptions' => 'default',
            ];
        });
        $yform->objparams['form_ytemplate'] = 'geolocation,' . $yform->objparams['form_ytemplate'];
        \rex_yform::addTemplatePath( \rex_path::addon(ADDON,'ytemplates') );
        return parent::executeForm($yform,$afterFieldsExecuted);
    }

    # AJAX-Abrufe

    static public function sendMapset( $mapset = 0 )
    {
        // Same Origin
        tools::isAllowed();

        // check mapset, abort if invalid
        $mapset = self::get( $mapset );
        if( !$mapset ) tools::sendNotFound();

        // get layers ond overlays in scope and send as JSON
        \rex_response::cleanOutputBuffers();
        \rex_response::sendJson( $mapset->getLayerset() );
    }

    # Support

    # Liefert aufbauend auf dem aktuellen Datensatz ein Array mit erweiterten Kartenkonfigurations-
    # daten z.B. für <rex-map mapset=...>
    public function getLayerset( ) : array
    {
        // get layers ond overlays in scope
        $locale = \rex_clang::getCurrent()->getCode();
        $result = [];
        layer::getLayerConfigSet( explode(',',$this->layer), $locale, $result );
        layer::getLayerConfigSet( explode(',',$this->overlay), $locale, $result );
        return $result;
    }

    # Liefert aufbauend auf dem aktuellen Datensatz ein Array mit Parametern zum Kartenverhalten
    # z.B. für <rex-map map=...>
    public function getMapOptions( bool $full = true ) : array
    {
        $value = [];

        // MapOptions im Datensatz;
        $options = explode(',',$this->getValue('mapoptions'));
        $defaultRequired = false !== array_search('default',$options);

        // für:
        // - Sonderfall Abgleich mit allgemeiner Config; nur wenn Unterschiede
        // - default angefordert
        // ... die allgemeinen Werte laden
        if( $defaultRequired || !$full ) {
            $config = explode('|', trim(\rex_config::get(ADDON,'map_components'),'|'));
            if( $defaultRequired ) {
                return $config;
            }
            sort($options);
            sort($config);
            $full = $config != $options;
        }

        // Unterschiede? Wenn nein Ende
        if( $full ) {
            foreach( self::$mapoptions as $k=>$v ){
                $value[$k] = false !== array_search($k,$options);
            }
        }

        return $value;
    }

    # Liefert das für Kartenanzeige vorgesehene Fragment und falls leer den Default/Fallback
    public function getOutFragment () : string
    {
        return $this->getValue('outfragment') ?: self::getDefaultOutFragment();
    }

    # Default/Fallback-Fragment für Kartenanzeige
    static public function getDefaultOutFragment()
    {
        return \rex_config::get(ADDON,'map_outfragment',OUT);
    }

    # Statt geolocation_mapset::get um eine Instanz für Datensatz $id zu laden mit Fallback auf
    # den Datensatz des Default-Kartensatzes
    public static function take( $id = null ) : mapset
    {
        try {
            $map = mapset::get($id);
            if( null === $map ) throw new \Exception();
        } catch (\Exception $e) {
            $map = mapset::get(\rex_config::get(ADDON,'default_map'));
            if( null === $map ) throw new Exception('default_map not found',1);
        }
        return $map;
    }

    # Einsammeln von HTML-Attributen für die Karte
    # kaskadierbar
    public function attributes( $name = null, $data = null ) : mapset
    {
        if( is_array($name) ) {
            $this->mapAttributes = array_merge( $this->mapAttributes, $name );
        } elseif( is_string($name) ) {
            $this->mapAttributes[$name] = $data;
        }
        return $this;
    }

    # Einsammeln von Datenkomponenten der Karte (dataset); siehe geolocation.js und die Tools
    # $mapset->dataset('toolname',«tooldaten»)
    # kaskadierbar
    public function dataset( $name = null, $data = null ) : mapset
    {
        if( is_array($name) ) {
            $this->mapDataset = array_merge( $this->mapDataset, $name );
        } elseif( is_string($name) ) {
            $this->mapDataset[$name] = $data;
        }
        return $this;
    }

    public function onCreate( string $code ) : mapset
    {
        $code = trim($code);
        if( $code ) {
            $this->mapJS['create'] = $code;
        }
        return $this;
    }

    # baut eine Kartenausgabe (HTML) auf, indem Kartensatz-Konfiguration (mapset), die
    # Kartenoptionen (map) und die Karteninhalte(dataset) durch das Fragment geschickt werden.
    public function parse( string $file = '' ) : string
    {
        $fragment = new \rex_fragment();
        $fragment->setVar( 'mapset', $this->getLayerset(), false );
        $fragment->setVar( 'dataset', $this->mapDataset, false );
        if( isset($this->mapAttributes['class']) ){
            $fragment->setVar( 'class', $this->mapAttributes['class'], false );
            unset( $this->mapAttributes['class'] );
        }
        if( $this->mapAttributes ) {
            $fragment->setVar( 'attributes', $this->mapAttributes ?: [], false );
        }
        if( $this->mapJS ) {
            $fragment->setVar( 'events', $this->mapJS ?: [], false );
        }
        $fragment->setVar( 'map', $this->getMapOptions(false), false );
        return $fragment->parse( $file ?: $this->getOutFragment() );
    }

}
