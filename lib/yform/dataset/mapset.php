<?php
/**
 * Geolocation|mapset ist eine erweiterte yform-dataset-Klasse für Kartensätze
 * aus einem oder mehreren Kartenlayern.
 *
 * - dataset-spezifisch
 *
 *     get         Modifiziert um anschließend Initialisierungen durchzuführen
 *     getForm:    baut einige Felder im Formular auf aktuelle Daten um
 *     delete:     Löschen nur wenn nicht in Benutzung z.B. in Slices/Modulen etc.
 *                 Abfrage durch EP GEOLOCATION_MAPSET_DELETE, der entsprechend belegt werden muss
 *
 * - Formularbezogen
 *
 *     executeForm           stellt Cache-Löschen bei Änderungen sicher
 *
 * - Listenbezogen
 *
 *     YFORM_DATA_LIST_ACTION_BUTTONS  Button "Cache löschen" für die Datentabelle
 *                                     Action-Button "delete" entfernen beim Default-Mapset
 *
 * - AJAX-Abrufe
 *
 *     sendMapset            Kartensatz-Abruf vom Client beantworten
 *
 * - Support
 *
 *     getValue              Eigene virtuelle Datensatzfelder abrufen
 *     getDefaultId          Default-Mapset-ID abrufen
 *     getLayerset           Array z.B. für <rex-map mapset=...> bereitstellen (Kartensatzparameter)
 *     getMapOptions         Array z.B. für <rex-map map=...> bereitstellen (Kartenoptionen)
 *     getOutFragment        das für Kartendarstellung vorgesehene Fragment (outfragment) mit Fallback
 *                           falls outfragment leer ist
 *     getDefaultOutFragment   Default-Ausgabefragment aus Config und Fallback
 *     take                  Alternative zu get, aber mit Fallback auf die Default-Karte
 *     attributes            sammelt die sonstigen HTML-Attribute ein
 *     dataset               sammelt die Karteninhalte ein (siehe Geolocation.js -> Tools)
 *     parse                 erzeugt Karten-HTML gem. vorgegebenem Fragment
*/

namespace Geolocation;

use Exception;
use rex;
use rex_clang;
use rex_config;
use rex_extension;
use rex_extension_point;
use rex_fragment;
use rex_i18n;
use rex_path;
use rex_request;
use rex_response;
use rex_url;
use rex_yform;
use rex_yform_manager_dataset;

use function count;
use function is_array;

/**
 * Mittels parent::__get bereitgestellte Daten.
 *
 * aus der Datentabelle rex_geolocation_mapset
 * @property string $layer
 * @property string $overlay
 * @property string $outfragment
 *
 * virtuelle Felder in mapset
 * @property array $layerset
 */

class mapset extends rex_yform_manager_dataset
{
    /**
     * @var array<string,string>
     */
    public static array $mapoptions = [
        'fullscreen' => 'translate:geolocation_form_mapoptions_fullscreen',
        'gestureHandling' => 'translate:geolocation_form_mapoptions_zoomlock',
        'locateControl' => 'translate:geolocation_form_mapoptions_location',
    ];

    /**
     * @var array<string,mixed>
     */
    protected array $mapDataset = [];

    /**
     * @var array<string,mixed>
     */
    protected array $mapAttributes = [];

    // dataset-spezifisch

    /**
     * baut einige Felder im Formular auf aktuelle Daten um.
     *
     * liefert das Formular und stellt bei ...
     *    - mapoptions die aktuell zulässigen Optionen bereit
     *    - outfragment einen angepassten Hinweistext
     *    - passt die Ausgabe der Fehlermeldungen an (beim Feld statt über dem Formular)
     */
    public function getForm(): rex_yform
    {
        $yform = parent::getForm();
        $yform->objparams['form_class'] .= ' geolocation-yform';

        foreach ($yform->objparams['form_elements'] as $k => &$fe) {
            if ('validate' === $fe[0]) {
                continue;
            }
            if ('action' === $fe[0]) {
                continue;
            }
            if ('mapoptions' === $fe[1]) {
                $fe[3] = array_merge(['default' => rex_i18n::rawMsg('geolocation_mapset_mapoptions_default')], self::$mapoptions);
                continue;
            }
            if ('outfragment' === $fe[1]) {
                if (str_starts_with($fe[6], 'translate:')) {
                    $fe[6] = substr($fe[6], 10);
                }
                $fe[6] = rex_i18n::rawMsg($fe[6], OUT);
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
     * Der Mapset darf auch dann nicht gelöscht werden, wenn er der Default-Mapset ist
     */
    public function delete(): bool
    {
        if ($this->getId() === self::getDefaultId()) {
            return false;
        }

        $delete = rex_extension::registerPoint(new rex_extension_point(
            'GEOLOCATION_MAPSET_DELETE',
            true,
            ['id' => $this->getId(), 'mapset' => $this]
        ));

        return $delete && parent::delete();
    }

    // Formularbezogen

    /**
     * Erweiterte Funktionalität bei der Ausführung des Formulars.
     *
     * - stellt aktuelle Konfig-Daten als Vorbelegung in ein Add-Formular
     * - Für choice.check wird ein modifiziertes Fragment (nur hier) aktiviert.
     */
    public function executeForm(rex_yform $yform, callable $afterFieldsExecuted = null): string
    {
        // setzt bei leeren Formularen (add) ein paar Default-Werte
        rex_extension::register('YFORM_DATA_ADD', function (rex_extension_point $ep) {
            // nur abarbeiten wenn es um diese Instanz geht
            if ($this !== $ep->getParam('data')) {
                return;
            }
            $objparams = &$ep->getSubject()->objparams;

            // Bug im Ablauf der EPs: YFORM_DATA_ADD wird nach dem Absenden des neuen Formulars
            // noch mal vor dem Speichern ausgeführt und dadurch werden die Eingaben wieder mit
            // den Vorgaben überschrieben. Autsch!
            // Lösung: Wenn im REQUEST Daten zum Formular liegen => Abbruch, nix tun.
            $form = rex_request::request('FORM', 'array', []);
            if (isset($form[$objparams['form_name']])) {
                return;
            }

            $objparams['data'] = [
                'outfragment' => rex_config::get(ADDON, 'map_outfragment', OUT),
                'mapoptions' => 'default',
            ];
        });

        $yform->objparams['form_ytemplate'] = 'geolocation,' . $yform->objparams['form_ytemplate'];
        rex_yform::addTemplatePath(rex_path::addon(ADDON, 'ytemplates'));
        return parent::executeForm($yform, $afterFieldsExecuted);
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

        // Button-Code abspeichern für YFORM_DATA_LIST
        $action_delete = $ep->getSubject()['delete'] ?? null;
        if (null !== $action_delete) {
            rex_extension::register(
                'YFORM_DATA_LIST',
                static function (rex_extension_point $ep) {
                    // nur für diese Tabelle
                    if ($ep->getParam('table_name') !== $ep->getParam('table')->getTableName()) {
                        return;
                    }
                    // Daten zusammensuchen
                    $list = $ep->getSubject();
                    $default = self::getDefaultId();
                    $toDelete = '<li>'.$ep->getParam('action_delete').'</li>';
                    // Spalte "Functions": delete-Action entfernen wenn Default-Mapset
                    $list->setColumnFormat(
                        rex_i18n::msg('yform_function').' ',
                        'custom',
                        static function ($params) use ($toDelete, $default) {
                            return $params['list']->getValue('id') === $default
                                ? str_replace($toDelete, '', $params['value'])
                                : $params['value'];
                        }
                    );
                },
                rex_extension::NORMAL,
                ['action_delete' => $action_delete, 'table_name' => $table_name]);
        }

        $user = rex::getUser();
        if (null !== $user && $user->hasPerm('geolocation[clearcache]')) {
            $link_vars = $ep->getParam('link_vars') + [
                'mapset_id' => '___id___',
                'rex-api-call' => 'geolocation_clearcache',
            ];
            $href = rex_url::backendController($link_vars, false);
            $confirm = rex_i18n::msg('geolocation_clear_cache_confirm', '___name___ [id=___id___]');
            $label = '<i class="rex-icon rex-icon-delete"></i> ' . rex_i18n::msg('geolocation_clear_cache');
            $action = '<a onclick="return confirm(\''.$confirm.'\')" href="'.$href.'">'.$label.'</a>';
            return $ep->getSubject() + ['geolocationClearCache' => $action];
        }
    }

    // AJAX-Abrufe

    /**
     * Layerset-Abruf vom Client.
     *
     * schickt die Konfigurationsdaten für Kartenlayer als JSON-String.
     * Wenn die Kartensatz-ID $mapset nicht existiert, stirbt die Methode mit einem HTTP-Fehlercode + Exit()
     *
     * @api
     * @return never
     */
    public static function sendMapset(int $mapset): void
    {
        // Same Origin
        Tools::isAllowed();

        // Abbruch bei unbekanntem Mapset
        $mapset = self::get($mapset);
        if (null === $mapset) {
            Tools::sendNotFound();
        }

        // get layers ond overlays in scope and send as JSON
        rex_response::cleanOutputBuffers();
        rex_response::sendJson($mapset->getLayerset());

        exit;
    }

    // Support

    /**
     * liefert in der virtuellen Variablen 'layerset' die Nummern aller Layer (basis und overlay)
     * ansonsten normales getValue.
     *
     * @api
     * @return mixed
     */
    public function getValue(string $key)
    {
        if ('layerset' === $key) {
            $layer = explode(',', $this->layer.','.$this->overlay);
            $layer = array_filter($layer, 'trim');
            return array_unique($layer);
        }
        return parent::getValue($key);
    }

    /**
     * liefert die ID des Default-Mapset wie in den Einstellungen festgelegt.
     *
     * @api
     */
    public static function getDefaultId(): int
    {
        return (int) rex_config::get(ADDON, 'default_map');
    }

    /**
     * Array z.B. für <rex-map mapset=...> bereitstellen (Kartensatzparameter).
     *
     * Liefert aufbauend auf dem aktuellen Datensatz ein Array mit erweiterten Kartenkonfigurations-
     * daten z.B. für <rex-map mapset=...>
     *
     * @api
     * NOTE: das muss doch einfache gehen als immer diese Definition (siehe Layer::getLayerConfigSet) abzuschreiben
     * @return list<array{layer:int, label:string, type:string, attribution:string}>
     */
    // TODO: Warum hab ich hier keine Sprachauswahl optional vorgesehen?
    // Später angehen, keine Eile.
    public function getLayerset(): array
    {
        // get layers ond overlays in scope
        $clang = rex_clang::getCurrent()->getCode();
        return Layer::getLayerConfigSet($this->layerset, $clang);
    }

    /**
     * Array z.B. für <rex-map map=...> bereitstellen (Kartensatzoptionen).
     *
     * Liefert aufbauend auf dem aktuellen Datensatz ein Array mit Parametern zum Kartenverhalten
     * z.B. für <rex-map map=...>
     *
     * Sonderfall "default": dann wird nur die Option "default=true" zurückgemeldet. I.V.m. $full
     * umfasst die Rückgabe alle im "default" aktivierten enthaltenen Einzeloptionen
     *
     * @api
     * @return list<string>|array<string,bool>      Array mit Kartensatzoptionen
     */
    // TODO: ist das sinnvoll, zwei Varianten der Ergebnisliste zuerzeugen, was wird wo benötigt?
    // Erstmal so lassen; zu einem späteren Zeitpunkt prüfen und ggf. überarbeiten
    public function getMapOptions(bool $full = true): array
    {
        $value = [];

        // MapOptions im Datensatz;
        $options = explode(',', $this->getValue('mapoptions'));
        $defaultRequired = false !== array_search('default', $options, true);

        // für:
        // - Sonderfall Abgleich mit allgemeiner Config; nur wenn Unterschiede
        // - default angefordert
        // ... die allgemeinen Werte laden
        if ($defaultRequired || !$full) {
            $config = explode('|', trim(rex_config::get(ADDON, 'map_components'), '|'));
            if ($defaultRequired) {
                return $config;
            }
            sort($options);
            sort($config);
            $full = $config !== $options;
        }

        // Unterschiede? Wenn nein Ende
        if ($full) {
            foreach (self::$mapoptions as $k => $v) {
                $value[$k] = false !== array_search($k, $options, true);
            }
        }

        return $value;
    }

    /**
     * Liefert das für die Kartenanzeige dieses Kartensatzes vorgesehene Fragment.
     *
     * Falls kein Fragment angegeben ist, wird als Fallback das Default-Fragment zurückgemeldet
     *
     * Achtung: REX-Fragment (addon/fragments/xyz) nicht yfragment!
     *
     * @api
     * @return string      Name der Fragment-Datei (xyz.php)
     */
    public function getOutFragment(): string
    {
        return ('' === $this->outfragment) ? self::getDefaultOutFragment() : $this->outfragment;
    }

    /**
     * Liefert das Default-Fragment für die Kartenanzeige.
     *
     * Falls kein gesondertes Default-Fragment angegeben ist, wird als Fallback das voreingestellte
     * Basis-Fragment (Geolocation\OUT) zurückgemeldet
     *
     * Achtung: REX-Fragment (addon/fragments/xyz) nicht yfragment!
     *
     * @return string      Name der Fragment-Datei (xyz.php)
     */
    public static function getDefaultOutFragment()
    {
        return rex_config::get(ADDON, 'map_outfragment', OUT);
    }

    /**
     * Alternative zu get(), aber mit Fallback auf die Default-Karte.
     *
     * Man kann auch Geolocation\mapset::get($id) nehmen ... aber hier wird im Fehlerfall oder wenn
     * $id aus irgend einem Grunde nicht gefunden wird, ein Fallback auf den Default-Kartensatz
     * erfolgen.
     *
     * Rückgabe ist also immer ein gültiger Kartensatz sofern überhaupt ein Kartensatz existert.
     *
     * @api
     */
    public static function take(?int $id = null): self
    {
        try {
            if (null === $id) {
                throw new InvalidMapsetParameter(InvalidMapsetParameter::MAPSET_ID, ['«leer»']);
            }
            $map = self::get($id);
            if (null === $map) {
                throw new InvalidMapsetParameter(InvalidMapsetParameter::MAPSET_ID, [$id]);
            }
        } catch (Exception $e) {
            $map = self::get(self::getDefaultId());
            if (null === $map) {
                throw new InvalidMapsetParameter(InvalidMapsetParameter::MAPSET_DEF, [self::getDefaultId()]);
            }
        }
        return $map;
    }

    /**
     * sammelt die sonstigen HTML-Attribute für den Aufbau des HTML-Karten-Tags ein.
     *
     * Entweder wird als $name ein Array ['attribut_a'=>'inhalt',..] angegeben oder der Attribut-Name.
     * Im zweiten Fall muss $data den Attributwert als String enthalten oder leer sein (entspricht '')
     *
     * @api
     * @param string|array<string,string|int|float|bool> $name
     */
    public function attributes(string|array $name, string|int|float|bool $data = ''): self
    {
        if (is_array($name)) {
            $this->mapAttributes = array_merge($this->mapAttributes, $name);
        } else {
            $this->mapAttributes[$name] = $data;
        }
        return $this;
    }

    /**
     * sammelt die Karteninhalte ein (siehe Geolocation.js -> Tools).
     *
     * Darüber werden dem Kartensatz die Inhalte (Marker etc.) hinzugefügt. Sie landen als
     * DATASET-Attribut im Karten-HTML.
     * Siehe Doku zum Thema "Tools"
     *
     * Entweder wird als $name ein Array ['tool_a'=>'inhalt',..] angegeben oder der Tool-Name.
     * Im zweiten Fall muss $data (die Tool-Daten) leer sein (entspricht '')
     *
     * @api
     * @param string|array<string,mixed> $name
     * @param mixed $data
     */
    public function dataset(string|array $name, mixed $data = null): self
    {
        if (is_array($name)) {
            $this->mapDataset = array_merge($this->mapDataset, $name);
        } else {
            $this->mapDataset[$name] = $data;
        }
        return $this;
    }

    /**
     * erzeugt Karten-HTML gem. vorgegebenem Fragment.
     *
     * baut eine Kartenausgabe (HTML) auf, indem Kartensatz-Konfiguration (mapset), die
     * Kartenoptionen (map) und die Karteninhalte(dataset) durch das Fragment geschickt werden.
     * Siehe Doku zum Thema "Für Entwickler"
     *
     * Wurde kein Fragment angegeben, wird das Default-Fragment des Kartensatzes herangezogen
     *
     * @api
     */
    public function parse(?string $fragmentFile = null): string
    {
        $fragment = new rex_fragment();
        $fragment->setVar('mapset', $this->getLayerset(), false);
        $fragment->setVar('dataset', $this->mapDataset, false);
        if (isset($this->mapAttributes['class'])) {
            $fragment->setVar('class', $this->mapAttributes['class'], false);
            unset($this->mapAttributes['class']);
        }
        if (0 < count($this->mapAttributes)) {
            $fragment->setVar('attributes', $this->mapAttributes, false);
        }
        $fragment->setVar('map', $this->getMapOptions(false), false);

        if (null === $fragmentFile || '' === $fragmentFile) {
            $fragmentFile = $this->getOutFragment();
        }
        return $fragment->parse($fragmentFile);
    }
}
