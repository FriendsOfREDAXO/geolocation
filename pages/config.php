<?php
/**
 * Basiskonfiguration
 *
 * @package geolocation
 *
 * @var \rex_addon $this
 */

$form = \Geolocation\config_form::factory( \Geolocation\ADDON );

$fragment = new \rex_fragment();
$fragment->setVar('class', 'edit', false);
$fragment->setVar('title', \rex_i18n::msg('geolocation_config'), false);
$fragment->setVar('body', $form->get(), false);
echo $fragment->parse('core/page/section.php');
