<?php

/**
 * Geolocation|Layer ist eine erweiterte yform-dataset-Klasse für Kartenlayer.
 *
 * - dataset-spezifisch
 *
 *     getForm:    baut einige Felder im Formular um (aktuelle Systemeinstellungen vorbelegen)
 *     delete:     Löschen nur wenn nicht in Benutzung bei Geolocation\Mapset
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
 *     epYformDataListActionButtons  Button "Cache löschen" für die Datentabelle
 *     epYformDataListQuery          Initiale Sortiertung die Liste nach zwei Kriterien
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

namespace FriendsOfRedaxo\Geolocation;

use PDO;
use rex;
use rex_addon;
use rex_clang;
use rex_config;
use rex_context;
use rex_csrf_token;
use rex_extension;
use rex_extension_point;
use rex_file;
use rex_i18n;
use rex_logger;
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
use rex_yform_manager_table;
use rex_yform_validate_customfunction;
use rex_yform_value_abstract;

use function count;
use function is_bool;
use function is_string;
use function sprintf;
use function strlen;

use const CURLINFO_CONTENT_TYPE;
use const CURLINFO_RESPONSE_CODE;
use const CURLOPT_FOLLOWLOCATION;
use const CURLOPT_HEADER;
use const CURLOPT_PROXY;
use const CURLOPT_RETURNTRANSFER;
use const E_WARNING;
use const PATHINFO_EXTENSION;

/**
 * Mittels parent::__get bereitgestellte Daten.
 *
 * aus der Datentabelle rex_geolocation_layer
 * @property string $name
 * @property string $url
 * @property string $retinaurl
 * @property string $subdomain
 * @property string $attribution
 * @property string $layertype
 * @property string $lang
 * @property int $ttl
 * @property int $cfmax
 * @property int $online
 */

class Layer extends rex_yform_manager_dataset
{
    /**
     * Pattern für den Aufbau von Dateinamen für Kartenkacheln (Tiles) im Cache.
     *
     * @api
     */
    public const FILE_PATTERN = '{x}-{y}-{z}-{r}.{suffix}';

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
                $fe[3] = 'choice|lang|Sprache|{' . implode(',', Tools::getLocales()) . '}|,text|label|Bezeichnung|';
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
     * Löschen nur wenn nicht in Benutzung bei Geolocation\Mapset
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
        $table = Mapset::table();
        $qry = 'SELECT `id`, `title` FROM `' . $table->getTableName() . '` WHERE FIND_IN_SET(:id,`layer`)';
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
                $v = '<li><a href="' . rex_url::backendController($params) . '" target="_blank">' . $v . '</li>';
            }
            $result = rex_i18n::msg('geolocation_layer_in_use', $this->name) . '<ul>' . implode('', $data) . '</ul>';

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
    public function executeForm(rex_yform $yform, ?callable $afterFieldsExecuted = null): string
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
     *  - Array mit den Feldnamen
     *  - Array mit den aktuellen Werten für 'url' und 'subdomain'
     *  - Rückgabewert als Vorbelegung (sollte leer sein), ignorieren
     *  - Instanz der aktiven Validator-Klasse
     *  - Array mit den Instanzen der Felder ('url', 'subdomain')
     *
     * @api
     * @param list<string> $fields
     * @param array<string,string> $values
     * @param string $return
     * @param rex_yform_validate_customfunction $self
     * @param array<string,rex_yform_value_abstract> $elements
     */
    public static function verifySubdomain($fields, $values, $return, $self, $elements): bool
    {
        $urlFieldName = $fields[0];
        $subdomainFieldName = $fields[1];

        $urlValue = trim($values[$urlFieldName]);
        $subdomainValue = trim($values[$subdomainFieldName]);

        return str_contains($urlValue, '{s}') && '' === $subdomainValue;
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
     * @api
     * @param string $field
     * @param string $value
     * @param string $return
     * @param rex_yform_validate_customfunction $self
     * @param array<string,rex_yform_value_abstract> $elements
     */
    public static function verifyUrl($field, $value, $return, $self, $elements): bool
    {
        $url = trim($value);
        if ('' === $url) {
            return false;
        }
        $url = str_replace(['{', '}'], '', $value);
        $xsRegEx_url = '/^(?:http[s]?:\/\/)[a-zA-Z0-9][a-zA-Z0-9._-]*\.(?:[a-zA-Z0-9][a-zA-Z0-9._-]*\.)*[a-zA-Z]{2,20}(?:\/[^\\/\:\*\?\"<>\|]*)*(?:\/[a-zA-Z0-9_%,\.\=\?\-#&@]*)*$/';
        return 0 === preg_match($xsRegEx_url, $url);
    }

    /**
     * Callback für customvalidator: 'lang'.
     *
     * Theoretisch können Sprachen mehrfach belegt werden. Hier kontrollieren, dass es nicht passiert
     * Die Parameter sind so belegt:
     *  - Feldname ('lang')
     *  - der aktuelle Werte für 'lang' (je nach YForm-Version JSON-String, array oder null)
     *  - Rückgabewert als Vorbelegung (sollte leer sein), ignorieren
     *  - Instanz der aktiven Validator-Klasse
     *  - Array mit einem Element: Instanz des Feldes 'lang'
     *
     * @api
     * @param string $field
     * @param string|array<array<string,string>>|null $value
     * @param string $return
     * @param rex_yform_validate_customfunction $self
     * @param array<string,rex_yform_value_abstract> $elements
     */
    public static function verifyLang($field, $value, $return, $self, $elements): bool
    {
        // wenn keine Sprachen angegeben sind kann auch null kommen.
        $value ??= '';

        /**
         * Kompatibilität zu YForm < 4.2.0
         * 4.2.0 lieber nicht benutzen!
         * TODO: rauswerfen wenn irgendwann mal die Mindestversion YFORM > 4.2. ist.
         */
        if (is_string($value)) {
            $value = trim($value);
            if ('' === $value) {
                return true;
            }
            $value = json_decode($value, true);
        }

        /**
         * ab YForm 4.2.1 sollte das hier funktionieren.
         */
        return 0 === count($value) || count(array_unique(array_column($value, '0'))) !== count($value);
    }

    // Listenbezogen

    /**
     * Button "Cache löschen" für die Datentabelle.
     *
     * Baut den Link/Button für "cache löschen" in die Listenansicht ein.
     * nur für Admins und User mit Permission "geolocation[clearcache]"
     * Rückgabe ist das erweiterte Array aus getSubject
     *
     * @api
     * @param rex_extension_point<array<string,string>> $ep
     * @return array<string,string>|void
     */
    public static function epYformDataListActionButtons(rex_extension_point $ep)
    {
        // nur wenn diese Tabelle im Scope ist
        /** @var rex_yform_manager_table $table just to male rexstan happy */
        $table = $ep->getParam('table');
        $table_name = $table->getTableName();
        if (self::class !== self::getModelClass($table_name)) {
            return;
        }

        $user = rex::getUser();
        if (null === $user || !$user->hasPerm('geolocation[clearcache]')) {
            return;
        }

        $link_vars = $ep->getParam('link_vars') + [
            'layer_id' => '___id___',
            'rex-api-call' => 'geolocation_clearcache',
        ];
        $href = rex_url::backendController($link_vars, false);
        $confirm = rex_i18n::msg('geolocation_clear_cache_confirm', '___name___');
        $label = '<i class="rex-icon rex-icon-delete"></i> ' . rex_i18n::msg('geolocation_clear_cache');

        $buttons = $ep->getSubject();

        /**
         * bis YForm 4.0.4 waren die Action-Buttons einfach HTML-Strings.
         * Post-4.0.4. sind es Arrays, die in einem List-Fragment verwertet werden.
         * Hier die beiden Fälle unterscheiden.
         * Note:
         * Stand 07.03.2023 gibt es nur das GH-Repo und keine neue Versionsnummer.
         * Daher auf das neue Fragment als Unterscheidungsmerkmal setzen.
         */
        if(is_file(rex_path::addon('yform', 'pages/manager.data_edit.php'))) {
            $buttons['geolocationClearCache'] = '<a onclick="return confirm(\'' . $confirm . '\')" href="' . $href . '">' . $label . '</a>';
        } else {
            $buttons['geolocationClearCache'] = [
                'url' => $href,
                'content' => $label,
                'attributes' => [
                    'onclick' => 'return confirm(\'' . $confirm . '\')',
                ],
            ];
        }
        $ep->setSubject($buttons);
    }

    /**
     * @deprecated 3.0.0 Aufrufe auf "Layer::epYformDataListActionButtons" geändert
     * @api
     * @param rex_extension_point<array<string,string>> $ep
     * @return array<string,string>|void
     */
    public static function YFORM_DATA_LIST_ACTION_BUTTONS(rex_extension_point $ep)
    {
        return self::epYformDataListActionButtons($ep);
    }

    /**
     * Initiale Sortiertung die Datentabelle nach zwei Kriterien.
     *
     * sorgt initial für die Sortierung nach 'layertyp,name', sofern es keine
     * individuelle Sortierung gibt: das wird hier hilfsweise geprüft über
     * \rex_request('sort') als Indikator.
     *
     * @api
     * @param rex_extension_point<rex_yform_manager_query<rex_yform_manager_dataset>> $ep
     * @return void|rex_yform_manager_query<rex_yform_manager_dataset>
     */
    public static function epYformDataListQuery(rex_extension_point $ep)
    {
        if ($ep->getSubject()->getTableName() !== self::table()->getTableName()) {
            return;
        }

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

    /**
     * @deprecated 3.0.0 Aufrufe auf "Layer::epYformDataListQuery" geändert
     * @api
     * @param rex_extension_point<rex_yform_manager_query<rex_yform_manager_dataset>> $ep
     * @return void|rex_yform_manager_query<rex_yform_manager_dataset>
     */
    public static function YFORM_DATA_LIST_QUERY(rex_extension_point $ep)
    {
        return self::epYformDataListQuery($ep);
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
        $retina = rex_request('r', 'string', null);
        if (null !== $retina) {
            $fileNameElements['{r}'] = $retina;
        }

        // prepare targetCacheDir-Name
        $cacheDir = rex_path::addonCache(ADDON, $layer->getId() . '/');
        $cacheFileName = null;
        $contentType = null;
        $ttl = $layer->ttl * 60;

        // if cache then check for a matching tile-file in the cache-dir
        if (0 < $ttl) {
            $fileNameElements['{suffix}'] = '*';
            $fileName = str_replace(array_keys($fileNameElements), $fileNameElements, self::FILE_PATTERN);
            $cacheFileName = $cache->findCachedFile($cacheDir . $fileName, $ttl);

            // Tile-File exists; send to the requestor
            if (null !== $cacheFileName) {
                $contentType = 'image/' . pathinfo($cacheFileName, PATHINFO_EXTENSION);
                $cache->sendCacheFile($cacheFileName, $contentType, $ttl);
            }
        }

        // no cache or no cached file found; retrieve from tile-server and store in cache-dir
        // Prepare the tile-URL and fetch the tile
        if ('' < $retina && '' < $layer->retinaurl) {
            $url = $layer->retinaurl;
        } else {
            $url = $layer->url;
        }
        $url = str_replace(array_keys($fileNameElements), $fileNameElements, $url);

        $ch = curl_init($url);
        if (is_bool($ch)) {
            Tools::sendInternalError();
        }
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        if (($proxy = rex_addon::get('geolocation')->getConfig('socket_proxy')) !== '') {
            curl_setopt($ch, CURLOPT_PROXY, $proxy);
        }
        $content = (string) curl_exec($ch);
        $returnCode = (string) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $contentType = (string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        curl_close($ch);

        // no reply at all, abort completely
        if ('0' === $returnCode) {
            $msg = sprintf('Geolocation: Tile-Request failed (cUrl Error %d / %s)', curl_errno($ch), curl_error($ch));
            rex_logger::logError(E_WARNING, $msg, __FILE__, __LINE__ - 8, rex_context::fromGet()->getUrl([], false) . ' ➜ ' . $url);
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
     * @return array<int,array{layer:int,label:string,type:string,attribution:string}>
     */
    public static function getLayerConfigSet($layerIds, ?string $clang)
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
        // FIXME:: Warum ist online nicht int? YForm und DB haben hier ein int-Feld!
        return 1 === $this->online;
    }

    /**
     * Aus welchem Grunde auch immer werden teilweise Werte, die in der DB integer sind,
     * tatsächlich im internen Array $data als String vorgehalten und demnach auch von
     * parent::getValue als String ausgeliefert.
     *
     * Ist leider keine Kleinigkeit bei striker Typ-Prüfung (z.B. 1 === getValue('online')
     *
     * Hier werden die Werte vor der Nutzung in int umgewandelt
     *
     * TODO: Testcase bauen und Issue aufmachen
     *
     * @return mixed
     */
    public function getValue(string $key)
    {
        $value = parent::getValue($key);
        if (is_string($value) && ('ttl' === $key || 'cfmax' === $key || 'online' === $key)) {
            $value = (int) $value;
        }
        return $value;
    }
}
