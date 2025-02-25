<?php

/**
 * YForm-Eingabefeld für Layer-Sets im Geolocation-Addon.
 *
 * Zusätzlich zum eigentlichen Feld xxxx (id-Liste) wird ein zweites
 * Datenfeld xxxx_selected erwartet, das im Datensatz vorkommt,
 * aber nicht als YForm-Value in der Tabelle.
 *
 * Hier kein Namespace, da andernfalls diese Value-Klasse nicht gefunden wird.
 */

use FriendsOfRedaxo\Geolocation\Layer;
use FriendsOfRedaxo\Geolocation\Mapset;
use rex_i18n;

class rex_yform_value_geolocation_layerselect extends rex_yform_value_abstract
{
    /**
     * Das zweite Feld, das hier gleich mitverwaltet wird, ist die Liste
     * der ID(s) (Checkbox, optional bzw. die Option, mandatory), die
     * als aktiv markiert sind. Darf nur ID(s) enthalten, die auch im
     * Value vorkommen.
     */
    protected string $subField;

    /**
     * Das eigene value-Feld der DB ist eine Komma-Liste mit Layer-IDs
     * wird hier für interne Zwecke als Array geführt.
     * @api
     * @var array<int>
     */
    public array $layer;

    /**
     * Das SubFeld in der DB ist eine Komma-Liste mit Layer-IDs
     * wird hier für interne Zwecke als Array geführt.
     * @api
     * @var array<int>
     */
    public array $activeLayer;

    /**
     * In Feldern mit Mehrfachauswahl gelten andere Regeln
     * Wird in init() aus dem Element "format" gefüllt.
     */
    protected bool $isRadioSelect = false;

    /**
     * Alle intern benötigten Variablen zusammenstellen.
     *
     * @return void
     */
    public function init()
    {
        parent::init();
        $this->subField = $this->getName() . '_selected';
        $this->isRadioSelect = 'radio' === $this->getElement('format');
    }

    /**
     * Erste Methode, in der die Daten im Value zur Verfügung stehen.
     * Aufbereiten zu folgenden Formaten:
     * - value selbst immer als Array ['value' => [...], 'choice' => [...]]
     * - Außerdem die SubArrays nach $this->layer und $this->activeLayer.
     */
    public function preValidateAction(): void
    {
        $value = $this->getValue();

        /**
         * Rückgabe aus dem Formular sollte immer ein Array sein.
         * Sicherstellen, dass die beiden Sub-Arrays vorkommen und
         * zumindest numerischen Inhalt haben.
         */
        if (is_array($value)) {
            $value = array_merge(
                ['value' => [], 'choice' => []],
                $value,
            );
            $this->layer = array_filter($value['value'], is_numeric(...));
            $this->activeLayer = array_filter($value['choice'], is_numeric(...));
        }

        /**
         * Wenn aus dem Bestand ausgelesen, wird es ein String sein (1,2,..).
         * Der Value betrifft nur die Layer-Liste. Die aktiven Layer der Liste
         * stehen im SubFeld und werden von dort ausgelesen.
         * Beide in ein Array umwandeln.
         */
        elseif (is_string($value)) {
            $this->layer = array_filter(
                $this->getArrayFromString($value),
                static function ($k) {return '' !== trim($k); },
                ARRAY_FILTER_USE_KEY,
            );
            $subValue = $this->getValueForKey($this->subField) ?? '';
            $this->activeLayer = array_filter(
                $this->getArrayFromString($subValue),
                static function ($k) {return '' !== trim($k); },
                ARRAY_FILTER_USE_KEY,
            );
        }

        /**
         * In allen anderen Fällen (kann eigentlich nur "neuer Satz sein und
         * damit null als Wert) wird eben "leer" angenommen.
         */
        else {
            $this->layer = [];
            $this->activeLayer = [];
        }

        /**
         * Sicherstellen, dass in activeLayer nur IDs stehen, die auch in der
         * Layer-Liste vorkommen
         * Im Fall der Basis-Layer muss mindestens ein Layer aktiviert sein
         * (notfalls der erste der Liste).
         */
        $this->activeLayer = array_intersect($this->layer, $this->activeLayer);
        if ($this->isRadioSelect && 0 < count($this->layer) && 0 === count($this->activeLayer)) {
            $this->activeLayer = array_slice($this->layer, 0, 1);
        }

        /**
         * Normierte Darstellung des Value in allen folgenden Schritten analog
         * zum Rückgabewert aus Formularen.
         */
        $this->setValue([
            'value' => $this->layer,
            'choice' => $this->activeLayer,
        ]);
    }

    public function enterObject(): void
    {
        /**
         * Formularausgabe.
         */
        if ($this->needsOutput()) {
            $linkParams = [
                'page' => 'yform/manager/data_edit',
                'table_name' => Layer::table()->getTableName(),
                'rex_yform_filter[layertype]' => $this->getElement('filter'),
                'rex_yform_set[layertype]' => $this->getElement('filter'),
                'rex_yform_manager_opener[field]' => sprintf('%s.%s', $this->params['main_table'], $this->getName()),
                'rex_yform_manager_opener[multiple]' => 1,
                'rex_yform_manager_opener[id]' => random_int(10000000, 99999999),
            ];

            $params = [
                'valueInput' => $this->getFieldName('value'),
                'choiceInput' => $this->getFieldName('choice'),
                'choiceType' => $this->isRadioSelect ? 'radio' : 'checkbox',
                'options' => self::getLayerList($this->layer),
                'selected' => $this->activeLayer,
                'linkParams' => $linkParams,
            ];

            $this->params['form_output'][$this->getId()] = $this->parse('value.layerselect.tpl.php', $params);
        }

        /**
         * Speichern.
         */
        $this->params['value_pool']['email'][$this->getName()] = implode(',', $this->layer);
        $this->params['value_pool']['email'][$this->subField] = implode(',', $this->activeLayer);

        if ($this->saveInDB()) {
            $this->params['value_pool']['sql'][$this->getName()] = implode(',', $this->layer);
            $this->params['value_pool']['sql'][$this->subField] = implode(',', $this->activeLayer);
        }
    }

    /**
     * Definitions.
     *
     * @return array<string,mixed>
     */
    public function getDefinitions(): array
    {
        return [
            'type' => 'value',
            'name' => 'geolocation_layerselect',
            'description' => rex_i18n::msg('geolocation_yfv_layserselect_description'),
            'values' => [
                'name' => [
                    'type' => 'name',
                    'label' => rex_i18n::msg('yform_values_defaults_name'),
                ],
                'label' => [
                    'type' => 'text',
                    'label' => rex_i18n::msg('yform_values_defaults_label'),
                ],
                'format' => [
                    'type' => 'choice',
                    'label' => rex_i18n::msg('geolocation_yfv_layserselect_format'),
                    'default' => 'checkbox',
                    'choices' => [
                        'checkbox' => rex_i18n::msg('geolocation_yfv_layserselect_format_ckeckbox'),
                        'radio' => rex_i18n::msg('geolocation_yfv_layserselect_format_radio'),
                    ],
                ],
                'filter' => [
                    'type' => 'choice',
                    'label' => rex_i18n::msg('geolocation_layer_type'),
                    'default' => 'b',
                    'choices' => [
                        'b' => rex_i18n::msg('geolocation_layer_type_choice_b'),
                        'o' => rex_i18n::msg('geolocation_layer_type_choice_o'),
                    ],
                ],
                'attributes' => [
                    'type' => 'text',
                    'label' => rex_i18n::msg('yform_values_defaults_attributes'),
                    'notice' => rex_i18n::msg('yform_values_defaults_attributes_notice'),
                ],
                'notice' => [
                    'type' => 'text',
                    'label' => rex_i18n::msg('yform_values_defaults_notice'),
                ],
                // Hier versteckte Felder, die die fixen Parameter für den
                // Popup-Aufruf á la be_manager_relation eintragen.
                'html1' => [
                    'type' => 'html',
                    'html' => '<div class="hidden">',
                ],
                'table' => [
                    'type' => 'text',
                    'attributes' => sprintf('{"value":"%s"}', Layer::table()->getTableName()),
                ],
                'field' => [
                    'type' => 'text',
                    'attributes' => '{"value":"name"}',
                ],
                'type' => [
                    'type' => 'text',
                    'attributes' => '{"value":"3"}',
                ],
                'html2' => [
                    'type' => 'html',
                    'html' => '</div>',
                ],
            ],
            'db_type' => ['varchar(191)'],
            'famous' => false,
            // Der Feldtyp ist sehr Geolocation-speziell. Im YForm-Manager nicht allgemein zur Auswahl anbieten
            'manager' => rex_request::request('table_name', 'string', '') === Mapset::table()->getTableName(),
        ];
    }

    public function getDescription(): string
    {
        return 'geolocation_layerselect|name|label|radio/checkbox|b/o|[attributes]|[notice]|';
    }

    /**
     * Aus den gegebenen Layer-IDs ein Array id => label zusammenbauen.
     *
     * @api
     * @param array<int> $list
     * @return array<string>
     */
    public static function getLayerList(array $list = []): array
    {
        $result = [];
        foreach (self::getLayerListItems($list) as $layer) {
            $result[$layer->getId()] = sprintf(
                '%s [ID=%d]',
                $layer->name,
                $layer->getId(),
            );
        }
        return $result;
    }

    /**
     * Aus den gegebenen Layer-IDs die zugehörige Datensatz-Liste abrufen.
     *
     * @api
     * @param array<int> $list
     * @return array<Layer>
     */
    public static function getLayerListItems(array $list = []): array
    {
        $list = array_filter($list, strlen(...));
        if (0 === count($list)) {
            return [];
        }

        // In der Reihenfolder der IDs
        $order = sprintf('FIELD(`id`,%s)', implode(',', $list));
        // "online" als Text
        $online = sprintf(
            'IF(`online`=1,"%s","%s")',
            rex_i18n::msg('geolocation_form_active_choice_1'),
            rex_i18n::msg('geolocation_form_active_choice_0'),
        );
        $data = Layer::query()
            ->selectRaw($online, 'status')
            ->orderByRaw($order)
            ->findIds($list);

        return $data->toArray();
    }

    /**
     * @api
     * @param mixed $params
     */
    public static function getListValue($params): string
    {
        $layer = array_filter(
            explode(',', (string) $params['subject']),
            static function ($k) {return '' !== trim($k); },
            ARRAY_FILTER_USE_KEY,
        );
        $options = [];
        foreach (self::getLayerListItems($layer) as $item) {
            $template = $item->isOnline() ? '<span title="%s">%s</span>' : '<s class="text-muted" title="%s">%s</s>';
            $options[] = sprintf(
                $template,
                rex_escape($item->status),
                rex_escape($item->name),
            );
        }
        return implode('<br />', $options);
    }

    /**
     * Baut die Suchfelder auf
     * In Anlehnung an be_manager_relation.
     *
     * @api
     * @param mixed $params
     * @return void
     */
    public static function getSearchField($params)
    {
        /** @var rex_yform $searchForm */
        $searchForm = $params['searchForm'];
        /** @var rex_yform_manager_field $field */
        $field = $params['field'];
        $searchForm->setValueField('be_manager_relation', [
            'name' => $field->getName(),
            'label' => $field->getLabel(),
            'empty_option' => true,
            'table' => Layer::table()->getTableName(),
            'field' => 'name',
            'filter' => 'layertype = ' . $field->getElement('filter'),
            'type' => 2,
        ]);
    }

    /**
     * Baut den Inhalt des Suchfeldes in die Query ein.
     * Hier kann komplett auf den Code aus be_manager_relation zurückgegriffen
     * werden.
     *
     * @api
     * @param mixed $params
     * @return rex_yform_manager_query
     */
    public static function getSearchFilter($params)
    {
        return rex_yform_value_be_manager_relation::getSearchFilter($params);
    }
}
