<?php

/**
 * Bei Update Weiterleitung auf install.php.
 */

namespace FriendsOfRedaxo\Geolocation;

use rex_addon;

/** @var rex_addon $this */

//damit wir die aktuelle install.php im temp. Update-Verzeichnis erwischen
$this->includeFile(__DIR__ . '/install.php');
