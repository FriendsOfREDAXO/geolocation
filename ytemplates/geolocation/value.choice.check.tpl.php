<?php
/*
    Vorgeschaltetes YTemplate für Geolocation
    -  der erste Auswahlpunkt (preferredChoices) stellt die Auswahl auf  "default" und damit den
       die Anwendung der Werte aus den Grundeinstellungen.
    -  Die übrigen Auswahlpunkte (choices) sind die Einzelauswahlmöglichkeiten.

    Die Default-Auswahl erhält einen onchange-Hhndler, mit dem die übrigen Felder disabled/enabled
    werden.
    Die übrigen Felder werden auf disabled gesetzt, wenn "default" die aktuelle Auswahl ist.
    Außerdem, um von JS gefunden zu werden, erhalten sie das Attribut "subchoice".
    Aus optischen erklärenden Gründen erhalten die LAbel der aktuelle in den Grundeinstellungen
    ausgewählten Optionen ein "(*)".

    Konkret werden die Elemente in $choiceListView durch modifizierte ersetzt.
    Den Feldaufbau übernimmt dann wieder das Original-YTemplate.

    Die Umleitung auf dieses Template erfolgt auschließlich für geolocation_mapset::
    Nutzung nur für das Feld "mapoptions"
*/

if( 'mapoptions' === $this->name ) {
    // Finde das rex_yform_choice_view für "default" (preferredChoices)
    // baue die OnChange-Funktion ein.
    $default = $choiceListView->preferredChoices[0];
    $attributes = [
        'onchange' =>
        'let t=document.getElementById("'.$this->getHTMLId().'");if(t)Array.from(t.querySelectorAll(".checkbox input[subchoice]")).forEach(x=>{x.disabled=this.checked});'
    ];
    $attributes = array_merge( $attributes,$default->getAttributes() );
    $choiceListView->preferredChoices[0] = new \rex_yform_choice_view( $default->getValue(), $default->getLabel(), $attributes );

    // Finde die übrigen Choices und setze sie auf disabled wenn "default" aktiviert ist
    $disabled = false !== array_search('default',$this->value);
    $options = \rex_config::get(\Geolocation\ADDON,'map_components');
    $options = explode('|',$options);
    $options = array_filter($options,'strlen');
    foreach( $choiceListView->choices as &$v ){
        $value = $v->getValue();
        $attributes = $v->getAttributes();
        $attributes['subchoice'] = '';
        if( $disabled ) $attributes['disabled'] = '';
        $label = $v->getLabel() . (false !== array_search($value,$options) ? ' (*)' : '');
        $v = new \rex_yform_choice_view( $value, $label, $attributes );
    }
}

// ab hier das Original
include \rex_path::addon('yform','ytemplates/bootstrap/value.choice.check.tpl.php');
