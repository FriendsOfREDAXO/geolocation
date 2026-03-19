<?php
define('REX_CRONJOB_SCRIPT', true);
require_once '../../core/boot.php';

try {
    $layerParams = ['func' => 'add'] + rex_csrf_token::factory(rex_yform_manager_table::get(rex::getTable('geolocation_layer'))->getCSRFKey())->getUrlParams();
    print_r($layerParams);
} catch (Throwable $e) { echo $e->getMessage(); }
