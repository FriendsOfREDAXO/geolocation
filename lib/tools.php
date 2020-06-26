<?php

# Helferlein

class geolocation_tools
{

    // Aktuell gültige Locale im aktuellen Kontext (BE oder FE)
    static public function getLocale(){
        if( rex::isBackend() ){
            $locale = rex_i18n::getLocale();
        } else {
            $locale = rex_clang::getCurrent()->getCode();
        }
        return substr( $locale,0,2 );
    }


    // Zusammenfassung aus BE (rex_i18n::getLocales) und FE (rex_clang::getAll())
    static public function getLocales(){
        $locales = [];
        foreach( rex_i18n::getLocales() as $v ){
            $locales[ substr( $v,0,2 ) ] = ['BE'];
        }
        foreach( rex_clang::getAll() as $v ){
            $v = $v->getCode();
            if( $locales[ $v ] ) {
                $locales[ $v ][] = 'FE';
            } else {
                $locales[ $v ] = ['FE'];
            }
        }
        foreach( $locales as $k=>&$v ){
            $v = '"' .$k . ' [ '.implode('/',$v).' ]":"' .$k. '"';
        }
        ksort( $locales );
        return $locales;
    }

}
