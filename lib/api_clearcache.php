<?php
/**
 * @package Geolocation
 *
 * Hier kein Namespace, da sonst die API-Plasse nicht gefunden wird.
 */

class rex_api_geolocation_clearcache extends \rex_api_function
{

    /**
     * ruft die Cachebereinigung auf.
     * Wenn in der URL layer_id=.. auf einen Kartenlayer verweist, wird dessen Cache gelöscht.
     * Wenn in der URL mapset_id=.. auf einen Kartensatz verweist, wird dessen Layer-Caches gelöscht.
     * Gibt es keine der beiden Parameter, werden alle Caches gelöscht.
     *
     * Nur zulässig wenn angemeldet und mit Permission "geolocation[clearcache]" bzw. als Admin
     *
     * @return rex_api_result ausgeführt
     *
     * @throws  \rex_api_exception no permissions
     */
    public function execute()
    {
        if (!($user = \rex::getUser()) || !$user->hasPerm('geolocation[clearcache]')) {
            throw new \rex_api_exception('User has no permission to delete cache files!');
        }

        if( $layerId = \rex_request('layer_id', 'integer', 0) ) {
            $c = \Geolocation\cache::clearLayerCache( $layerId );
        }

        elseif( $mapsetId = \rex_request('mapset_id', 'integer', 0) ) {
            $c = 0;
            $mapset = \Geolocation\mapset::get( $mapsetId );
            if( $mapset ) {
                foreach( $mapset->layerset as $layerId ) {
                    $c += \Geolocation\cache::clearLayerCache( $layerId );
                }
            }
        }

        else {
            $c = \Geolocation\cache::clearCache();
        }

        return new \rex_api_result(true, \rex_i18n::msg('geolocation_cache_files_removed', $c));
    }

}
