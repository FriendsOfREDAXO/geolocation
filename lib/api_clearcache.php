<?php
/**
 * ruft die Cachebereinigung auf.
 * Wenn in der URL layer_id=.. auf einen Kartenlayer verweist, wird dessen Cache gelöscht.
 * Wenn in der URL mapset_id=.. auf einen Kartensatz verweist, wird dessen Layer-Caches gelöscht.
 * Gibt es keine der beiden Parameter, werden alle Caches gelöscht.
 *
 * Nur zulässig wenn angemeldet und mit Permission "geolocation[clearcache]" bzw. als Admin
 *
 * Hier kein Namespace, da sonst die API-Klasse nicht gefunden wird.
 */

class rex_api_geolocation_clearcache extends \rex_api_function
{
    /**
     * @throws \rex_api_exception
     * @return \rex_api_result
     */
    public function execute()
    {
        $user = \rex::getUser();
        if (null === $user || !$user->hasPerm('geolocation[clearcache]')) {
            throw new \rex_api_exception('User has no permission to delete cache files!');
        }

        if (0 < ($layerId = rex_request('layer_id', 'integer', 0))) {
            $c = \Geolocation\Cache::clearLayerCache($layerId);
        } elseif (0 < ($mapsetId = rex_request('mapset_id', 'integer', 0))) {
            $c = 0;
            $mapset = \Geolocation\Mapset::get($mapsetId);
            if (null !== $mapset) {
                /**
                 * STAN: Foreach overwrites $layerId with its value variable.
                 * Soll wohl. Passt ja auch.
                 * @phpstan-ignore-next-line
                 */
                foreach ($mapset->layerset as $layerId) {
                    $c += \Geolocation\Cache::clearLayerCache($layerId);
                }
            }
        } else {
            $c = \Geolocation\Cache::clearCache();
        }

        return new \rex_api_result(true, \rex_i18n::msg('geolocation_cache_files_removed', $c));
    }
}
