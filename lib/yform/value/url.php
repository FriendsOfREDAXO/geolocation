<?php
/**
 * YForm-Eingabefeld zum Testen von Urls im Geolocation-Addon.
 *
 * Im Prinzip ist es ein Text-Feld; der nötige Button wird über das
 * Element "append" eingefügt. Der Button selbst ist kein Button-Tag,
 * sondern ein CustomHTML, das die gesamte Test-Logik kapselt.
 *
 * Es gibt kein eigenes YFragment, da das Text-YFragment per Saldo ausreicht.
 *
 * Hier kein Namespace, da sonst die Value-Klasse nicht gefunden wird.
 */

use rex;
use rex_api_geolocation_testurl;
use rex_fragment;
use rex_i18n;
use rex_request;
use rex_string;

class rex_yform_value_geolocation_url extends rex_yform_value_text
{
    /**
     * @return void
     */
    public function enterObject()
    {
        /**
         * Attribute für den Button zusammenstellen
         */
        $fragment = new rex_fragment();
        $fragment->setVar('id', $this->getHTMLId('modal'), false);
        $fragment->setVar('title', rex_i18n::msg('geolocation_testurl_title'));

        $attr = [
            'class' => 'btn btn-info',
            'api' => json_encode(rex_api_geolocation_testurl::getApiParams(rex_api_geolocation_testurl::TILE_URL)),
            'subdomain' => '',
            'url' => $this->getFieldId(),
            'modal' => rex_escape($fragment->parse('geolocation_modal.php')),
        ];

        /**
         * Die ID des Subdomain-Feldes ermitteln und in die Attribute eintragen
         */
        $subdomainField = $this->getElement('field');
        $valueFields = $this->getParam('values');
        foreach ($valueFields as $field) {
            if ($subdomainField === $field->getName()) {
                $attr['subdomain'] = $field->getFieldId();
                break;
            }
        }

        /**
         * "append" mit dem Code für den Test-Button belegen.
         */
        $append = '<geolocation-test-tile-url '.rex_string::buildAttributes($attr).'>'.rex_i18n::msg('geolocation_testurl_label').'</geolocation-test-tile-url>';
        $this->setElement('append', $append);

        /**
         * Text-Object klassisch zusammenbauen
         * Damit "append" (der Button) richtig dargestellt wird: input-group-addon in input-group-btn ändern.
         */
        parent::enterObject();
        $this->params['form_output'][$this->getId()] = str_replace('"input-group-addon"', '"input-group-btn"', $this->params['form_output'][$this->getId()]);
    }

    /**
     * Erzeugt den Description-String für diesen Feldtyp (Kurzform).
     *
     * @return string Short-Description
     */
    public function getDescription(): string
    {
        return 'geolocation_url|name|label|[default]|[notice]|'.rex_i18n::msg('geolocation_yfv_url_subdomain').'|';
    }

    /**
     * Felddefinitionen für das Konfigurations-Formular.
     *
     * Das Feld ist nur für die Tabelle rex_geolocation_layer im
     * Table-Manager sichtbar ($definitions['manager'] = ...).
     *
     * (so) nicht benötigte Felder im Parent-Typ "text" werden entfernt bzw. geändert
     *
     * @return array<string,mixed>   Feldfefinitionen
     */
    public function getDefinitions(): array
    {
        $definitions = parent::getDefinitions();
        $definitions['name'] = 'geolocation_url';
        $definitions['description'] = rex_i18n::msg('geolocation_yfv_url_description');
        $definitions['is_searchable'] = false;
        $definitions['famous'] = false;
        $definitions['manager'] = rex_request::request('table_name', 'string', '') === rex::getTable('geolocation_layer');
        $definitions['values']['field'] = ['type' => 'select_name', 'label' => rex_i18n::msg('geolocation_yfv_url_subdomain')];
        unset($definitions['hooks']);
        unset($definitions['values']['no_db']);
        unset($definitions['values']['prepend']);
        unset($definitions['values']['append']);
        return $definitions;
    }

}
