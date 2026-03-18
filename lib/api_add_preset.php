<?php

/**
 * API-Funktion: Preset-Layer hinzufügen.
 *
 * Kein Namespace, da die rex_api_*-Klasse sonst nicht gefunden wird.
 *
 * @package geolocation
 */

use FriendsOfRedaxo\Geolocation\PresetManager;

class rex_api_geolocation_add_preset extends rex_api_function
{
    /** @var bool */
    protected $published = false;

    /**
     * @throws rex_api_exception
     */
    public function execute(): rex_api_result
    {
        // CSRF prüfen
        $token = rex_csrf_token::factory('geolocation_add_preset');
        if (!$token->isValid()) {
            throw new rex_api_exception('Ungültiger CSRF-Token.');
        }

        // Berechtigungsprüfung
        $user = rex::getUser();
        if (null === $user || (!$user->hasPerm('geolocation[layer]') && !$user->isAdmin())) {
            throw new rex_api_exception('Keine Berechtigung.');
        }

        $presetKey = rex_request('preset', 'string', '');
        $apiKey    = rex_request('api_key', 'string', '');

        $result = PresetManager::addPreset($presetKey, $apiKey);

        if ($result['ok']) {
            return new rex_api_result(true, rex_view::success(rex_escape($result['message'])));
        }

        return new rex_api_result(false, rex_view::error(rex_escape($result['message'])));
    }
}
