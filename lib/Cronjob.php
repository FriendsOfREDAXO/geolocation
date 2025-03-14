<?php

/**
 * Cronjob-Klasse; ruft die Bereinigungs-Methode in Geolocation\Cache auf.
 */

namespace FriendsOfRedaxo\Geolocation;

use rex_cronjob;
use rex_i18n;

use const PHP_EOL;

class Cronjob extends rex_cronjob
{
    /** @api */
    public const LABEL = 'Geolocation: Cleanup Cache';

    /**
     * Ausführende Aktion des Cron-Jobs.
     *
     * Die eigentliche Rückmeldung erfolgt mit setMessage()
     */
    public function execute(): bool
    {
        /** @var list<string> $msg */
        $msg = Cache::cleanupCache();
        $this->setMessage(implode(PHP_EOL, $msg));
        return true;
    }

    /**
     * Typename.
     */
    public function getTypeName(): string
    {
        return rex_i18n::msg('geolocation_cron_title');
    }
}
