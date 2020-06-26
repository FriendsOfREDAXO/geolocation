<?php

# Cronjob-Klasse; ruft die Bereinigungs-Methode in geolocation_proxy auf

class rex_cronjob_geolocation_cache extends rex_cronjob
{

    public function execute()
    {
        $msg = geolocation_proxy::cleanupCache();
        $this->setMessage( implode( PHP_EOL,$msg ) );
        return true;
    }

    public function getTypeName()
    {
        return rex_i18n::msg( 'geolocation_cron_title' );
    }


/*
    static public function createTestFiles( $dir, $alter, $anzahl ){
        $dir = rex_path::addonCache('geolocation',$dir) . '/';
        $timestamp = time() - ( $alter * 24 * 60 * 60 );
        $timeString = date( 'Y-m-d G-H-s',$timestamp);
        for( $i=1; $i <= 20; $i++) {
            $filename = sprintf( '%s %02d', $timeString, $i );
            rex_file::put( $dir.$filename, $filename );
            touch( $dir.$filename, $timestamp );
        }
    }
*/

}
