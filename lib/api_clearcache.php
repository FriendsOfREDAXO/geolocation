<?php
/**
 * @package Geolocation
 *
 * @internal
 */

class rex_api_geolocation_clearcache extends \rex_api_function
{
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
