<?php
/**
 * Vorgeschaltetes YTemplate für Geolocation
 *  -  der erste Auswahlpunkt (preferredChoices) stellt die Auswahl auf  "default" und damit den
 *      die Anwendung der Werte aus den Grundeinstellungen.
 *   -  Die übrigen Auswahlpunkte (choices) sind die Einzelauswahlmöglichkeiten.
 *
 * Die Default-Auswahl erhält einen onchange-Hhndler, mit dem die übrigen Felder disabled/enabled
 * werden.
 * Die übrigen Felder werden auf disabled gesetzt, wenn "default" die aktuelle Auswahl ist.
 * Außerdem, um von JS gefunden zu werden, erhalten sie das Attribut "subchoice".
 * Aus optischen erklärenden Gründen erhalten die LAbel der aktuelle in den Grundeinstellungen
 * ausgewählten Optionen ein "(*)".
 *
 * Konkret werden die Elemente in $choiceListView durch modifizierte ersetzt.
 * Den Feldaufbau übernimmt dann wieder das Original-YTemplate.
 *
 * Die Umleitung auf dieses Template erfolgt auschließlich für geolocation_mapset::
 * Nutzung nur für das Feld "mapoptions"
 */

namespace FriendsOfRedaxo\Geolocation;

use rex_config;
use rex_path;
use rex_yform_choice_list_view;
use rex_yform_choice_view;
use rex_yform_value_choice;

/**
 * @var rex_yform_value_choice $this
 * @var rex_yform_choice_list_view $choiceListView
 * @var string $template
 */

if ('mapoptions' === $this->name) {
    // Finde das rex_yform_choice_view für "default" (preferredChoices)
    // baue die OnChange-Funktion ein.
    $default = $choiceListView->preferredChoices[0];
    $attributes = [
        'onchange' => 'let t=document.getElementById("'.$this->getHTMLId().'");if(t)Array.from(t.querySelectorAll(".checkbox input[subchoice]")).forEach(x=>{x.disabled=this.checked});',
    ];
    $attributes = array_merge($attributes, $default->getAttributes());
    /**
     * STAN: Parameter #3 $attributes of class rex_yform_choice_view constructor expects (callable(): mixed)|string|null, non-empty-array given.
     * Der Fehler ist keiner, denn die Klasse akzeptiert auch array. Allerdings fehlt im PHPDOC array als Variante
     * https://github.com/yakamara/redaxo_yform/pull/1308
     * Betrifft YForm 4.0.0 bis 4.0.2.
     */
    $choiceListView->preferredChoices[0] = new rex_yform_choice_view($default->getValue(), $default->getLabel(), $attributes);

    // Finde die übrigen Choices und setze sie auf disabled wenn "default" aktiviert ist
    $disabled = false !== array_search('default', $this->value, true);
    $options = rex_config::get(ADDON, 'map_components');
    $options = explode('|', $options);
    $options = array_filter($options, 'strlen');
    foreach ($choiceListView->choices as &$v) {
        $value = $v->getValue();
        $attributes = $v->getAttributes();
        $attributes['subchoice'] = '';
        if ($disabled) {
            $attributes['disabled'] = '';
        }
        $label = $v->getLabel() . (false !== array_search($value, $options, true) ? ' (*)' : '');
        $v = new rex_yform_choice_view($value, $label, $attributes);
    }
}

// ab hier das Original-Template abrufen und dahin weiterleiten
$form_ytemplate = $this->params['form_ytemplate'];
$ytemplates = explode(',', $form_ytemplate);
$geoPos = array_search('geolocation', $ytemplates, true);

$previousTemplate = '';
if (false !== $geoPos) {
    unset($ytemplates[$geoPos]);
    $this->params['form_ytemplate'] = implode(',', $ytemplates);
    $previousTemplate = $this->params['this']->getTemplatePath($template);
}
if ('' === $previousTemplate) {
    $previousTemplate = rex_path::addon('yform', 'ytemplates/bootstrap/value.choice.check.tpl.php');
}
$this->params['form_ytemplate'] = $form_ytemplate;
include $previousTemplate;
