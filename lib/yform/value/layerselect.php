<?php
/**
 * YForm-Eingabefeld für Layersets im Geolocation-Addon.
 *
 * Im Prinzip ist es ein be_manager_relation-Feld Typ 3
 *
 * Zusätzlich zum eigentlichen Feld xxxx (id-Liste) wird ein zweites
 * Datenfeld xxxx_selected erwartet, das im Datensatz erwartet wird,
 * aber nicht als YForm-Value in der Tabelle.
 *
 * Für Typ-3-Felder erzeugt be_manager_relation HTML basierend auf
 * dem Fragment "widget-list". Das HTML wird leicht modifiziert und mit
 * Zusatzinformationen bzgl. xxxx_selected versehen. dazu dient das
 * YFragment "value.geolocation_layerselect.tpl.php".
 *
 * Hier kein Namespace, da sonst die Value-Klasse nicht gefunden wird.
 */

use rex_i18n;
use rex_request;
use rex_yform_value_be_manager_relation;

class rex_yform_value_geolocation_layerselect extends rex_yform_value_be_manager_relation
{
    /**
     * Der Name des zusätzlichen DB-Feldes mit den IDs der selektierten
     * Einträge im Hauptfeld.
     * Aufbau: DB-Feldname des Hauptfeldes plus "_selected".
     * Wird in init() gesetzt.
     */
    protected string $subField = '';

    /**
     * Array mit den IDs der Layer, die markiert sind (Feld xxxx_selected).
     * Wird in preValidateAction() gesetzt.
     *
     * @var string[]
     */
    protected array $selectedLayers = [];

    /**
     * In Feldern mit Mehrfachauswahl gelten andere Regeln
     * Wird in init() aus dem Element "format" gefüllt.
     */
    protected bool $isRadioSelect = false;

    /**
     * Dieses Feld ist vom Typ 3, also hier noch mal sicherstellen
     * Alle intern benötigten Variablen zusammenstellen.
     *
     * @return void
     */
    public function init()
    {
        $this->setElement('type', 3); // 'Multiple (popup)'
        $this->setElement('relation_table', '');

        parent::init();

        $this->subField = $this->getName() . '_selected';
        $this->isRadioSelect = 'radio' === $this->getElement('format');
    }

    /**
     * Das YFragment arbeitet mit einem input-Name, der sowohl die
     * Liste der Layer-Ids als auch der markierten Layer als ein
     * übergreifendes Array darstellt.
     *
     * feldname[value][]    IDs der insgesamt selektuerten Layer
     * feldname[choice][]   Subset aus Value: IDs der markierten Layer.
     *
     * Je nach Situation wird entweder die Formularrückgabe in $selectedLayers
     * und den re_manager_relation-Value aufgesplittet oder zusätzlich
     * $selectedLayers aus der DB bzw. leer initialisiert.
     */
    public function preValidateAction(): void
    {
        $value = $this->getValue();
        
        /**
         * aus der Formular-Erfasung (add/edit) kommt ein Array
         * feldname[value][]    IDs der insgesamt selektuerten Layer
         * feldname[choice][]   Subset aus Value: IDs der markierten Layer.
         *
         * Alternativ könnten die Werte auch aus dem Datensatz stammen.
         *
         * Fallback (neuer Datensatz): leeres Array
         */
        if ($this->params['send']) {
            if( null === $value ) {
                $selectedLayers = [];
                $value = [];
            } else {
                $selectedLayers = $value['choice'];
                $value = $value['value'];
                $this->setValue($value);
            }
        } elseif (0 < $this->params['main_id']) {
            $selectedLayers = $this->getParam('manager_dataset')->getValue($this->subField);
        } else {
            $selectedLayers = [];
            $value = [];
        }

        if (is_string($value)) {
            $value = explode(',', $value);
        }

        if (is_string($selectedLayers)) {
            $selectedLayers = explode(',', $selectedLayers);
        }

        $this->selectedLayers = array_map('trim', $selectedLayers);
        $this->selectedLayers = array_filter($this->selectedLayers, 'strlen');
        $this->selectedLayers = array_unique($this->selectedLayers);

        // nur Elemente auswählbar, die auch im Hauptfeld vorkommen
        $this->selectedLayers = array_intersect($this->selectedLayers, $value);

        // Bei Radio-Auswahl: wenn kein Feld selektiert => das erste auswählen
        if ($this->isRadioSelect && 0 === count($this->selectedLayers) && 0 < count($value)) {
            $this->selectedLayers[] = reset($value);
        }

        // normale Verarbeitung
        parent::preValidateAction();
    }

    /**
     * Hauptverarbeitung: Das Feld wird regulär über die Parent-Methode von be_manager_relation
     * generiert. Der Output wird im Fragment dieses Values überarbeitet.
     * Die Teile ab Anfang inkl. Label bzw. ab Notice bleiben (leadIn, leadOut)
     * Aus dem inneren Teil werden ein paar Angaben extrahiert:
     *  - die verfügbaren Optionen (id,name)
     *  - Parameter für den Popup-Aufruf (selectId, PopupButton)
     * Dazu kommen Informationen zur Struktur des Eingabefeldes.
     *
     * @return void
     */
    public function enterObject()
    {
        /**
         * zunächst be_manager_relaton das Feld normal erzeugen lassen.
         */
        parent::enterObject();

        /**
         * Das erzeugte HTML umarbeiten:
         *  - Den inneren Block und die Teile davor und danach separieren
         *  - Die vorhandenen Optionen im Select extrahieren
         *  - Die Parameter für das YForm-Popup extrahieren.
         */
        if ($this->needsOutput()) {
            $html = $this->params['form_output'][$this->getId()];
            preg_match('/(?<leadIn>.*?<\\/label>)\\s*(?<inner><div.*<\\/div>)\\s*(?<leadOut>(<p.*?<\\/p>\\s*)?<\\/div>)/ms', $html, $matchStructure);
            preg_match_all('/<option.*?value="(?<id>.*?)".*?\>(?<name>.*?)<\\/option>/ms', $matchStructure['inner'], $matchOptions, PREG_SET_ORDER);
            preg_match('/(<a href).*?openYFormDatasetList\((?<id>.*?),.*?<\\/a>/', $matchStructure['inner'], $matchButton);

            $params = [
                'leadIn' => trim($matchStructure['leadIn']),
                'leadOut' => trim($matchStructure['leadOut']),
                'options' => array_map(static function ($option) {
                    return array_filter($option, 'is_string', ARRAY_FILTER_USE_KEY);
                }, $matchOptions),
                'popupButton' => $popupButton = '<button type="button"' . substr($matchButton[0], 2, -2) . 'button>',
                'selectId' => $matchButton['id'],
                'valueInput' => $this->getFieldName('value'),
                'choiceInput' => $this->getFieldName('choice'),
                'choiceType' => $this->isRadioSelect ? 'radio' : 'checkbox',
            ];

            foreach ($params['options'] as &$option) {
                $option['checked'] = in_array($option['id'], $this->selectedLayers, true) ? 'checked' : '';
            }

            $this->params['form_output'][$this->getId()] = $this->parse('value.layerselect.tpl.php', $params);
        }

        /**
         * Daten speichern wie in anderen Values auch.
         */
        $selectedLayers = array_intersect($this->selectedLayers, $this->getValue());
        $this->params['value_pool']['email'][$this->subField] = implode(',', $selectedLayers);
        if ($this->saveInDB()) {
            $this->params['value_pool']['sql'][$this->subField] = implode(',', $selectedLayers);
        }
    }

    /**
     * Individualisierte Definitions
     * alles raus, was nicht nötig ist bzw. hidden setzen oder anpassen.
     *
     * @return array<string,mixed>
     */
    public function getDefinitions(): array
    {
        $definitions = parent::getDefinitions();
        $definitions['name'] = 'geolocation_layerselect';
        $definitions['description'] = rex_i18n::msg('yform_values_be_manager_relation_description');
        $definitions['db_type'] = ['text', 'varchar(191)', 'int'];
        $definitions['values']['format'] = ['type' => 'choice',  'label' => rex_i18n::msg('yform_values_be_manager_relation_type'), 'default' => 'checkbox', 'choices' => ['checkbox' => 'Multiple (Checkboxen)', 'radio' => 'Einfach (Radio-Button)']];
        $definitions['manager'] = rex_request::request('table_name', 'string', '') === rex::getTable('geolocation_mapset');
        $definitions['values']['type']['type'] = 'hidden';
        $definitions['values']['type']['default'] = '3';
        $definitions['values']['relation_table']['type'] = 'hidden';
        return $definitions;
    }
}
