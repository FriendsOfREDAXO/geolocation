<?php
/**
 * Cronjob-Klasse; ruft die Bereinigungs-Methode in Geolocation\Cache auf.
 *
 * @package geolocation
 */

namespace Geolocation;

use rex_cronjob;
use rex_i18n;

class cronjob extends rex_cronjob
{
    public const LABEL = 'Geolocation: Cleanup Cache';

    /**
     *   Ausführende Aktion des Cron-Jobs.
     *
     *   Die eigentliche Rückmeldung erfolgt mit setMessage()
     *
     *   @return bool      true
     */
    public function execute(): bool
    {
        $msg = Cache::cleanupCache();
        $this->setMessage(implode(PHP_EOL, $msg));
        return true;
    }

    /**
     *   Typename.
     *
     *   @return string      Typename
     */
    public function getTypeName(): string
    {
        return rex_i18n::msg('geolocation_cron_title');
    }
}
