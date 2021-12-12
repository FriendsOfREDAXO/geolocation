<?php
/**
 * @package Geolocation
 *
 * Hier kein Namespace, da sonst die API-Plasse nicht gefunden wird.
 */

class rex_api_geolocation_clearcache extends \rex_api_function
{

    /**
     * ruft die Cachebereinigung auf. Wenn in der URL data_id=.. auf einen
     * Kartenlayer verweist, wird dessen Cache gelöscht. Ohne data_id
     * werden alle Caches gelöscht.
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

        $layerId = \rex_request('data_id', 'integer', 0);
        $c = $layerId
             ? \Geolocation\cache::clearLayerCache( $layerId )
             : \Geolocation\cache::clearCache();

        return new \rex_api_result(true, \rex_i18n::msg('geolocation_cache_files_removed', $c));
    }

}
