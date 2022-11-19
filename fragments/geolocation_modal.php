<?php
/**
 * Fragment zum Aufbau eines modalen Fensters (Bootstrap 3.4).
 *
 * Wird benötigt für das YForm-Value "geolocation_url"
 * 
 * Es finden nur formale Prüfungen statt. Fehlende Werte werden nicht durch Defaults ersetzt.
 * Hier wird aus Eingangsdaten HTML gebaut, mehr nicht.
 * 
 * $this->id        optional; ID für das Modal an sich
 * $this->title     optional: Titel für das Modal
 * $this->content   optional: Inhalt
 *  
 */

namespace Geolocation;

use rex_fragment;

/**
 * @var rex_fragment $this
 */

/**
 * @var string $id
 */
$id = $this->id ?? '';
if ('' === $id) {
    $modalIdAttr = '';
    $modalLabelId = md5(microtime());
} else {
    $modalIdAttr = ' id="'.$id.'"';
    $modalLabelId = $id.'-label'; 
}

/**
 * @var string $content
 */
$content = $this->content ?? '';
$content = '<div class="modal-body" style="font-size:15px;">'.$content.'</div>';

/**
 * @var string $title
 */
$title = $this->title ?? '';
if ('' === $title) {
    $title = '';
} else {
    $title = '<h4 class="modal-title" id="' . $modalLabelId.'">'.$title.'</h4>';
}

?>
<div class="modal fade"<?= $modalIdAttr ?> tabindex="-1" role="dialog" aria-labelledby="<?= $modalLabelId ?>">
<div class="modal-dialog" role="document">
<div class="modal-content">
<div class="modal-header">
<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
<?= $title ?>
</div>
<?= $content ?>
<div class="modal-footer">
<button type="button" class="btn btn-default" data-dismiss="modal">OK</button>
</div>
</div>
</div>
</div>
