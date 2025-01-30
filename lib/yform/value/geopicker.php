<?php

/**
 * Der Picker bietet:
 * - Geolocation-Kartensätze für die Kartendarstellung
 * - Ein Eingabefeld für die Suche nach Adressen
 * - Eingabefelder für die Eingabe bzw. Sichtbarmachung der Koordinaten (Lat,Lng)
 * - Außerhalb dieses Values liegende Lat-/Lng-Felder nutzen
 * - Zentrale Basisvalidierung (gültige Koordinaten, Leer-Prüfung)
 * - optische Gestaltungsmöglichkeiten (Pin-Farbe, Kreis um den Marker)
 * - Validierung der Feldkonfiguration im TableManager
 *
 * Validierung immer über dieses Field, auch wenn die Koordinaten extern gespeichert sind
 * Anderweitige Fehlermeldungen direkt beim feld werden ignoriert und ggf. gelöscht.
 *
 * Dieses Feld nur per Custom-Validator analysieren
 */

use FriendsOfRedaxo\Geolocation\Calc\Box;
use FriendsOfRedaxo\Geolocation\Calc\InvalidPointParameter;
use FriendsOfRedaxo\Geolocation\Calc\Point;
use FriendsOfRedaxo\Geolocation\Exception;
use FriendsOfRedaxo\Geolocation\GeoCoder;
use FriendsOfRedaxo\Geolocation\Mapset;
use rex_yform_value_abstract;

use function sprintf;

class rex_yform_value_geolocation_geopicker extends rex_yform_value_abstract
{
    private const MIN_MYSQL = '5.7';
    private const MIN_MARIADB = '10.5.10';

    protected ?rex_yform_value_abstract $latField = null;
    protected ?rex_yform_value_abstract $lngField = null;

    // TODO: die Werte aus einer zentralen Konstanten holen?
    private static int $markerRadius = 250;
    private static int $minMarkerRadius = 25;

    protected bool $useExternalFields = false;

    protected bool $notEmpty = false;

    protected ?Point $point = null;

    /** @var array<string> */
    protected array $error = [];

    /**
     * Wandelt das Element "type in ein handliches Flag um.
     *
     * @ return void;
     */
    public function init(): void
    {
        $this->useExternalFields = 'external' === $this->getElement('type');
        $this->notEmpty = '1' === $this->getElement('not_required');
    }

    /**
     * Extern:  Die Daten werden aus den zwei Einzelfeldern Lat und Lng abgerufen.
     *          Daraus wird wiederum das Value-Array mit LatLng gebildet.
     * Intern:  Die Daten sind insgesamt als Formular-Array erhältlich.
     * Initial: Beim ersten Aufruf "intern" liegt ein String oder null vor, kein Array.
     *          In dem Fall den String in ein Array umwandeln (['',''] wenn null)
     * Numerische Werte werden in float konvertiert.
     * Ansonsten finden hier keine weiteren Überprüfungen statt.
     * @api
     */
    public function preValidateAction(): void
    {
        /* * @var string|array<mixed> $value */

        /**
         * Die Instanzen der LatLng-Felder ermitteln falls "extern" aus den beiden Felder
         * entnehmen oder wenn intern dann das Feld zerlegen
         * Das Ergebnis ist jeweils ein Array [lat=>,lng=>] zur internen Weiterverarbeitung.
         */
        if ($this->useExternalFields) {
            // Felder ermitteln
            /** @var rex_yform_value_abstract $v */
            foreach ($this->getParam('values') as $k => $v) {
                if ($v->getName() === $this->getElement('lat')) {
                    $this->latField = $v;
                    continue;
                }
                if ($v->getName() === $this->getElement('lng')) {
                    $this->lngField = $v;
                }
            }

            // Mindestens ein Feld nicht ermittelbar -> Developer-Fehler
            if (null === $this->latField || null === $this->lngField) {
                throw new Exception('Error Processing Request', 1);
            }

            $value = [
                'lat' => ($this->latField->getValue() ?? ''),
                'lng' => ($this->lngField->getValue() ?? ''),
            ];
        } else {
            // Value entnehmen
            $value = $this->getValue() ?? '';
            if (is_string($value)) {
                if ('' === trim($value)) {
                    $value = [
                        'lat' => '',
                        'lng' => '',
                    ];
                } else {
                    $value = preg_split('@[\s,]+@', $value, flags: PREG_SPLIT_NO_EMPTY);
                    if ('latlng' === $this->getElement('type')) {
                        $value = [
                            'lat' => $value[0] ?? '',
                            'lng' => $value[1] ?? '',
                        ];
                    } else {
                        $value = [
                            'lat' => $value[1] ?? '',
                            'lng' => $value[0] ?? '',
                        ];
                    }
                }
            }
            $This = $this;
        }

        /**
         * Optimistische Annahme: jetzt gibt es ein Array [lat=>,lng=>] mit den Werten,
         * die sicherheitshalber noch einmal aufbereitet werden, damit es '' oder Float ist.
         */
        if (1 === $this->params['send']) {
            /**
             * Validierung der Einzelwerte.
             */
            $border = $this->getElement('range');
            try {
                $border = self::decodeBounds($border);
            } catch (\Throwable $th) {
                $border = [[-90, -180], [90, 180]];
            }
            $latMessage = self::validateItem($value['lat'], $border[0][0], $border[1][0], $this->notEmpty);
            $lngMessage = self::validateItem($value['lng'], $border[0][1], $border[1][1], $this->notEmpty);

            /**
             * Fehlermeldungen an die Felder weitergeben
             * Bei externen Feldern ist das einfach ...
             * Bei internen Feldern werden die Fehlermeldungen zunächst als Array gespeichert
             * und vor der Ausgabe in einen Text umgewandelt und die internen Felder.
             */
            if ($this->useExternalFields) {
                if ('' < $latMessage) {
                    $this->setErrorMessage($this->latField, $latMessage);
                }
                if ('' < $lngMessage) {
                    $this->setErrorMessage($this->lngField, $lngMessage);
                }
            } else {
                $this->error = [];
                if ('' < $latMessage) {
                    $this->error['lat'] = rex_i18n::msg('geolocation_lat_label') . ': ' . $latMessage;
                }

                if ('' < $lngMessage) {
                    $this->error['lng'] = rex_i18n::msg('geolocation_lng_label') . ': ' . $lngMessage;
                }
                if( 0 < count($this->error)) {
                    $msg = implode(' | ', $this->error);
                    $this->setErrorMessage($this, $msg);
                }
            }
        }
        $this->setValue($value);
    }

    protected function setErrorMessage(rex_yform_value_abstract $field, string $message): void
    {
        $this->params['warning'][$field->getId()] = $this->params['error_class'];
        $this->params['warning_messages'][$field->getId()] = $field->getLabel() . ': ' . $message;
    }

    /**
     * HTML für das Feld generieren
     * Daten ggf. speichern.
     *
     * $value enthält die Koordinate
     *
     * @api
     */
    public function enterObject(): void
    {
        $value = $this->getValue();
        $This = $this;
        dump(['enterObject' => get_defined_vars()]);

        if ($this->needsOutput()) {
            /**
             * Liste der Adressfelder in der vorgegebenen Reihenfolge.
             */
            $addressFields = [];
            $fields = preg_split('@[\s,]+@', $this->getElement('fields'), flags: PREG_SPLIT_NO_EMPTY);
            if (0 < count($fields)) {
                $addressFields = $fields;
                /** @var rex_yform_value_abstract $v */
                foreach ($this->getParam('values') as $k => $v) {
                    $key = array_search($v->getName(), $fields, true);
                    if (false !== $key) {
                        $addressFields[$key] = [
                            'id' => $v->getFieldId(),
                            'label' => $v->getLabel(),
                        ];
                    }
                }
                $addressFields = array_combine(
                    array_column($addressFields, 'id'),
                    array_column($addressFields, 'label'),
                );
            }

            /** @var ?Point $point */
            $point = null;
            try {
                $point = Point::factory($value, 'lat', 'lng');
            } catch (Exception $e) {
            }

            /** @var ?Box $defaultBounds */
            $defaultBounds = null;
            try {
                $default = json_decode(sprintf('[%s]', $this->getElement('default')), true) ?? [];
                $defaultBounds = Box::byCorner(
                    Point::byLatLng($default[0] ?? []),
                    Point::byLatLng($default[1] ?? []),
                );
            } catch (Exception $e) {
            }

            $params = [
                // Id des Geolocation-Mapset
                'mapsetId' => $this->getElement('mapset'),
                // Klasse zur Kartenfoormatierung (Höhe/Breite,..)
                'mapsetClass' => '',
                // Externe oder interne LatLng-Felder
                'type' => $this->getElement('type'),
                // Radius in Meter um die Position (sorgt für den Zoom)
                'radius' => self::$markerRadius,
                // initiale Kartenansicht wenn es keine Werte gibt
                'defaultBounds' => $defaultBounds,
                // Koordinaten-Felder
                'latLngId' => [
                    'lat' => $this->useExternalFields ? $this->latField->getFieldId() : $this->getFieldId('lat'),
                    'lng' => $this->useExternalFields ? $this->lngField->getFieldId() : $this->getFieldId('lng'),
                ],
                'latLngName' => [
                    'lat' => $this->useExternalFields ? $this->latField->getFieldName() : $this->getFieldName('lat'),
                    'lng' => $this->useExternalFields ? $this->lngField->getFieldName() : $this->getFieldName('lng'),
                ],
                'latLngValue' => $point,
                // Verlinkung zu Feldern mit Adress-Teilen
                'addressFields' => $addressFields,
                // Formatierung des Markers/Pins (Farbe)
                'markerStyle' => $this->getElement('params'),
                'geoCoder' => GeoCoder::take($this->getElement('geocoder')),
                // Fehlermeldungen
                'error' => $this->error,
            ];

            $this->params['form_output'][$this->getId()] = $this->parse('value.geolocation_geopicker.php', $params);
        }

        /**
         * Lat und Lng wieder zusammenfassen und speichern.
         */
        switch ($this->getElement('type')) {
            case 'latlng':
                $this->setValue(trim(sprintf('%s,%s', $value['lat'], $value['lng'])));
                break;
            case 'lnglat':
                $this->setValue(trim(sprintf('%s,%s', $value['lng'], $value['lat'])));
                break;
            case 'external':
                $this->latField->setValue($value['lat']);
                $this->lngField->setValue($value['lng']);
        }

        if ($this->useExternalFields) {
            $this->params['value_pool']['email'][$this->latField->getName()] = $value['lat'];
            $this->params['value_pool']['email'][$this->lngField->getName()] = $value['lng'];
            if ($this->saveInDB()) {
                $this->params['value_pool']['sql'][$this->latField->getName()] = $value['lat'];
                $this->params['value_pool']['sql'][$this->lngField->getName()] = $value['lng'];
            }
        } else {
            $this->params['value_pool']['email'][$this->getName()] = $this->getValue();
            if ($this->saveInDB()) {
                $this->params['value_pool']['sql'][$this->getName()] = $this->getValue();
            }
        }
    }

    /**
     * TODO: 1) richtig machen und 2) prüfen ob überhaput nötig. (Stichwort "nur BE").
     * @api
     */
    public function getDescription(): string
    {
        return 'geolocation_geopicker|osmgeocode|Bezeichnung|format[latlng|lnglat|extern]|lat|lng|strasse,plz,ort|height|class|[mapbox_token]|[no_db]';
    }

    /**
     * @api
     * @return array<string, mixed>
     */
    public function getDefinitions(): array
    {
        return [
            'type' => 'value',
            'name' => 'geolocation_geopicker',
            'values' => [
                'name' => [
                    'type' => 'name',
                    'label' => 'translate:yform_values_defaults_name',
                ],
                'label' => [
                    'type' => 'text',
                    'label' => 'translate:yform_values_defaults_label',
                ],
                'mapset' => [
                    'type' => 'choice',
                    'label' => 'translate:geolocation_mapset',
                    // TODO: mit einer neuen Methode Mapset::getList abrufen (analog zu GeoCoder::getList)
                    'choices' => [0 => 'translate:geolocation_config_map_default'] +
                                 Mapset::query()
                                    ->orderBy('title')
                                    ->find()
                                    ->toKeyValue('id', 'title'),
                    'default' => 0,
                ],
                'geocoder' => [
                    'type' => 'choice',
                    'label' => 'translate:geolocation_yfv_geopicker_geocoder',
                    'choices' => GeoCoder::getList(true),
                    'default' => 0,
                ],
                'fields' => [
                    'type' => 'text',  // "select_names" kann man nicht nehmen, da die Reihenfolge der Feldnamen wichtig ist.
                    'label' => 'translate:geolocation_yfv_geopicker_fields',
                    'notice' => 'translate:geolocation_yfv_geopicker_fields_notice',
                ],
                'type' => [
                    'type' => 'choice',
                    'label' => 'translate:geolocation_yfv_geopicker_type',
                    'default' => 'external',
                    'choices' => [
                        'latlng' => 'translate:geolocation_yfv_geopicker_type_c_latlng',
                        'lnglat' => 'translate:geolocation_yfv_geopicker_type_c_lnglat',
                        'external' => 'translate:geolocation_yfv_geopicker_type_c_extern',
                    ],
                    'expanded' => 1,
                    /**
                     * die beiden folgenden Attribute steuern via JS die Sichtbarkeit der Felder lat und lng.
                     */
                    'group_attributes' => json_encode([
                        'geolocation-yvc' => json_encode([
                            'self' => '-type',
                            'group' => ['-lat', '-lng'],
                            'latlng' => [],
                            'lnglat' => [],
                            'external' => ['-lat', '-lng'],
                        ]),
                    ]),
                    'attributes' => json_encode([
                        'geolocation-yvc-source' => '-type',
                    ]),
                ],
                'lat' => [
                    'type' => 'select_name',
                    'label' => 'translate:geolocation_yfv_geopicker_lat',
                ],
                'lng' => [
                    'type' => 'select_name',
                    'label' => 'translate:geolocation_yfv_geopicker_lng',
                ],
                'size' => [
                    'type' => 'number',
                    'label' => 'translate:geolocation_form_geopicker_radius',
                    'default' => (int) rex_config::get('geolocation', 'picker_radius', self::$markerRadius),
                    'scale' => 0,
                    'widget' => 'input:number',
                    'notice' => rex_i18n::msg('geolocation_form_picker_radius_notice', self::$minMarkerRadius),
                ],
                'params' => [
                    'type' => 'text',
                    'label' => 'translate:geolocation_yfv_geopicker_params',
                    'notice' => 'translate:geolocation_yfv_geopicker_params_notice',
                ],
                'default' => [
                    'type' => 'text',
                    'label' => 'translate:geolocation_yfv_geopicker_bounds',
                    'default' => '',
                    'placeholder' => rex_config::get('geolocation', 'map_bounds'),
                    'notice' => 'translate:geolocation_yfv_geopicker_bounds_notice',
                ],
                'format' => [
                    'type' => 'choice',
                    'label' => 'geolocation_yfv_geopicker_format',
                    'default' => 'dd1',
                    'choices' => [
                        'dd' => 'translate:geolocation_yfv_geopicker_format_c_dd',
                        'dd1' => 'translate:geolocation_yfv_geopicker_format_c_dd1',
                        'dm' => 'translate:geolocation_yfv_geopicker_format_c_dm',
                        'dm1' => 'translate:geolocation_yfv_geopicker_format_c_dm1',
                        'dms' => 'translate:geolocation_yfv_geopicker_format_c_dms',
                    ],
                    'expanded' => 0,
                    'notice' => 'translate:geolocation_yfv_geopicker_format_notice',
                ],
                'not_required' => [
                    'type' => 'choice',
                    'label' => 'translate:geolocation_yfv_geopicker_empty',
                    'default' => '1',
                    'choices' => [
                        '1' => 'translate:geolocation_yfv_geopicker_empty_c_1',
                    ],
                    'expanded' => 1,
                    'multiple' => 1,
                ],
                'range' => [
                    'type' => 'text',
                    'label' => 'translate:geolocation_yfv_geopicker_range',
                    'notice' => 'translate:geolocation_yfv_geopicker_range_notice',
                    'placeholder' => '[-90,-180][90,180]',
                ],
                'notice' => [
                    'type' => 'text',
                    'label' => 'translate:yform_values_defaults_notice',
                ],
            ],
            'validates' => [
                ['customfunction' => ['name' => 'fields', 'function' => $this->validateFields(...)]],
                ['customfunction' => ['name' => 'lat', 'function' => $this->validateLatLng(...)]],
                ['customfunction' => ['name' => 'lng', 'function' => $this->validateLatLng(...)]],
                ['customfunction' => ['name' => ['lat', 'lng'], 'function' => $this->validateLatLngFields(...)]],
                ['customfunction' => ['name' => 'size', 'function' => $this->validateSize(...)]],
                ['customfunction' => ['name' => 'params', 'function' => $this->validateParams(...)]],
                ['customfunction' => ['name' => 'default', 'function' => $this->validateBox(...)]],
                ['customfunction' => ['name' => 'range', 'function' => $this->validateBox(...)]],
                ['customfunction' => ['name' => 'search', 'function' => $this->validateSearch(...)]],
            ],
            'description' => 'translate:geolocation_yfv_geopicker_description',
            'db_type' => ['varchar(50)'],
            'formbuilder' => false,
            'multi_edit' => false,
        ];
    }

    /**
     * Suche nach Lokationen per Umkreissuche.
     *
     * Der Suchfilter initiiert eine Umkreissuche. Das wird konkret mit den Spatial-Funktionen erledigt, die
     * aber erst ab MySQL 5.7 (Redaxo: ab 5.6) bzw. MariaDB 10.5.10 (Redaxo: ab 10.1) vorhanden sind.
     * Die Version wird in der Feldkonfogration beim Freischalten der Suche abgefragt.
     * Sicherheitshalber machen wir das hier noch einmal und gehen mit einer Exception raus.
     *
     * Bis auf weiters erfolgt die Aingabe als String aus drei Elementen, getrennt durch ; oder Leerstellen
     * - Breitengrad (bis ±90)
     * - Längengrad (bis ±180)
     * - Suchradius (ab 0.01km ≙ 10m)
     *
     * Dezimaltrenner ist . und ,. Beim Speichern wir , in . umgewandelt
     *
     * @api
     * @param array<mixed> $params
     */
    public static function getSearchField(array $params): void
    {
        $checkResult = self::verifyDbVersion();
        if (is_string($checkResult)) {
            throw new Exception($checkResult, 1);
        }

        /** @var rex_yform $yform */
        $yform = $params['searchForm'];

        /** @var rex_yform_manager_field $field */
        $field = $params['field'];

        $yform->setValueField('text', [
            'name' => $field->getName(),
            'label' => $field->getLabel(),
            'notice' => 'Suchbegriff: breitengrad längengrad radius | Bsp: "52.51627 13.377703 500" | Radius in Meter',
        ]);

        // Zur Kommunikation von Custom_Validatore und getSearchFilter
        $yform->setValidateField('customfunction', [
            $field->getName(),
            self::validateSearchTerm(...),
            '',
            'Fehlermeldung',
            '1',
        ]);
    }

    /**
     * Der Suchfilter initiiert eine Umkreissuche. Das wird konkret mit den Spatial-Funktionen erledigt, die
     * aber erst ab MySQL 5.7 (Redaxo: ab 5.6) bzw. MariaDB $field->getElement('lat').
     *
     * mit verifySearchTerm wird der Suchbegriff aufbereitet und zugleich verifiziert.
     * eigentlich sollte das bereits durch den Custom-Validator der Eingebe erledigt sein.
     * Leider werden aktuell desen Ergebnisse vor dem Aufruf dieser Methode nicht berücksichtigt.
     * Daher doppelte Abfrage.
     * siehe https://github.com/yakamara/yform/pull/1549
     *
     * @api
     * @param mixed $params
     * @return rex_yform_manager_query<rex_yform_manager_dataset>
     */
    public static function getSearchFilter($params): rex_yform_manager_query
    {
        $parts = self::verifySearchTerm($params['value']);

        if (!is_array($parts)) {
            return $params['query'];
        }

        /**
         * Objekte etc. handlich bereitstellen.
         */
        [$lat, $lng, $radius] = $parts;

        /** @var rex_yform_manager_field $field */
        $field = $params['field'];

        /** @var rex_yform_manager_query<rex_yform_manager_dataset> $query */
        $query = $params['query'];
        $alias = $query->getTableAlias();

        /**
         * Je nach Typ (extern/intern) die Abfrage aufbauen.
         */
        $type = (string) $field->getElement('type');
        switch ($type) {
            case 'latlng':
                return $query->whereRaw(GeoCoder::circleSearchLatLng($lat, $lng, $radius, $field->getName(), $alias));
            case 'lnglat':
                return $query->whereRaw(GeoCoder::circleSearchLngLat($lat, $lng, $radius, $field->getName(), $alias));
            case 'external':
                return $query->whereRaw(GeoCoder::circleSearch($lat, $lng, $radius, $field->getElement('lat'), $field->getElement('lng'), $alias));
            default:
                return $query;
        }
    }

    /**
     * Listendarstellung: je nach gewähltem Typ wird das Feld dezimal oder
     * in einer Koordinaten-Notation dargestellt.
     * "Try" fängt ungültige Koordinaten ab; Point wirft dann nämlich eine Exception.
     *
     * @api
     * @param mixed $params
     */
    public static function getListValue($params): string
    {
        // Try fängt ungültige Koordinaten ab; Point wirft dann eine Exception
        try {
            switch ($params['params']['field']['type']) {
                case 'external':
                    $latField = $params['params']['field']['lat'];
                    $lngField = $params['params']['field']['lng'];
                    /** @var rex_yform_list $list */
                    $list = $params['list'];
                    $point = Point::byLatLng([$list->getValue($latField), $list->getValue($lngField)]);
                    break;
                case 'latlng':
                    $value = preg_split('@[\s,]+@', $params['value'], flags: PREG_SPLIT_NO_EMPTY);
                    $value = array_map(floatval(...), $value);
                    $point = Point::byLatLng($value);
                    break;
                case 'lnglat':
                    $value = preg_split('@[\s,]+@', $params['value'], flags: PREG_SPLIT_NO_EMPTY);
                    $value = array_map(floatval(...), $value);
                    $point = Point::byLngLat($value);
                    break;
                default:
                    return $params['subject'];
            }
        } catch (InvalidPointParameter $th) {
            return '';
        }
        // Fallback
        $format = $params['params']['field']['format'];
        $format = '' === $format ? 'dd1' : $format;
        return self::formatLatLng($point, $format, '</br>');
    }

    /**
     * Die Formatierung für die Listenausgabe beruht auf den Formatierungen, die
     * Geolocation über den vendor phpgeo einsetzt; es erfolgt für die Listenausgabe einer
     * weitergehende Aufbereitung:
     * - Trennzeichen zwischen Lat und Lng wird ein Zeilenumbruch (</br>)
     * - Je nach Vorzeichen (Himmelsrichtung) wird der Kennbuchstabe (N/S bzw. E/W) angehängt.
     *
     * NOTICE:
     * (nein, nicht O wie Ost sondern E wie East um Verwechselungen mit 0 wie Null zu vermeiden.
     * Und ddas werde ich auch nicht ändern)
     *
     * @api
     */
    public static function formatLatLng(Point $point, string $format, string $delimiter = ', '): string
    {
        switch ($format) {
            case 'dd':  $text = $point->text(Point::DD, '|');
                break;
            case 'dd1': $text = $point->text(Point::DD, '|', 6);
                break;
            case 'dm':  $text = $point->text(Point::DM, '|');
                break;
            case 'dm1': $text = $point->text(Point::DM, '|', 6);
                break;
            case 'dms': $text = $point->text(Point::DMS, '|');
                break;
            default:
                return implode($delimiter, $point->latLng(6));
        }
        $parts = explode('|', $text);
        $parts[0] = str_starts_with($parts[0], '-') ? (substr($parts[0], 0) . 'S') : ($parts[0] . 'N');
        $parts[1] = str_starts_with($parts[1], '-') ? (substr($parts[1], 0) . 'W') : ($parts[1] . 'E');
        return implode($delimiter, $parts);
    }

    /**
     * Validator für die Feld-Konfiguration.
     *
     * Überprüft die angegebenen Felder für Adress-Teile
     * - nicht "dieses" Feld
     * - jedes darf nur einmal vorkommen -> Doppelte Namen werden automatisch erntfernt.
     * - jedes muss ein gültiger Feldname ein.
     * - keine trennenden Leerzeichen im Feld -> Leerzeichen werden automatsch entfernt
     * - Evtl vorkommende Lat/Lng-Feldnamen (nur bei Type=extern relevant), werden dort überprüft
     *
     * Wenn ok: korrigerten Feldinhalt zurückschreiben.
     *
     * @param array<rex_yform_value_abstract> $fields
     */
    protected function validateFields(string $field_name, string $value, bool $return, rex_yform_validate_customfunction $self, array $fields): bool
    {
        /**
         * Eingabe in ein Array auflösen und formal bereinigen.
         */
        $address_field_names = preg_split('@[\s,]+@', $value, flags: PREG_SPLIT_NO_EMPTY);
        $address_field_names = array_unique($address_field_names);

        /**
         * Der eigene Feldname darf nicht in der Liste vorkommen.
         */
        $valueName = $self->getValueObject('name')->getValue();
        if (in_array($valueName, $address_field_names, true)) {
            return self::setValidatorMsg($self, 'geolocation_yfv_geopicker_error_self', $fields[$field_name]->getLabel(), $valueName);
        }

        /**
         * Liste der Feldnamen in der Tabelle abrufen und ermitteln, welches angegebene Feld
         * nicht im Formular vorkommt.
         */
        $sql = rex_sql::factory();
        $field_list = $sql->getArray(
            'SELECT id,name FROM ' . rex::getTable('yform_field') . ' WHERE type_id = :ti AND table_name = :tn',
            [
                ':ti' => 'value',
                ':tn' => $self->getParam('form_hiddenfields')['table_name'],
            ],
            PDO::FETCH_KEY_PAIR,
        );

        /**
         * Fehler: unbekanntes Feld.
         */
        $unknown_fields = array_diff($address_field_names, $field_list);
        if (0 < count($unknown_fields)) {
            return self::setValidatorMsg($self, 'geolocation_yfv_geopicker_fields_err_1', implode('», «', $unknown_fields));
        }

        /**
         * formal bereinigte Liste in das Feld zurückgeben.
         */
        $fields[$field_name]->setValue(implode(',', $address_field_names));
        return false;
    }

    /**
     * Validator für die Feld-Konfiguration.
     *
     * Überprüft, ob Feld LAT bzw. LNG halbwegs korrekt ausgewählt wurde.
     * - nicht der Name dieses Feldes
     * - keines der Adress-Felder
     *
     * @param array<rex_yform_value_abstract> $fields
     */
    protected function validateLatLng(string $field_name, string $value, bool $return, rex_yform_validate_customfunction $self, array $fields): bool
    {
        // Nur relevant wenn "externe" Felder
        if ('external' !== $self->getValueObject('type')->getValue()) {
            return false;
        }

        // nicht der eigene Feldname ausgewählt
        $valueName = $self->getValueObject('name')->getValue();
        if ($valueName === $value) {
            return self::setValidatorMsg($self, 'geolocation_yfv_geopicker_error_self', $fields[$field_name]->getLabel(), $valueName);
        }

        // Adressfeld ausgewählt?
        // keines der Address-Felder darf als Lat/Lng ausgewählt sein.
        $reference = $self->getValueObject('fields')->getValue();
        $addressList = preg_split('@[\s,]+@', $reference, flags: PREG_SPLIT_NO_EMPTY);
        if (in_array($value, $addressList, true)) {
            return self::setValidatorMsg($self, 'geolocation_yfv_geopicker_latlng_err_1', $fields[$field_name]->getLabel(), $reference);
        }
        return false;
    }

    /**
     * Validator für die Feld-Konfiguration.
     *
     * Überprüft, ob die angegebenen Felder für LAT/LNG untereinander korrekt sind sind.
     * - unterschiedliche Namen
     *
     * @param array<string> $field_name
     * @param array<string, string> $value
     * @param array<rex_yform_value_abstract> $fields
     */
    protected function validateLatLngFields(array $field_name, array $value, bool $return, rex_yform_validate_customfunction $self, array $fields): bool
    {
        // Nur relevant wenn "externe" Felder
        if ('external' !== $self->getValueObject('type')->getValue()) {
            return false;
        }

        // keine unterschiedlichen Felder ausgewählt
        if (array_unique($value) !== $value) {
            return self::setValidatorMsg($self, 'geolocation_yfv_geopicker_latlng_err_2', $fields[$field_name[0]]->getLabel(), $fields[$field_name[1]]->getLabel());
        }
        return false;
    }

    /**
     * Validator für die Feld-Konfiguration.
     *
     * Prüft ab, dass der Mindest-Radius eingehalten ist
     *
     * @param array<rex_yform_value_abstract> $fields
     */
    protected function validateSize(string $field_name, string $value, bool $return, rex_yform_validate_customfunction $self, array $fields): bool
    {
        $value = trim($value);
        if ('' === $value) {
            return false;
        }

        if (!is_numeric($value)) {
            return self::setValidatorMsg($self, 'geolocation_config_geocoding_radius_error', $fields[$field_name]->getLabel(), self::$minMarkerRadius);
        }

        $val = (int) $value;
        if ($val < self::$minMarkerRadius) {
            return self::setValidatorMsg($self, 'geolocation_config_geocoding_radius_error', $fields[$field_name]->getLabel(), self::$minMarkerRadius);
        }
        return false;
    }

    /**
     * Validator für die Feld-Konfiguration.
     *
     * Prüft ab, dass die Marker-Parameter ein gültiges JSON-Format haben
     * Die Komponenten werden nicht überprüft. Dazu kommt was in die Doku
     *
     * @param array<rex_yform_value_abstract> $fields
     */
    protected function validateParams(string $field_name, string $value, bool $return, rex_yform_validate_customfunction $self, array $fields): bool
    {
        $value = trim($value);
        if ('' === $value) {
            return false;
        }

        $params = json_decode($value, true);
        if (null === $params) {
            return self::setValidatorMsg($self, 'geolocation_yfv_geopicker_params_err');
        }
        return false;
    }

    /**
     * Validator für die Feld-Konfiguration.
     *
     * Überprüft, ob der angegebene Default-Auschnitt der Karte korrekt Koordinaten aufweist
     * - Zwei Wertepaare [lat,lng], komma-getrennt
     * - Werte innerhalb der Grenzen (±90 bzw. ±180)
     *
     * @param array<rex_yform_value_abstract> $fields
     */
    protected function validateBox(string $field_name, ?string $value, bool $return, rex_yform_validate_customfunction $self, array $fields): bool
    {
        /**
         * Wenn $value leer ist, wird eh der Default-Value gesetzt.
         * Weitere Überprüfungen sind dann nicht erforderlich.
         */
        $value = trim($value);
        if ('' === $value) {
            return false;
        }

        /** @var rex_yform_value_abstract */
        $field = $fields[$field_name];

        try {
            $location = self::decodeBounds($value);
        } catch (Exception $e) {
            return self::setValidatorRawMsg($self, sprintf('%s: %s', $field->getLabel(), $e->getMessage()));
        }

        $value = sprintf('[%s,%s],[%s,%s]', $location[0][0], $location[0][1], $location[1][0], $location[1][1]);
        $field->setValue($value);
        return false;
    }

    /**
     * Validator für die Feld-Konfiguration.
     *
     * Das Feld "Search)" ist ein System-Feld, um das GeoPicker-Value in das Suchformular aufzunehmen).
     * Es kann aber dennoch überprüft werden.
     *
     * Konkret wird geprüft, ob die Mindest-Versionen von MySQL bzw. MariaDB installiert sind, ab denen
     * die Spatial-Funktionen integriert sind. Denn darüber ist die Umkreissuche realisiert.
     *
     * @param array<rex_yform_value_abstract> $fields
     */
    protected function validateSearch(string $field_name, ?string $value, bool $return, rex_yform_validate_customfunction $self, array $fields): bool
    {
        $checkVersionResult = self::verifyDbVersion();
        if (is_string($checkVersionResult)) {
            $self->setElement('message', $checkVersionResult);
            return true;
        }
        return false;
    }

    /**
     * Die Methode überprüft die Datenbank-Version. Erst ab der Mindestversion stehen die
     * Spatial-Funktionen zur Verfügung, auf denen die Suche aufbaut.
     *
     * @api
     * @return true|string      True für "DB passt" bzw. die Fehlermeldung mit Details
     */
    public static function verifyDbVersion(): bool|string
    {
        $sql = rex_sql::factory();
        $dbType = $sql->getDbType();
        $dbVersion = $sql->getDbVersion();
        switch ($dbType) {
            case rex_sql::MYSQL : $minVersion = self::MIN_MYSQL;
                break;
            case rex_sql::MARIADB : $minVersion = self::MIN_MARIADB;
                break;
            default: return true;
        }

        if (rex_version::compare($minVersion, $dbVersion, '>')) {
            $label = rex_i18n::msg('yform_useassearchfieldalidatenamenotempty');
            return rex_i18n::msg('geolocation_dbversion_err', $label, $dbType, $dbVersion, $minVersion);
        }
        return true;
    }

    /**
     * Validator für das Suchfeld.
     *
     * Überprüft, ob der "Suchbegriff" formal korrekt aufgebaut ist. Die eigentliche Analyse führt verifySearchTerm durch.
     *
     * NOTE: Dass die Arbeit nach verifySearchTerm verschoben wurde, liegt daran, dass auch bei negativer Überprüfung
     * NOTE: die Query aufgebaut wird. Daher hier die Überprüfung "für das Such-Formular"
     *
     * @api
     * @param array<rex_yform_value_abstract> $fields
     */
    public static function validateSearchTerm(string $field_name, ?string $value, bool $return, rex_yform_validate_customfunction $self, array $fields): bool
    {
        $result = self::verifySearchTerm($value);

        if (0 === $result) {
            return false;
        }

        if (is_array($result)) {
            $value = implode(' ', $result);
            $fields[$field_name]->setValue($value);
            return false;
        }

        return self::setValidatorRawMsg($self, $result);
    }

    /**
     * Q&D wird überprüft, ob der Suchterm OK ist oder nicht.
     * Rückgabe ist ein Array mit den drei Werten oder ein Fehler-Code.
     *
     * 0 = leeres Feld, also keine Filterung
     * 'yxz' = Fehlermeldung
     * [lat,lng,radius] = OK
     *
     * - drei Zahlen
     * - Zahl 1: Dezimalwert für einen Breitengrad (±90)
     * - Zahl 2: Dezimalwert für einen Längengrad (±180)
     * - Zahl 3: Ganzzahl für den Suchradius (ab 10m)
     * - leer ist erlaubt: dann keine Suche
     * 
     * @api
     * @return int|string|array<numeric>
     */
    public static function verifySearchTerm(string $value): int|string|array
    {
        /**
         * Wenn $value leer ist,passiert eh nichts
         * Weitere Überprüfungen sind dann nicht erforderlich.
         */
        $value = trim($value);
        if ('' === $value) {
            return 0;
        }

        /**
         * In drei Teile zerlegen (Blank oder ;)
         * und dann die Einzelteile auf korrekten Wert prüfen.
         */
        $parts = preg_split('@[\s;]+@', $value, flags: PREG_SPLIT_NO_EMPTY);

        if (3 !== count($parts)) {
            return rex_i18n::msg('geolocation_yfv_geopicker_search_err_1');
        }
        $pattern = '@^[+-]?\d+([.,]\d+)?$@';
        $ok = preg_match($pattern, $parts[0], $matches);
        $parts[0] = str_replace(',', '.', $parts[0]);
        if (0 === $ok || (float) $parts[0] < -90 || 90 < (float) $parts[0]) {
            return rex_i18n::msg('geolocation_yfv_geopicker_search_err_2', -90, 90);
        }
        $ok = preg_match($pattern, $parts[1], $matches);
        $parts[1] = str_replace(',', '.', $parts[1]);
        if (0 === $ok || (float) $parts[1] < -180 || 180 < (float) $parts[1]) {
            return rex_i18n::msg('geolocation_yfv_geopicker_search_err_3', -180, 180);
        }
        $pattern = '@^\d+?$@';
        $ok = preg_match($pattern, $parts[2], $matches);
        $parts[2] = str_replace(',', '.', $parts[2]);
        if (0 === $ok || (int) $parts[2] < self::$minMarkerRadius) {
            return rex_i18n::msg('geolocation_yfv_geopicker_search_err_4', self::$minMarkerRadius);
        }

        return $parts;
    }

    /**
     * Hilfsmethode: Ändert die fehlermeldung des Validators situativ.
     *
     * Konkret geht es nur um die diversen Konfigurationsfelder im TableManager.
     * Die Fehlermeldung ist Teil des Validators (hier CustomValidator).
     * Die variable Meldung wird in das Feld Message des Validators eingetragen
     */
    protected static function setValidatorRawMsg(rex_yform_validate_customfunction $field, string $msg): bool
    {
        $field->setElement(
            'message',
            $msg,
        );
        return true;
    }

    /**
     * @param string|int ...$replacements A arbritary number of strings used for interpolating within the resolved message
     */
    protected static function setValidatorMsg(rex_yform_validate_customfunction $field, string $msg, ...$replacements): bool
    {
        return self::setValidatorRawMsg($field, rex_i18n::msg($msg, ...$replacements));
    }

    /**
     * Validiert ein einzelnes Item (Lat oder Lng).
     *
     * Alle zugehörigen Parameter müssen hier angegeben werden:
     *
     * @param mixed $value      der Wert sollte '' oder eine Zahl sein
     * @param float $min        Untergrenze. -90 bei Breitengraden und -180 bei Längengraden
     * @param float $max        Obergrenze. 90 bei Breitengraden und 180 bei Längengraden
     * @param bool $notEmpty    true wenn der Wert nicht leer sein darf
     * @return string           Fehlermeldungs-ID oder '' für ok
     */
    protected static function validateItem(mixed $value, float $min, float $max, bool $notEmpty = false): string
    {
        if ('' === $value) {
            if ($notEmpty) {
                return rex_i18n::msg('geolocation_yfv_geopicker_coord_err_1');
            }
            return '';
        }
        if (!is_numeric($value)) {
            return rex_i18n::msg('geolocation_yfv_geopicker_coord_err_2', $value);
        }
        $value = (float) $value;
        if ($value < $min || $max < $value) {
            return rex_i18n::msg('geolocation_yfv_geopicker_coord_err_3', (string) $min, (string) $max);
        }
        return '';
    }

    /**
     * Ermittelt aus einer Zeichenkette mit den Eckkordinaten eines Kartenauschnitts
     * "[lat,lng],[lat,lng]" die Koordinaten und prüft, ob sie im zulässigen Bereich liegen.
     *
     * Wenn die Zeichenkette nicht dem Muster entspricht oder wenn die Koordinaten nicht
     * innerhalb des zulässigen Wertebereichs liegen, wird eine Exception geworfen
     *
     * aber .... Box kann nur Breiten von max. 180° und wirft andernfalls eine Exception.
     * REVIEW: mal überprüfen, ob wie sich das abfangen lässt.
     * REVIEW: wird die Methode überhaupt benötigt?
     *
     * @api
     */
    public static function decodeBoxBounds(string $value, float $latMin = -90, float $latMax = 90, float $lngMin = -180, float $lngMax = 180): Box
    {
        $bounds = self::decodeBounds($value, $latMin, $latMax, $lngMin, $lngMax);
        return Box::byCorner(
            Point::byLatLng($bounds[0]),
            Point::byLatLng($bounds[1]),
        );
    }

    /**
     * Ermittelt aus einer Zeichenkette mit den Eckkordinaten eines Kartenauschnitts
     * "[lat,lng],[lat,lng]" die Koordinaten und prüft, ob sie im zulässigen Bereich liegen.
     *
     * Wenn die Zeichenkette nicht dem Muster entspricht oder wenn die Koordinaten nicht
     * innerhalb des zulässigen Wertebereichs liegen, wird eine Exception geworfen
     *
     * @api
     * @return array<array<float>>
     */
    public static function decodeBounds(string $value, float $latMin = -90, float $latMax = 90, float $lngMin = -180, float $lngMax = 180): array
    {
        $value = str_replace(' ', '', $value);
        $pattern = '@^\[(?<lat1>[+-]?\d+(\.\d+)?),(?<lng1>[+-]?\d+(\.\d+)?)\],\[(?<lat2>[+-]?\d+(\.\d+)?),(?<lng2>[+-]?\d+(\.\d+)?)\]$@';

        $ok = preg_match($pattern, $value, $match);

        if (0 === $ok) {
            throw new Exception(rex_i18n::msg('geolocation_yfv_geopicker_box_err_1'));
        }

        $match = array_filter($match, is_string(...), ARRAY_FILTER_USE_KEY);
        $match = array_map(floatval(...), $match);

        if ($match['lat1'] < $latMin || $latMax < $match['lat1']
            || $match['lat2'] < $latMin || $latMax < $match['lat2']
            || $match['lng1'] < $lngMin || $lngMax < $match['lng1']
            || $match['lng2'] < $lngMin || $lngMax < $match['lng2']) {
            throw new Exception(rex_i18n::msg('geolocation_yfv_geopicker_box_err_2', (string) $latMin, (string) $latMax, (string) $lngMin, (string) $lngMax));
        }
        // [SW,NO]
        return [
            [min($match['lat1'],$match['lat2']), min($match['lng1'],$match['lng2'])],
            [max($match['lat1'],$match['lat2']), max($match['lng1'],$match['lng2'])],
        ];
    }
}
