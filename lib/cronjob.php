<?php
namespace Geolocation;

# Cronjob-Klasse; ruft die Bereinigungs-Methode in Geolocation\cache auf

class cronjob extends \rex_cronjob
{

    const LABEL = 'Geolocation: Cleanup Cache';

    public function execute() : bool
    {
        $msg = cache::cleanupCache();
        $this->setMessage( implode( PHP_EOL,$msg ) );
        return true;
    }

    public function getTypeName() : string
    {
        return \rex_i18n::msg( 'geolocation_cron_title' );
    }

}
