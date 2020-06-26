<?php
/*
    yform-dataset to enhance rex_geolocation_tiles:

    - dataset-spezifisch

        getForm:    baut einige Felder im Formular um (aktuelle Systemeinstellungen vorbelegen)
        delete:     Löschen nur wenn nicht in Benutzung bei rex_geolocation_maps
                    Cache ebenfalls löschen
        save:       Cache löschen wenn Einstellungen geändert wurden.

    - Formularbezogen

        verifyUrl             Callback für customvalidator
        verifyUrlSubdomain    Callback für customvalidator
        verifyLang            Callback für customvalidator

    - Listenbezogen

        listAddCacheButton    Button "Cache löschen" für die Datentabelle
        listSort              Initiale Sortiertung die Liste nach zwei Kriterien

    - AJAX-Abrufe

        sendTile              Kachel-Abruf vom Client aus dem Cache oder vom Tile-Server beantworten

    - Support

        getLabel              Extrahiert aus dem lang-Feld die passende Sprachvariante der Kartentitels
        getLayerConfig        Stellt die Daten zur Konfiguration des Leaflet-Layers bereit
        getLayerConfigSet     Stellt für mehrere Id die "getLayerConfig" zusammen

*/


class geolocation_layer extends \rex_yform_manager_dataset
{

    const FILE_PATTERN = '{x}-{y}-{z}.{suffix}';    // file-name-pattern

    # dataset-spezifisch

    public function getForm( )
    {
        $yform = parent::getForm();

        foreach( $yform->objparams['form_elements'] as $k=>&$fe ){
            if( $fe[0] == 'action' ) continue;
            if( $fe[0] == 'validate' ) {
                if( 'intfromto' == $fe[1] ){
                    if( 'ttl' == $fe[2] ){
                        $fe[3] = GEOLOCATION_TTL_MIN;
                        $fe[4] = GEOLOCATION_TTL_MAX;
                        continue;
                    }
                    if( 'cfmax' == $fe[2] ){
                        $fe[3] = GEOLOCATION_CFM_MIN;
                        $fe[4] = GEOLOCATION_CFM_MAX;
                        continue;
                    }
                }
                continue;
            }

            if( 'lang' == $fe[1] ){
                // Auswahlfähige Sprachcodes ermitteln
                $fe[3] = 'choice|lang|Sprache|{'.implode(',',geolocation_tools::getLocales()).'}|,text|label|Bezeichnung|';
                continue;
            }
            if( 'ttl' == $fe[1] && empty($fe[3]) ){
                $fe[3] = rex_config::get( 'geolocation', 'ttl', GEOLOCATION_TTL_DEF );
                continue;
            }
            if( 'cfmax' == $fe[1] && empty($fe[3]) ){
                $fe[3] = rex_config::get( 'geolocation', 'maxfiles', GEOLOCATION_CFM_DEF );
            }
        }
        return $yform;
    }

    public function delete()
    {
        $sql = rex_sql::factory();
        $qry = 'SELECT `id`, `title` FROM `'.geolocation_mapset::table()->getTableName().'` WHERE FIND_IN_SET(:id,`layer`)';
        $data = $sql->getArray( $qry, [':id'=>$this->id], PDO::FETCH_KEY_PAIR );
        if( $data ) {
            $params = [
                'page'=>'geolocation/maps',
                'rex_yform_manager_popup'=>'0',
                'func'=>'edit',
            ];
            foreach( $data as $k=>&$v ){
                $params['data_id'] = $k;
                $v = '<li><a href="'.rex_url::backendController($params).'" target="_blank">'.$v.'</li>';
            }
            $result = rex_i18n::msg( 'geolocation_tiles_in_use', $this->name ) .'<ul>'.implode('',$data).'</ul>';

            rex_extension::register('YFORM_DATA_LIST', function( $ep ) {
                // nur abarbeiten wenn es um diese Instanz geht
                if( $ep->getParam('data') !== $this ) return;
                echo $ep->getParam('msg');
            }, 0, ['msg'=>rex_view::error( $result ),'data'=>$this] );

            return false;
        }

        $result = parent::delete();
        if( $result ){
            geolocation_proxy::clearLayerCache( $this->id);
        }
        return $result;
    }

    public function save()
    {
        $result = parent::save();
        if( $result ){
            geolocation_proxy::clearLayerCache( $this->id);
        }
        return $result;
    }

    # Formularbezogen

    # Wenn die URL einen Platzhalter für Subdomänen aufweist ({s}) muss auch das Feld subdomain
    # ausgefüllt werden.
    static public function verifyUrlSubdomain ( $fields,$values,$return,$self,$elements){
        if( strpos( $values['url'],'{s}') !== false ){
            $return = empty(trim($values['subdomain']));
        }
        return $return;
    }

    # die URL-Validierung scheitert an den Platzhaltern {x}. Die also erst entfernen
    static public function verifyUrl ( $field,$value,$return,$self,$elements){
        $url = str_replace( ['{','}'], '', $value );
        $xsRegEx_url = '/^(?:http[s]?:\/\/)[a-zA-Z0-9][a-zA-Z0-9._-]*\.(?:[a-zA-Z0-9][a-zA-Z0-9._-]*\.)*[a-zA-Z]{2,20}(?:\/[^\\/\:\*\?\"<>\|]*)*(?:\/[a-zA-Z0-9_%,\.\=\?\-#&]*)*$' . '/';
        return preg_match($xsRegEx_url, $url) == 0;
    }

    # Theoretisch können Sprachen mehrfach belegt werden. Hier kontrollieren, dass es nicht passiert
    static public function verifyLang ( $field,$value,$return,$self,$elements){
        if( !empty($value) ){
            $value = json_decode( $value, true );
            return count( $value ) == 0 || count( array_unique( array_column($value,'0') ) ) != count( $value );
        }
        return true;
    }

    # Listenbezogen

    # Baut den Link/Button für "cache löschen" in die Listenansicht ein.
    static public function listAddCacheButton( \rex_list $list, $table_name ){
        if( self::class != geolocation_layer::getModelClass( $table_name ) ) return;
        $list->addColumn('clearCache', '<i class="rex-icon rex-icon-delete"></i> ' . rex_i18n::msg('geolocation_clear_cache'), -1, ['', '<td class="rex-table-action">###VALUE###</td>']);
        $list->setColumnParams('clearCache', ['layer_id' => '###id###', 'func' => 'clear_cache']);
        $list->addLinkAttribute('clearCache', 'data-confirm', rex_i18n::msg('geolocation_clear_cache_confirm','###name###'));
    }

    # sorgt initial für die Sortierung nach 'layertyp,name'
    static public function listSort( $query, $table_name ){
        if( self::class != geolocation_layer::getModelClass( $table_name ) ) return;
        if( rex_request('sort','string',null) ) return;
        $pos = strrpos( $query,'ORDER BY');
        if( $pos ) $query = substr( $query, 0, $pos);
        $query .= 'ORDER BY `layertype`,`name` ASC';
        return $query;
    }

    # AJAX-Abrufe

    # schickt die Kachel/Tile an den Client.
    # nimmt alle Daten (außer LayerID, die hat schon boot.php geholt) aus dem Request.
    # Wenn die Datei existiert, wird sie aus dem Cache geschickt, sonst
    # erst vom Tile-Server geholt, im Cache gespeichert und dann an den Client gesendet
    static public function sendTile( $layerId = null )
    {
        $proxy = new geolocation_proxy();

        $layer = self::get( $layerId );
        if( !$layer ) $proxy->sendNotFound();

        // collect tile-URL-paramters and prepare replace
        $fileNameElements = [];
        $subdomain = $layer->subdomain;
        if( $subdomain ) {
            $subdomain = substr($subdomain, mt_rand(0, strlen($subdomain)-1), 1);
            $fileNameElements['{s}'] = $subdomain;
        }
        $column = rex_request('x', 'integer',null);
        if( null !== $column ) {
            $fileNameElements['{x}'] = $column;
        }
        $row = rex_request('y', 'integer',null);
        if( null !== $row ) {
            $fileNameElements['{y}'] = $row;
        }
        $zoom = rex_request('z', 'integer',null);
        if( null !== $zoom ) {
            $fileNameElements['{z}'] = $zoom;
        }

        // prepare targetCacheDir-Name
        $cacheDir = rex_path::addonCache( GEOLOCATION_ADDON, $layer->id.'/' );
        $cacheFileName = null;
        $contentType = null;
        $ttl = rex_config::get( GEOLOCATION_ADDON, 'ttl',GEOLOCATION_TTL_DEF ) * 60;

        // check for a matching tile-file in the cache-dir
        $fileNameElements['{suffix}'] = '*';
        $fileName = str_replace( array_keys($fileNameElements), $fileNameElements, self::FILE_PATTERN );
        $cacheFileName = $proxy->findCachedFile( $cacheDir.$fileName, $ttl );

        // Tile-File exists; send to the requestor
        if( null !== $cacheFileName ) {
            $contentType = 'image/' . pathinfo ( $cacheFileName, PATHINFO_EXTENSION );
            $proxy->sendCacheFile( $cacheFileName, $contentType, $ttl );
        };

        // no cached file found; retrieve from tile-server and store in cache-dir
        // Prepare the tile-URL and fetch the tile
        $url = str_replace( array_keys($fileNameElements), $fileNameElements, $layer->url );

        $ch = curl_init( $url );
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setOpt($ch, CURLOPT_RETURNTRANSFER, true);
        $content = curl_exec($ch);
        $header_data = curl_getinfo($ch);
        $contentType = $header_data['content_type'];
        $returnCode = $header_data['http_code'];
        curl_close($ch);

        // no reply at all, abort completely
        if( null === $returnCode ) $proxy->sendInternalError();

        // forward the error, simulation of a direct connection between client and tile-server
        if( 200 != $returnCode ) {
            rex_response::setStatus( $returnCode );
            rex_response::sendContent( $content, $contentType );
            exit();
        }

        // prepare cache-filename according to the content_type
        // and write the content into the cache-file
        $fileNameElements['{suffix}'] = substr( $contentType, strrpos($contentType,'/')+1 );
        $cacheFile = str_replace( array_keys($fileNameElements), $fileNameElements, self::FILE_PATTERN );
        $cacheFileName = $cacheDir . $cacheFile;
        rex_file::delete( $cacheFileName );
        rex_file::put( $cacheFileName, $content );

        // send the cached tile-file to the client
        $proxy->sendCacheFile( $cacheFileName, $contentType, $ttl );
    }

    # Support

    public function getLabel( string $locale = '' ){
        if( !$locale ) $locale = geolocation_tools::getLocale();
        $lang = rex_var::toArray( $this->lang );
        $lang = array_column( $lang,1,0 );
        return $lang[$locale] ?: $lang[array_key_first($lang)] ?: $this->name;
    }

    # wird für die Konfiguration des Layers bei Leaflet eingesetzt
    public function getLayerConfig( $locale = '' ){
        return [
            'tile' => $this->id,
            'label' => $this->getLabel( $locale ),
            'type' => $this->layertype,
            'attribution' => $this->attribution,
        ];
    }

    # stellt die Reihenfolge sicher; ist bei getRelatedCollection nicht der Fall
    static public function getLayerConfigSet( $layerIds, $locale = '', &$set=[] ){
        foreach( $layerIds as $layer ){
            if( $layer && ( $layer=geolocation_layer::get( $layer ) ) ){
                $set[] = $layer->getLayerConfig( $locale );
            }
        }
        return $set;
    }

}
