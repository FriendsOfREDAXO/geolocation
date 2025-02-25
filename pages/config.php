<?php

/**
 * Basiskonfiguration.
 */

namespace FriendsOfRedaxo\Geolocation;

use rex_fragment;
use rex_i18n;

$form = ConfigForm::factory(ADDON);

$fragment = new rex_fragment();
$fragment->setVar('class', 'edit', false);
$fragment->setVar('title', rex_i18n::msg('geolocation_config'), false);
$fragment->setVar('body', $form->get(), false);
echo $fragment->parse('core/page/section.php');
