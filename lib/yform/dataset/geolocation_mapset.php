<?php
/*
yform-dataset to enhance rex_geolocation_maps:

    - dataset-spezifisch

    - Formularbezogen

    - Listenbezogen

    - AJAX-Abrufe

        sendMapset            Kartensatz-Abruf vom Client beantworten

    - Support


*/

class geolocation_mapset extends \rex_yform_manager_dataset
{

    # dataset-spezifisch

    # Formularbezogen

    # AJAX-Abrufe

    static public function sendMapset( $mapset = 0 )
    {
        $proxy = new geolocation_proxy();

        // check mapset, abort if invalid
        $mapset = self::get( $mapset );
        if( !$mapset ) $proxy->sendNotFound();

        // get layers ond overlays in scope
        $locale = geolocation_tools::getLocale();
        geolocation_layer::getLayerConfigSet( explode(',',$mapset->layer), $locale, $result );
        geolocation_layer::getLayerConfigSet( explode(',',$mapset->overlay), $locale, $result );
        rex_response::sendJson( $result );
    }

    # Support

}
