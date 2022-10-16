<?php
/**
 * Geolocatin|layer ist eine erweiterte yform-dataset-Klasse für Kartenlayer.
 *
 * - dataset-spezifisch
 *
 *     getForm:    baut einige Felder im Formular um (aktuelle Systemeinstellungen vorbelegen)
 *     delete:     Löschen nur wenn nicht in Benutzung bei Geolocation\mapset
 *                 Cache ebenfalls löschen
 *     save:       Cache löschen wenn Einstellungen geändert wurden.
 *
 * - Formularbezogen
 *
 *     executeForm           stellt Cache-Löschen bei Änderungen sicher
 *     verifyUrl             Callback für customvalidator
 *     verifySubdomain       Callback für customvalidator
 *     verifyLang            Callback für customvalidator
 *
 * - Listenbezogen
 *
 *     YFORM_DATA_LIST_ACTION_BUTTONS  Button "Cache löschen" für die Datentabelle
 *     YFORM_DATA_LIST_QUERY           Initiale Sortiertung die Liste nach zwei Kriterien
 *
 * - AJAX-Abrufe
 *
 *     sendTile              Kachel-Abruf vom Client aus dem Cache oder vom Tile-Server beantworten
 *
 * - Support
 *
 *     getLabel              Extrahiert aus dem lang-Feld die passende Sprachvariante der Kartentitels
 *     getLayerConfig        Stellt die Daten zur Konfiguration des Leaflet-Layers bereit
 *     getLayerConfigSet     Stellt für mehrere Id die "getLayerConfig" zusammen
 *     isOnline              liefert true wenn der Layer online ist ($layer->online == 1 )
 */

namespace Geolocation;

use PDO;
use rex;
use rex_clang;
use rex_config;
use rex_csrf_token;
use rex_extension;
use rex_extension_point;
use rex_file;
use rex_i18n;
use rex_path;
use rex_request;
use rex_response;
use rex_sql;
use rex_url;
use rex_var;
use rex_view;
use rex_yform;
use rex_yform_manager_dataset;
use rex_yform_manager_query;
use rex_yform_validate_customfunction;
use rex_yform_value_abstract;

use function count;
use function is_bool;
use function strlen;

/**
 * Mittels parent::__get bereitgestellte Daten.
 *
 * aus der Datentabelle rex_geolocation_layer
 * @property string $name
 * @property string $url
 * @property string $subdomain
 * @property string $attribution
 * @property string $layertype
 * @property string $lang
 * @property int $ttl
 * @property int $cfmax
 * @property int $online
 */

class layer extends rex_yform_manager_dataset
{
    /**
     * Pattern für den Aufbau von Dateinamen für Kartenkacheln (Tiles) im Cache.
     *
     * @api
     */
    public const FILE_PATTERN = '{x}-{y}-{z}.{suffix}';

    // dataset-spezifisch

    /**
     * baut einige Felder im Formular um (z.B. aktuelle Systemeinstellungen vorbelegen).
     *
     * @return rex_yform   Das Formular-Gerüst
     */
    public function getForm(): rex_yform
    {
        $yform = parent::getForm();
        $yform->objparams['form_class'] .= ' geolocation-yform';

        foreach ($yform->objparams['form_elements'] as $k => &$fe) {
            if ('action' === $fe[0]) {
                continue;
            }
            if ('validate' === $fe[0]) {
                if ('intfromto' === $fe[1]) {
                    if ('ttl' === $fe[2]) {
                        $fe[3] = TTL_MIN;
                        $fe[4] = TTL_MAX;
                        continue;
                    }
                    if ('cfmax' === $fe[2]) {
                        $fe[3] = CFM_MIN;
                        $fe[4] = CFM_MAX;
                        continue;
                    }
                }
                continue;
            }

            if ('lang' === $fe[1]) {
                // Auswahlfähige Sprachcodes ermitteln
                $fe[3] = 'choice|lang|Sprache|{'.implode(',', Tools::getLocales()).'}|,text|label|Bezeichnung|';
                continue;
            }
            if ('ttl' === $fe[1] && '' === trim($fe[3])) {
                $fe[3] = rex_config::get(ADDON, 'cache_ttl', TTL_DEF);
                if (str_starts_with($fe[6], 'translate:')) {
                    $fe[6] = substr($fe[6], 10);
                }
                $fe[6] = rex_i18n::rawMsg($fe[6], TTL_MAX);
                continue;
            }
            if ('cfmax' === $fe[1] && '' === trim($fe[3])) {
                $fe[3] = rex_config::get(ADDON, 'cache_maxfiles', CFM_DEF);
            }
        }

        $yform->objparams['hide_top_warning_messages'] = true;
        $yform->objparams['hide_field_warning_messages'] = false;

        return $yform;
    }

    /**
     * Löschen nur wenn nicht in Benutzung bei Geolocation\mapset
     * Cache ebenfalls löschen.
     *
     * Wenn es noch Bezüge auf den Layer gibt, werden Links auf die Edit-Seite
     * via EP YFORM_DATA_LIST ausgegeben.
     *
     * @return bool   TRUE für "Löschen erfolgreich"
     */
    public function delete(): bool
    {
        $sql = rex_sql::factory();
        $table = mapset::table();
        $qry = 'SELECT `id`, `title` FROM `'.$table->getTableName().'` WHERE FIND_IN_SET(:id,`layer`)';
        /**
         * STAN: Possible SQL-injection in expression $table->getTableName().
         * False positive: der TableName kommt aus rex_yform_manager_dataset und ist m.E. safe.
         * @phpstan-ignore-next-line
         */
        $data = $sql->getArray($qry, [':id' => $this->getId()], PDO::FETCH_KEY_PAIR);
        /** @var array<int,string> $data */
        if (0 < count($data)) {
            $params = [
                'page' => 'geolocation/mapset',
                'rex_yform_manager_popup' => '1',
                'func' => 'edit',
            ];
            $params += rex_csrf_token::factory($table->getCSRFKey())->getUrlParams();

            /** @var string $v */
            foreach ($data as $k => &$v) {
                $params['data_id'] = $k;
                $v = '<li><a href="'.rex_url::backendController($params).'" target="_blank">'.$v.'</li>';
            }
            $result = rex_i18n::msg('geolocation_layer_in_use', $this->name) .'<ul>'.implode('', $data).'</ul>';

            rex_extension::register('YFORM_DATA_LIST', function ($ep) {
                // nur abarbeiten wenn es um diese Instanz geht
                if ($ep->getParam('data') !== $this) {
                    return;
                }
                echo $ep->getParam('msg');
            }, 0, ['msg' => rex_view::error($result), 'data' => $this]);

            return false;
        }

        $result = parent::delete();
        if ($result) {
            Cache::clearLayerCache($this->getId());
        }
        return $result;
    }

    /**
     * Layer-Cache löschen wenn Einstellungen geändert wurden.
     * zieht nicht bei den Formularen, leider.
     *
     * @return bool     TRUE für "Speichern erfolgreich"
     */
    public function save(): bool
    {
        $result = parent::save();
        if ($result) {
            Cache::clearLayerCache($this->getId());
        }
        return $result;
    }

    // Formularbezogen

    /**
     * Erweiterte Funktionalität bei der Ausführung des Formulars.
     *
     * Da das Formular sichert per db_action, nicht via dataset::save()!
     * Daher hier den Cache per EP löschen
     */
    public function executeForm(rex_yform $yform, callable $afterFieldsExecuted = null): string
    {
        rex_extension::register('YFORM_DATA_UPDATED', function (rex_extension_point $ep) {
            // nur abarbeiten wenn es um diese Instanz geht
            if ($this !== $ep->getParam('data')) {
                return;
            }
            // Cache löschen
            Cache::clearLayerCache((int) $ep->getParam('data_id'));
        });

        return parent::executeForm($yform, $afterFieldsExecuted);
    }

    /**
     * Callback für customvalidator: 'url', 'subdomain'.
     *
     * Wenn die URL einen Platzhalter für Subdomänen aufweist ({s}) muss auch das Feld subdomain
     * ausgefüllt werden. Der fehler wird an beiden Feldern gemeldet.
     *
     * Angabe von Subdomains ohne {s} in der Url ist egal
     *
     * Die Parameter sind so belegt:
     *  - Array mit den Feldnamen ('url', 'subdomain')
     *  - Array mit den aktuellen Werten für 'url' und 'subdomain'
     *  - Rückgabewert als Vorbelegung (sollte leer sein), ignorieren
     *  - Instanz der aktiven Validator-Klasse
     *  - Array mit den Instanzen der Felder ('url', 'subdomain')
     *
     * @param list<string> $fields
     * @param array<string,string> $values
     * @param string $return
     * @param rex_yform_validate_customfunction $self
     * @param array<string,rex_yform_value_abstract> $elements
     */
    public static function verifySubdomain($fields, $values, $return, $self, $elements): bool
    {
        return false !== strpos($values['url'], '{s}') && '' === trim($values['subdomain']);
    }

    /**
     * Callback für customvalidator: 'url'.
     *
     * die URL-Validierung per Regex stolpert an den Platzhaltern {x}. Die also erst entfernen
     * Die Parameter sind so belegt:
     *  - Feldname ('url')
     *  - der aktuelle Werte für 'url' (JSON-String)
     *  - Rückgabewert als Vorbelegung (sollte leer sein), ignorieren
     *  - Instanz der aktiven Validator-Klasse
     *  - Array mit einem Element: Instanz des Feldes 'url'
     *
     * @param string $field
     * @param string $value
     * @param string $return
     * @param rex_yform_validate_customfunction $self
     * @param array<string,rex_yform_value_abstract> $elements
     */
    public static function verifyUrl($field, $value, $return, $self, $elements): bool
    {
        $url = str_replace(['{', '}'], '', $value);
        $xsRegEx_url = '/^(?:http[s]?:\/\/)[a-zA-Z0-9][a-zA-Z0-9._-]*\.(?:[a-zA-Z0-9][a-zA-Z0-9._-]*\.)*[a-zA-Z]{2,20}(?:\/[^\\/\:\*\?\"<>\|]*)*(?:\/[a-zA-Z0-9_%,\.\=\?\-#&]*)*$' . '/';
        return 0 === preg_match($xsRegEx_url, $url);
    }

    /**
     * Callback für customvalidator: 'lang'.
     *
     * Theoretisch können Sprachen mehrfach belegt werden. Hier kontrollieren, dass es nicht passiert
     * Die Parameter sind so belegt:
     *  - Feldname ('lang')
     *  - der aktuelle Werte für 'lang' (JSON-String)
     *  - Rückgabewert als Vorbelegung (sollte leer sein), ignorieren
     *  - Instanz der aktiven Validator-Klasse
     *  - Array mit einem Element: Instanz des Feldes 'lang'
     *
     * @param string $field
     * @param string $value
     * @param string $return
     * @param rex_yform_validate_customfunction $self
     * @param array<string,rex_yform_value_abstract> $elements
     */
    public static function verifyLang($field, $value, $return, $self, $elements): bool
    {
        if ('' !== trim($value)) {
            $value = json_decode($value, true);
            return 0 === count($value) || count(array_unique(array_column($value, '0'))) !== count($value);
        }
        return true;
    }

    // Listenbezogen

    /**
     * Button "Cache löschen" für die Datentabelle.
     *
     * Baut den Link/Button für "cache löschen" in die Listenansicht ein.
     * nur für Admins und User mit Permission "geolocation[clearcache]"
     * Rückgabe ist das erweiterte Array aus getSubject
     *
     * @param rex_extension_point<array<string,string>> $ep
     * @return array<string,string>|void
     */
    public static function YFORM_DATA_LIST_ACTION_BUTTONS(rex_extension_point $ep)
    {
        // nur wenn diese Tabelle im Scope ist
        $table_name = $ep->getParam('table')->getTableName();
        if (self::class !== self::getModelClass($table_name)) {
            return;
        }

        $user = rex::getUser();
        if (null !== $user && $user->hasPerm('geolocation[clearcache]')) {
            $link_vars = $ep->getParam('link_vars') + [
                'layer_id' => '___id___',
                'rex-api-call' => 'geolocation_clearcache',
            ];
            $href = rex_url::backendController($link_vars, false);
            $confirm = rex_i18n::msg('geolocation_clear_cache_confirm', '___name___');
            $label = '<i class="rex-icon rex-icon-delete"></i> ' . rex_i18n::msg('geolocation_clear_cache');
            $action = '<a onclick="return confirm(\''.$confirm.'\')" href="'.$href.'">'.$label.'</a>';
            return $ep->getSubject() + ['geolocationClearCache' => $action];
        }
    }

    /**
     * Initiale Sortiertung die Datentabelle nach zwei Kriterien.
     *
     * sorgt initial für die Sortierung nach 'layertyp,name', sofern es keine
     * individuelle Sortierung gibt: das wird hier hilfsweise geprüft über
     * \rex_request('sort') als Indikator.
     *
     * @param rex_extension_point<rex_yform_manager_query<rex_yform_manager_dataset>> $ep
     * @return void
     */
    public static function YFORM_DATA_LIST_QUERY(rex_extension_point $ep)
    {
        // nichts tun wenn es schon einen Sort gibt
        if ('' === rex_request::request('sort', 'string', '')) {
            return;
        }

        // nur wenn diese Tabelle im Scope ist
        if (self::class !== self::getModelClass($ep->getSubject()->getTableName())) {
            return;
        }

        $ep->getSubject()
            ->resetOrderBy()
            ->orderBy('layertype')
            ->orderBy('name');
    }

    // AJAX-Abrufe

    /**
     * Kachel-Abruf vom Client aus dem Cache oder vom Tile-Server beantworten.
     *
     * schickt die Kachel/Tile an den Client.
     * nimmt alle Daten (außer LayerID, die hat schon boot.php geholt) aus dem Request.
     * Wenn die Datei existiert, wird sie aus dem Cache geschickt, sonst wird sie
     * erst vom Tile-Server geholt, im Cache gespeichert und dann an den Client gesendet
     * Es gibt keinen Rückgabewert. Die Methode löst den Versand der Grafik aus oder
     * stirbt mit einem HTTP-Returncode + Exit()
     *
     * @api
     * @return never
     */
    public static function sendTile(int $layerId): void
    {
        // Same Origin
        Tools::isAllowed();

        $layer = self::get($layerId);
        if (null === $layer || !$layer->isOnline()) {
            Tools::sendNotFound();
        }

        $cache = new Cache();

        // collect tile-URL-paramters and prepare replace
        $fileNameElements = [];
        $subdomain = $layer->subdomain;
        if ('' !== $subdomain) {
            $subdomain = substr($subdomain, random_int(0, strlen($subdomain) - 1), 1);
            $fileNameElements['{s}'] = $subdomain;
        }
        $column = rex_request('x', 'integer', null);
        if (null !== $column) {
            $fileNameElements['{x}'] = $column;
        }
        $row = rex_request('y', 'integer', null);
        if (null !== $row) {
            $fileNameElements['{y}'] = $row;
        }
        $zoom = rex_request('z', 'integer', null);
        if (null !== $zoom) {
            $fileNameElements['{z}'] = $zoom;
        }

        // prepare targetCacheDir-Name
        $cacheDir = rex_path::addonCache(ADDON, $layer->getId().'/');
        $cacheFileName = null;
        $contentType = null;
        $ttl = $layer->ttl * 60;

        // if cache then check for a matching tile-file in the cache-dir
        if (0 < $ttl) {
            $fileNameElements['{suffix}'] = '*';
            $fileName = str_replace(array_keys($fileNameElements), $fileNameElements, self::FILE_PATTERN);
            $cacheFileName = $cache->findCachedFile($cacheDir.$fileName, $ttl);

            // Tile-File exists; send to the requestor
            if (null !== $cacheFileName) {
                $contentType = 'image/' . pathinfo($cacheFileName, PATHINFO_EXTENSION);
                $cache->sendCacheFile($cacheFileName, $contentType, $ttl);
            }
        }

        // no cache or no cached file found; retrieve from tile-server and store in cache-dir
        // Prepare the tile-URL and fetch the tile
        $url = str_replace(array_keys($fileNameElements), $fileNameElements, $layer->url);

        $ch = curl_init($url);
        if (is_bool($ch)) {
            Tools::sendInternalError();
        }
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $content = (string) curl_exec($ch);
        $returnCode = (string) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $contentType = (string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        curl_close($ch);

        // no reply at all, abort completely
        if ('0' === $returnCode) {
            Tools::sendInternalError();
        }

        // forward the error, simulation of a direct connection between client and tile-server
        if ('200' !== $returnCode) {
            rex_response::setStatus($returnCode);
            rex_response::sendContent($content, $contentType);
            exit;
        }

        // if cache:
        // prepare cache-filename according to the content_type
        // and write the content into the cache-file
        if (0 < $ttl) {
            $fileNameElements['{suffix}'] = substr($contentType, ((int) strrpos($contentType, '/')) + 1);
            $cacheFile = str_replace(array_keys($fileNameElements), $fileNameElements, self::FILE_PATTERN);
            $cacheFileName = $cacheDir . $cacheFile;
            rex_file::put($cacheFileName, $content);
            $cache->sendCacheFile($cacheFileName, $contentType, $ttl);
        }

        // send the received tile to the client and exit
        Tools::sendTile($content, $contentType, time(), $ttl);
    }

    // Support

    /**
     * Extrahiert aus dem lang-Feld der Datenbank (be_table, JSON),
     * die passende Sprachvariante der Kartentitels.
     *
     * Zur via Parameter geünschten Variante (de, ... oder null = default) wird
     * aus dem DB-Feld "lang" der passende Kartentitel geholt bzw. $this->name als Fallback
     *
     * @api
     */
    public function getLabel(?string $clang): string
    {
        if (null === $clang || '' === trim($clang)) {
            $clang = rex_clang::getCurrent()->getCode();
        }
        $lang = rex_var::toArray($this->lang) ?? [];
        $lang = array_column($lang, 1, 0);
        if (isset($lang[$clang]) && $lang[$clang]) {
            return $lang[$clang];
        }
        $clang = array_key_first($lang);
        if (isset($lang[$clang]) && $lang[$clang]) {
            return $lang[$clang];
        }
        return $this->name;
    }

    /**
     * Stellt die Daten zur Konfiguration des Leaflet-Layers bereit.
     * Hauptsächlich zur Weitergabe an JS.
     *
     * Berücksichtigt für den Titel die Sprachauswahl via Parameter
     * (de, ... oder null = deault)
     *
     * @api
     * @return array{layer:int,label:string,type:string,attribution:string}
     */
    public function getLayerConfig(?string $clang): array
    {
        return [
            'layer' => $this->getId(),
            'label' => $this->getLabel($clang),
            'type' => $this->layertype,
            'attribution' => $this->attribution,
        ];
    }

    /**
     * Baut für mehrere Layer-Id die "getLayerConfig" zu einem array zusammen.
     * In $layerIds werden die IDs der Layer übergeben. Im Ergebnis sind nur Daten
     * existierender Layer enthalten, die auch online sind.
     *
     * Stellt Rückgabe in der Reihenfolge der Is sicher; das wäre mit
     * query->getRelatedCollection nicht der Fall
     *
     * @api
     * @param list<int>   $layerIds
     * STAN: das muss doch einfacher gehen als immer diesen Text (siehe getLayerConfig) abzuschreiben
     * @return list<array{layer:int,label:string,type:string,attribution:string}>
     */
    public static function getLayerConfigSet(array $layerIds, ?string $clang)
    {
        $set = [];
        foreach ($layerIds as $layerId) {
            $layer = self::get($layerId);
            if (null !== $layer && $layer->isOnline()) {
                $set[] = $layer->getLayerConfig($clang);
            }
        }
        return $set;
    }

    /**
     * liefert true wenn der Layer online ist ($layer->online == 1 ).
     *
     * @api
     */
    public function isOnline(): bool
    {
        return 1 === $this->online;
    }
}
