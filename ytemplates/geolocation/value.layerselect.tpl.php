<?php

/**
 * baut das HTML für das Geolocation-Value rex_yform_value_geolocation_layerselect
 * zusammen.
 *
 * Parameter aus dem Value:
 * @var string $valueInput      FORM[data_edit-rex_geolocation_mapset][3][value][]
 * @var string $choiceInput     FORM[data_edit-rex_geolocation_mapset][3][choice][]
 * @var string $choiceType      checkbox|radio
 * @var array<string> $options  [id=>label,...]
 * @var array<int> $selected    [id,..]
 * @var string $link            Link für das Popup zur Auswahl der Layer
 * @var string $dataField       Paramter für das Popup
 * 
 * Die Auswahl neuer Layer erfolgt durch ein be_manager_relation-Popup ($link).
 * Das Fenster hinterlegt die ausgewählten Daten in einem SELECT-Tag, der hier
 * als hidden-Element zur Verfügung gestellt wird. wird. Per JS werden die
 * Änderungen im Select in die .list-group übertragen.
 *
 * Das Value an sich:
 * @var rex_yform_value_geolocation_layerselect $this
 */

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
$bmrId = random_int(10000000, 99999999);
$selectAttributes = [
    'class' => 'input-group yform-dataset-widget',
    'template' => $template,
    'data-widget_type' => 'multiple',
    'data-id' => $bmrId,
    'data-link' => $link,
    'data-field_name' => $dataField,
];

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
            <a class="btn btn-popup yform-dataset-widget-open" title="<?= rex_i18n::msg('yform_relation_choose_entry') ?>"><i class="rex-icon rex-icon-add"></i></a>
        </div>
    </span>
    <select class="hidden" id="yform-dataset-view-<?= $bmrId ?>"></select>
</geolocation-layerselect>
<?php

echo $notice;

if ('' != trim($this->getLabel())) {
    echo '</div>';
}
