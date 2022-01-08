<?php
/**
 * Helferlein, einige Hlfsfunktionen, gekapselt als  statische Klassenmethoden
 *
 * @package geolocation
 */

namespace Geolocation;

class tools
{

    /**
     *  Array mit allen implementierten Sprachversionen (BE und FE)
     *  Zusammenfassung aus BE (rex_i18n::getLocales) und FE (rex_clang::getAll())
     *  Je Eintrag der Sprachcode (2-Zeichen) und die Information ob BE, FE oder beide
     *
     *  @return array   [ 'de' => 'de [FE/BE]', 'en' => 'en [BE]', ... ]
     */
    static public function getLocales() : array
    {
        $locales = [];
        foreach( \rex_i18n::getLocales() as $v ){
            $locales[ substr( $v,0,2 ) ][] = 'BE';
        }
        foreach( \rex_clang::getAll() as $v ){
            $locales[ $v->getCode() ][] = 'FE';
        }
        foreach( $locales as $k=>&$v ){
            $v = '"' .$k . ' [ '.implode('/',$v).' ]":"' .$k. '"';
        }
        ksort( $locales );
        return $locales;
    }

    /**
     *  Prüft "same origin" und bricht ggf. HTTP-Status HTTP_SERVICE_UNAVAILABLE hart ab.
     *
     *  @return bool   true wenn Bedingung erfüllt
     */
    static public function isAllowed() : bool
    {
        if (!empty($_SERVER['HTTP_REFERER']) && parse_url($_SERVER['HTTP_REFERER'], PHP_URL_HOST) != $_SERVER['HTTP_HOST'])
        {
            \rex_response::cleanOutputBuffers();
            \rex_response::setStatus( \rex_response::HTTP_SERVICE_UNAVAILABLE );
            \rex_response::sendContent( \rex_response::HTTP_SERVICE_UNAVAILABLE );
            exit();
        }
        return true;
    }

    /**
     *  schickt HTTP_NOT_FOUND
     *
     *  ... und bricht dann mit HTTP_NOT_FOUND hart ab.
     */
    static public function sendNotFound() : void
    {
        \rex_response::cleanOutputBuffers();
        \rex_response::setStatus( \rex_response::HTTP_NOT_FOUND );
        \rex_response::sendContent( \rex_response::HTTP_NOT_FOUND );
        exit();
    }

    /**
     *  schickt HTTP_INTERNAL_ERROR
     *
     *  ... und bricht dann mit HTTP_INTERNAL_ERROR hart ab.
     */
    static public function sendInternalError(): void
    {
        \rex_response::cleanOutputBuffers();
        \rex_response::setStatus( \rex_response::HTTP_INTERNAL_ERROR );
        \rex_response::sendContent( \rex_response::HTTP_INTERNAL_ERROR );
        exit();
    }

    /**
     *  schickt eine Kartenkachel Tile an den Client
     *
     *  aus $timestamp und $ttl wird deren Header-Daten (Expires, Cache-Control)
     *  errechnet
     *
     *  ... und bricht dann nach Versand hart ab.
     *
     *  @param  string $tile        Das Bild der Karten-Kachel (Tile)
     *  @param  string $contentType Art des Bildes (Mime-Typ)
     *  @param  string $timestamp   Unix-Zeitstempel der Kachel
     *  @param  string $ttl         Time-To-Live in Minuten
     */
    static public function sendTile( $tile, $contentType, $timestamp, $ttl ) : void
    {
        $time2elapse = $timestamp + ($ttl * 60);
        \rex_response::cleanOutputBuffers();
        \rex_response::setHeader('Expires', gmdate("D, d M Y H:i:s", $time2elapse) ." GMT" );
        \rex_response::sendCacheControl( 'public, max-age=' . $ttl * 60 );
        \rex_response::sendContent($tile, $contentType, $timestamp);
        exit();
    }

    /**
     *  Liefert (für das FE) die Asset-Tags gem. Konfiguration;
     *  Direkte Ausgabe mit ECHO
     */
    static public function echoAssetTags() : void
    {
        if( LOAD ){
            if( \rex::isBackend() ) {
                \rex_view::addCssFile(\rex_url::addonAssets(\Geolocation\ADDON,'geolocation.min.css'));
                \rex_view::addJsFile(\rex_url::addonAssets(\Geolocation\ADDON,'geolocation.min.js'));
            } else {
                echo AssetPacker\AssetPacker::target( \rex_path::addonAssets(ADDON,'geolocation.min.css') )
                    ->getTag();
                echo AssetPacker\AssetPacker::target( \rex_path::addonAssets(ADDON,'geolocation.min.js') )
                    ->getTag();
            }
        }
    }

}
