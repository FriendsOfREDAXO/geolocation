<?php

/**
 * baut das HTML für das Geolocation-Value rex_yform_value_geolocation_layerselect
 * zusammen.
 *
 * Parameter aus dem Value:
 * @var string $valueInput          FORM[data_edit-rex_geolocation_mapset][3][value][]
 * @var string $choiceInput         FORM[data_edit-rex_geolocation_mapset][3][choice][]
 * @var string $choiceType          checkbox|radio
 * @var array<string> $options      [id=>label,...]
 * @var array<int> $selected        [id,..]
 * @var array<string> $linkParams   Elemente des Links zum Popup zur Auswahl der Layer
 *
 * Die Auswahl neuer Layer erfolgt durch ein be_manager_relation-Popup ($link).
 * Das Fenster hinterlegt die ausgewählten Daten in einem SELECT-Tag, der hier
 * als hidden-Element zur Verfügung gestellt wird. wird. Per JS werden die
 * Änderungen im Select in die .list-group übertragen.
 *
 * Das Value an sich:
 * @var rex_yform_value_geolocation_layerselect $this
 */

use FriendsOfRedaxo\Geolocation\Layer;

$notices = [];
if ('' != $this->getElement('notice')) {
    $notices[] = rex_i18n::translate($this->getElement('notice'), false);
}
if (isset($this->params['warning_messages'][$this->getId()]) && !$this->params['hide_field_warning_messages']) {
    $notices[] = '<span class="text-warning">' . rex_i18n::translate($this->params['warning_messages'][$this->getId()], false) . '</span>';
}

$notice = '';
if (count($notices) > 0) {
    $notice = '<p class="help-block">' . implode('<br />', $notices) . '</p>';
}

$class = $this->getElement('required') ? 'form-is-required ' : '';

if ('' != trim($this->getLabel())) {
    $labelAttributes = [
        'class' => 'control-label',
        'for' => $this->getFieldId(),
    ];

    $containerAttributes = [
        'class' => trim('form-group formgeolocation_layerselect ' . $class . $this->getWarningClass()),
        'id' => $this->getHTMLId(),
        'data-be-relation-wrapper' => $this->getFieldName(),
    ];

    echo '<div',rex_string::buildAttributes($containerAttributes),'>
    <label',rex_string::buildAttributes($labelAttributes),'>' . $this->getLabel() . '</label>';
}

/**
 * Für die Elemente in der Auswahlliste wird ein Template erstellt.
 */
$template = <<<HTML
    <geolocation-layerselect-item type="button" class="list-group-item" tabindex="0">
    <input type="$choiceType" name="{$choiceInput}[]" value="{value}" {checked} tabindex="-1" />
    <input type="hidden" name="{$valueInput}[]" value="{value}" />
    {label}
    <div class="btn-group btn-group-xs pull-right" role="group">
    <gelocation-trigger class="btn btn-default" event="geolocation:layerselect.down"><i class="rex-icon rex-icon-down"></i></gelocation-trigger>
    <gelocation-trigger class="btn btn-default" event="geolocation:layerselect.up"><i class="rex-icon rex-icon-up"></i></gelocation-trigger>
    <gelocation-trigger class="btn btn-default" event="geolocation:layerselect.delete"><i class="rex-icon rex-icon-package-not-activated"></i></gelocation-trigger>
    </div>
    </geolocation-layerselect-item>
    HTML;

/**
 * Für die vorhandenen Elemente (Options) werden direkt die Einträge
 * der Auswahlliste erzeugt.
 */
$layerEntries = '';
foreach ($options as $id => $option) {
    $checked = in_array($id, $selected) ? 'checked' : '';
    $layerEntries .= str_replace(['{value}', '{label}', '{checked}'], [$id, $option, $checked], $template);
}

/**
 * Attribute für den Rahmen.
 */
$selectAttributes = [
    'class' => 'input-group',
    'template' => $template,
];

/**
 * Url zum Abruf der Layerliste / Auswahl weiterer Layer
 * Hierfür wird eine URL generiert, die die Popup-Logik des Felddtyps
 * be_manager_relation auslöst.
 */
$bmrLink = rex_url::backendController($linkParams, false);
$bmrId = $linkParams['rex_yform_manager_opener[id]'];

/**
 * ID-String für den Select abhängig von der YForm-Version
 * ab YForm 4.2.0 ist es: yform-dataset-view-
 * 
 * NOTICE: kann entfallen wenn irgendwann die Yform-Mindestversion ab 4.2 ist
 */
$selectId = rex_version::compare(rex_addon::get('yform')->getVersion(),'4.2.0','<')
    ? 'YFORM_DATASETLIST_SELECT_'
    : 'yform-dataset-view-';

?>
<geolocation-layerselect <?= rex_string::buildAttributes($selectAttributes) ?>>
    <div class="form-control">
        <p><?= rex_i18n::msg('geolocation_yfv_layerselect_empty') ?></p>
        <div class="list-group">
            <?= $layerEntries ?>
        </div>
    </div>
    <span class="input-group-addon">
        <div class="btn-group-vertical">
            <gelocation-trigger class="btn btn-default" event="geolocation:layerselect.add" detail="<?= rex_escape($bmrLink) ?>"><i class="rex-icon rex-icon-add"></i></gelocation-trigger>
        </div>
    </span>
    <select class="hidden" id="<?= $selectId ?><?= $bmrId ?>"></select>
</geolocation-layerselect>
<?php

echo $notice;

if ('' != trim($this->getLabel())) {
    echo '</div>';
}
