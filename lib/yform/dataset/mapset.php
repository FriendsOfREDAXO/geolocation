<?php namespace Geolocation;
/**
 * Geolocation|layer ist eine erweiterte yform-dataset-Klasse für Kartensätze
 *
 * @package geolocation
 */

/*

    - dataset-spezifisch

        get             Modifiziert um anschließend Initialisierungen durchzuführen
        getForm         baut einige Felder im Formular aus aktuelle Daten um
        delete          Löschen nur wenn nicht in Benutzung z.B. in Slices/Modulen etc.
                        Abfrage durch EP GEOLOCATION_MAPSET_DELETE, der entsprechend belegt werden
                        muss

    - Formularbezogen

        executeForm     stellt aktuelle Konfig-Daten als Vorbelegung in ein Add-Formular

    - Listenbezogen

        YFORM_DATA_LIST_ACTION_BUTTONS  Button "Cache löschen" in die Datentabelle
                                        Action-Button "delete" entfernen beim Default-Mapset

    - AJAX-Abrufe

        sendMapset      Kartensatz-Abruf vom Client beantworten

    - Support

        getValue        Eigene virtuelle Datensatzfelder abrufen
        getDefaultId    Default-Mapset-ID abrufen
        getLayerset     Array z.B. für <rex-map mapset=...> bereitstellen (Kartensatzparameter)
        getMapOptions   Array z.B. für <rex-map map=...> bereitstellen (Kartenoptionen)
        getOutFragment  das für Kartendarstellung vorgesehene Fragment (outfragment) mit Fallback
                        falls outfragment leer ist
        getDefaultOutFragment   Default-Ausgabefragment aus Config und Fallback
        take            Alternative zu get, aber mit Fallback auf die Default-Karte
        attributes      sammelt die sonstigen HTML-Attribute ein
        dataset         sammelt die Karteninhalte ein (siehe Geolocation.js -> Tools)
        parse           erzeugt Karten-HTML gem. vorgegebenem Fragment

*/

class InvalidMapsetParameter extends InvalidParameter {}


class mapset extends \rex_yform_manager_dataset
{

    static $mapoptions = [
        'fullscreen' => 'translate:geolocation_form_mapoptions_fullscreen',
        'gestureHandling' => 'translate:geolocation_form_mapoptions_zoomlock',
        'locateControl' => 'translate:geolocation_form_mapoptions_location',
    ];

    protected $mapDataset = [];
    protected $mapAttributes = [];

    # dataset-spezifisch

    /**
     * Modifiziertes GET um sofort Initialisierungen durchzuführen
     *
     * @param int           ID des gesuchten Mapset
     * @param string|null   Wird ignoriert. Siehe Anmerkung im Code
     *
     * @return self|null    also Instanz von Geolocation\mapset oder NULL falls ID unbekannt
     */
    public static function get(int $id, ?string $table = null): ?\rex_yform_manager_dataset # ?self erst ab PHP 7.4
    {
        // Es wird immer "diese" Tabelle benutzt, daher $table ignorieren und null nehmen
        $dataset = parent::get( $id, null );
        if( $dataset ){
            $dataset->mapDataset = [];
            $dataset->mapAttributes = [];
        }
        return $dataset;
    }

    /**
     * baut einige Felder im Formular aus aktuelle Daten um
     *
     * liefert das Formular und stellt bei ...
     *    - mapoptions die aktuell zulässigen Optionen bereit
     *    - outfragment einen angepassten Hinweistext
     *    - passt die Ausgabe der Fehlermeldungen an (beim Feld statt über dem Formular)
     *
     * @return \rex_yform   Das Formular-Gerüst
     */
    public function getForm( ) : \rex_yform
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
                if( str_starts_with($fe[6],'translate:') ) {
                    $fe[6] = substr( $fe[6],10 );
                }
                $fe[6] = \rex_i18n::rawMsg( $fe[6],OUT );
                continue;
            }
        }

        $yform->objparams['hide_top_warning_messages'] = true;
        $yform->objparams['hide_field_warning_messages'] = false;

        return $yform;
    }

    /**
     * Löschen nur wenn nicht in Benutzung z.B. in Slices/Modulen etc.
     *
     * Slices/Module:
     * Dazu erfolgt eine Abfrage mittels EP GEOLOCATION_MAPSET_DELETE, der entsprechend belegt
     * werden muss. Das gibt die Möglichkeit um z.B. zu prüfen, ob der Mapset in REX_VALUEs vorkommt.
     * Cache ebenfalls löschen
     *
     * Der Mapset darf auch nicht gelöscht werden, wenn er der Default-Mapset ist
     *
     * @return bool   TRUE für "Löschen erfolgreich"
     */
    public function delete() : bool
    {
        if( $this->id === self::getDefaultId() ) {
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

    /**
     * Erweiterte Funktionalität bei der Ausführung des Formulars
     *
     *    - stellt aktuelle Konfig-Daten als Vorbelegung in ein Add-Formular
     *    - Für choice.check wird ein modifiziertes Fragment (nur hier) aktiviert.
     *
     * @param \rex_yform        Das aktuelle YForm-Formular-Objekt
     * @param callable          Callback (Details müssten in der YForm-Doku zu finden sein)
     *
     * @return string           Formular-HTML
     */
    public function executeForm(\rex_yform $yform, callable $afterFieldsExecuted = null) : string
    {

        // setzt bei leeren Formularen (add) ein paar Default-Werte
        \rex_extension::register('YFORM_DATA_ADD', function( \rex_extension_point $ep ){
            // nur abarbeiten wenn es um diese Instanz geht
            if( $this !== $ep->getParam('data') ) return;
            $objparams = &$ep->getSubject()->objparams;

            // Bug im Ablauf der EPs: YFORM_DATA_ADD wird nach dem Absenden des neuen Formulars
            // noch mal vor dem Speichern ausgeführt und dadurch werden die Eingaben wieder mit
            // den Vorgaben überschrieben. Autsch!
            // Lösung: Wenn im REQUEST Daten zum Formular liegen => Abbruch, nix tun.
            if( isset($_REQUEST['FORM'][$objparams['form_name']]) ) return;

            $objparams['data'] = [
                'outfragment' => \rex_config::get(ADDON,'map_outfragment',OUT),
                'mapoptions' => 'default',
            ];
        });

        $yform->objparams['form_ytemplate'] = 'geolocation,' . $yform->objparams['form_ytemplate'];
        \rex_yform::addTemplatePath( \rex_path::addon(ADDON,'ytemplates') );
        return parent::executeForm($yform,$afterFieldsExecuted);
    }

    # Listenbezogen

    /**
     * Button "Cache löschen" für die Datentabelle
     *
     * Baut den Link/Button für "cache löschen" in die Listenansicht ein.
     * nur für Admins und User mit Permission "geolocation[clearcache]"
     *
     * @param \rex_extension_point      EP-Parameter
     *
     * @return array|null               Array der Actions oder null (=keine Änderung)
     */
    static public function YFORM_DATA_LIST_ACTION_BUTTONS( \rex_extension_point $ep )
    {
        // nur wenn diese Tabelle im Scope ist
        $table_name = $ep->getParam('table')->getTableName();
        if( self::class != self::getModelClass( $table_name ) ) return;

        // Button-Code abspeichern für YFORM_DATA_LIST
        $action_delete = $ep->getSubject()['delete'] ?? null;
        if( $action_delete ) {
            \rex_extension::register(
                'YFORM_DATA_LIST',
                static function (\rex_extension_point $ep) {
                    // nur für diese Tabelle
                    if( $ep->getParam('table_name') !== $ep->getParam('table')->getTableName() ) return;
                    // Daten zusammensuchen
                    $list = $ep->getSubject();
                    $default = self::getDefaultId();
                    $toDelete = '<li>'.$ep->getParam('action_delete').'</li>';
                    // Spalte "Functions": delete-Action entfernen wenn Default-Mapset
                    $list->setColumnFormat(
                        \rex_i18n::msg('yform_function').' ',
                        'custom',
                        function( $params ) use( $toDelete,$default){
                            return $params['list']->getValue('id') === $default
                                ? str_replace($toDelete,'',$params['value'])
                                : $params['value'];
                        }
                    );
                },
                \rex_extension::NORMAL,
                ['action_delete'=>$action_delete, 'table_name'=>$table_name]);
        }

        if( ($user = \rex::getUser()) && $user->hasPerm('geolocation[clearcache]') ){
            $link_vars = $ep->getParam('link_vars') + [
                'mapset_id' => '___id___',
                'rex-api-call' => 'geolocation_clearcache',
            ];
            $href = \rex_url::backendController( $link_vars, false );
            $confirm = \rex_i18n::msg('geolocation_clear_cache_confirm','___name___ [id=___id___]');
            $label = '<i class="rex-icon rex-icon-delete"></i> ' . \rex_i18n::msg('geolocation_clear_cache');
            $action = '<a onclick="return confirm(\''.$confirm.'\')" href="'.$href.'">'.$label.'</a>';
            return $ep->getSubject() + ['geolocationClearCache' => $action];
        }
    }

    # AJAX-Abrufe

    /**
     * Layerset-Abruf vom Client
     *
     * schickt die Konfigurationsdaten für Kartenlayer als JSON-String.
     * Wenn die Kartensatz-ID nicht existiert, stirbt die Methode mit einem HTTP-Fehlercode + Exit()
     *
     * @param int|null       Kartensatz-ID, zu der die Karten/Layer-Definition abgerufen wird
     */
    static public function sendMapset( ?int $mapset = null )
    {
        // Same Origin
        tools::isAllowed();

        // Abbruch bei unbekanntem Mapset
        $mapset = self::get( $mapset );
        if( !$mapset ) tools::sendNotFound();

        // get layers ond overlays in scope and send as JSON
        \rex_response::cleanOutputBuffers();
        \rex_response::sendJson( $mapset->getLayerset() );
    }

    # Support

    /**
     * liefert in der virtuellen Variablen 'layerset' die Nummern aller Layer (basis und overlay)
     * ansonsten normales getValue
     *
     * @param string    Name der Variablen
     * @return mixed
     */
    public function getValue(string $key)
    {
        if( 'layerset' === $key ) {
            $layer = explode(',',$this->layer.','.$this->overlay);
            $layer = array_filter( $layer, 'strlen' );
            return array_unique( $layer );
        }
        return parent::getValue($key);
    }

    /**
     * liefert die ID des Default-Mapset wie in den Einstellungen festgelegt
     *
     * @return int
     */
    public static function getDefaultId()
    {
        return (int)\rex_config::get(ADDON,'default_map');
    }


    /**
     * Array z.B. für <rex-map mapset=...> bereitstellen (Kartensatzparameter)
     *
     * Liefert aufbauend auf dem aktuellen Datensatz ein Array mit erweiterten Kartenkonfigurations-
     * daten z.B. für <rex-map mapset=...>
     *
     * @return array      Array mit Karteninformationen zu den Karten in diesem Mapset
     */
    public function getLayerset( ) : array
    {
        // get layers ond overlays in scope
        $locale = \rex_clang::getCurrent()->getCode();
        return layer::getLayerConfigSet( $this->layerset, $locale );
    }

    /**
     * Array z.B. für <rex-map map=...> bereitstellen (Kartensatzoptionen)
     *
     * Liefert aufbauend auf dem aktuellen Datensatz ein Array mit Parametern zum Kartenverhalten
     * z.B. für <rex-map map=...>
     *
     * Sonderfall "default": dann wird nur die Option "default=true" zurückgemeldet. I.V.m. $full
     * umfasst die Rückgabe alle im "default" aktivierten enthaltenen Einzeloptionen
     *
     * @param bool        wenn TRUE die komlette liste, sonst nur die aktivierten Kartenoptionen
     *
     * @return array      Array mit Kartensatzoptionen
     */
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

    /**
     * Liefert das für die Kartenanzeige dieses Kartensatzes vorgesehene Fragment
     *
     * Falls kein Fragment angegeben ist, wird als Fallback das Default-Fragment zurückgemeldet
     *
     * Achtung: REX-Fragment (addon/fragments/xyz) nicht yfragment!
     *
     * @return string      Name der Fragment-Datei (xyz.php)
     */
    public function getOutFragment () : string
    {
        return $this->getValue('outfragment') ?: self::getDefaultOutFragment();
    }

    /**
     * Liefert das Default-Fragment für die Kartenanzeige
     *
     * Falls kein gesondertes Default-Fragment angegeben ist, wird als Fallback das voreingestellte
     * Basis-Fragment (Geolocation\OUT) zurückgemeldet
     *
     * Achtung: REX-Fragment (addon/fragments/xyz) nicht yfragment!
     *
     * @return string      Name der Fragment-Datei (xyz.php)
     */
    static public function getDefaultOutFragment()
    {
        return \rex_config::get(ADDON,'map_outfragment',OUT);
    }

    /**
     * Alternative zu get(), aber mit Fallback auf die Default-Karte
     *
     * Man kann auch Geolocation\mapset::get($id) nehmen ... hier wird im Fehlerfall oder wenn
     * $id aus irgend einem Grunde nicht gefunden wird, ein Fallback auf den Default-Kartensatz
     * erfolgen.
     *
     * @param int|null     Nummer des Kartensatzes
     *
     * @return self        Die gefundene Kartensatz-Instanz
     */
    public static function take( ?int $id = null ) : self
    {
        try {
            if( !$id ) {
                throw new InvalidMapsetParameter( InvalidMapsetParameter::MAPSET_ID, [$id] ) ;
            }
            $map = mapset::get($id);
            if( null === $map ) {
                throw new InvalidMapsetParameter( InvalidMapsetParameter::MAPSET_ID, [$id] ) ;
            }
        } catch (\Exception $e) {
            $map = mapset::get(self::getDefaultId());
            if( null === $map ) {
                throw new InvalidMapsetParameter( InvalidMapsetParameter::MAPSET_DEF, [self::getDefaultId()] ) ;
            }
        }
        return $map;
    }

    /**
     * sammelt die sonstigen HTML-Attribute für den Aufbau des HTML-Karten-Tags ein
     *
     * Entweder wird als $name ein Array ['attribut_a'=>'inhalt',..] angegeben oder der Attribut-Name.
     * Im zweiten Fall muss $data den Attributwert als String enthalten oder leer sein (entspricht '')
     *
     * @param string|array  Name des Attributes oder ein Array mit Wertepaaren name=>wert
     * @param string|null   Inhalt/Wert des Attributes; ignoriert wenn $name ein Array ist.
     *
     * @return self         diese Kartensatz-Instanz
     */
    public function attributes( $name = null, ?string $data = null ) : self
    {
        if( is_array($name) ) {
            $this->mapAttributes = array_merge( $this->mapAttributes, $name );
        } elseif( is_string($name) && $name ) {
            $this->mapAttributes[$name] = $data ?: '';
        }
        return $this;
    }

    /**
     * sammelt die Karteninhalte ein (siehe Geolocation.js -> Tools)
     *
     * Darüber werden dem Kartensatz die Inhalte (Marker etc.) hinzugefügt. Sie landen als
     * DATASET-Attribut im Karten-HTML.
     * Siehe Doku zum Thema "Tools"
     *
     * Entweder wird als $name ein Array ['tool_a'=>'inhalt',..] angegeben oder der Tool-Name.
     * Im zweiten Fall muss $data die Tool-Daten leer sein (entspricht '')
     *
     * @param string|array  Name des Tools oder ein Array mit Wertepaaren name=>daten
     * @param mixed         Daten für das Tool. Ignoriert wenn $name ein Array ist
     *
     * @return self         diese Kartensatz-Instanz
     */
    public function dataset( $name = null, $data = null ) : self
    {
        if( is_array($name) ) {
            $this->mapDataset = array_merge( $this->mapDataset, $name );
        } elseif( is_string($name) && $name ) {
            $this->mapDataset[$name] = $data ?: null;
        }
        return $this;
    }

    /**
     * erzeugt Karten-HTML gem. vorgegebenem Fragment
     *
     * baut eine Kartenausgabe (HTML) auf, indem Kartensatz-Konfiguration (mapset), die
     * Kartenoptionen (map) und die Karteninhalte(dataset) durch das Fragment geschickt werden.
     * Siehe Doku zum Thema "Für Entwickler"
     *
     * @param string|null   Name des Fragmentes oder leer(null) für Kartensatz-Fragment
     *
     * @return string       HTMl für die Kartenausgabe
     */
    public function parse( ?string $file = null ) : string
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
        $fragment->setVar( 'map', $this->getMapOptions(false), false );

        return $fragment->parse( $file ?: $this->getOutFragment() );
    }

}
