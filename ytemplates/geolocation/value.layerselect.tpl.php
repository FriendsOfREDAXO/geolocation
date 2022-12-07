<?php
/**
 * yform-Fragment für das Geolocation-Value rex_yform_value_geolocation_layerselect.
 *
 * benötigt aus geolocation_be.js die CustomHTML-Elemente
 *  geolocation-trigger
 *  geolocation-layerselect
 *  geolocation-layerselect-item
 */

namespace Geolocation;

use rex_i18n;
use rex_yform_value_geolocation_layerselect;
use rex_request;

/**
 * rex_yform_value-Instanz und die daraus gelieferten Daten.
 *
 * @var rex_yform_value_geolocation_layerselect $this
 * @var string $leadIn
 * @var string $leadOut
 * @var string $choiceInput
 * @var string $valueInput
 * @var string $choiceType
 * @var array $options
 * @var string $selectId
 * @var string $popupButton
 */

/**
 * Für die Elemente in der Hauptliste wird ein Template erstellt.
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
 * Die List-Group mit den aktuellen Elementen aufbauen
 * Basis ist das $template.
 */
$layerEntries = '';
foreach ($options as $option) {
    $layerEntries .= str_replace(['{value}', '{label}', '{checked}'], [$option['id'], $option['name'], $option['checked']], $template);
}

echo $leadIn;
?>
<geolocation-layerselect class="input-group" template="<?= rex_escape($template)?>">
    <div class="form-control">
        <p><?= rex_i18n::msg('geolocation_yfv_layerselect_empty') ?></p>
        <div class="list-group"><?= $layerEntries ?></div>
    </div>
    <span class="input-group-addon">
        <div class="btn-group-vertical"><?= $popupButton ?></div>
    </span>
    <select id="YFORM_DATASETLIST_SELECT_<?= $selectId ?>"></select>
</geolocation-layerselect>
<?php
echo $leadOut;
