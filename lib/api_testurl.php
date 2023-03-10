<?php

/**
 * Führt URL-Tests durch.
 *
 * Zur Zeit ist nur ein Test implementiert: Tiles/Layer
 *
 * Die Rückmeldung an den aufrufenden Client ist HTML zur Anzeige
 * in einem modalen Dalog im Client.
 *
 * Die Rückmeldung ist eigentlich immer mit einem HTTP_OK, denn erkannte
 * Fehler z.B. der Url oder aus der Rückantwort des Providers sind ja
 * gewünschte Erkenntnisse.
 *
 * Nur zulässig wenn angemeldet und mit passender Permission "geolocation[....]" bzw. als Admin
 *
 * Hier kein Namespace, da sonst die API-Klasse nicht gefunden wird.
 */

use rex;
use rex_api_function;
use rex_i18n;
use rex_request;
use rex_response;

class rex_api_geolocation_testurl extends rex_api_function
{
    /**
     * ?action=..
     * @api
     */
    public const API_TYPE = 'action';

    /**
     * Codes für die verfügbaren Actions.
     * @api
     */
    public const TILE_URL = '1'; // Teste eine Tile/Layer-URL

    /**
     * Testkoordinaten für Tile/Layer-Abrufe => Konstanz am Bodensee.
     * @var array<string,int|string>
     */
    protected static array $tileTestData = [
        '{x}' => 8609,
        '{y}' => 5718,
        '{z}' => 14,
        '{s}' => '',
        '{r}' => '@2x',
    ];

    /**
     * Haupt-Methode des API-Calls
     * Endet immer mit einem response, also ohne Return.
     *
     * @return never
     */
    public function execute()
    {
        /**
         * Test-Typ abrufen und entsprechend dispatchen.
         */
        switch (rex_request::request(self::API_TYPE, 'string', '')) {
            case self::TILE_URL:
                $this->ensurePermission('geolocation[layer]');
                $this->testTileUrl();
                //break;
        }

        /**
         * Keine gültige Action in der URL.
         */
        $this->sendResponseAndExit(
            rex_response::HTTP_BAD_REQUEST,
            rex_i18n::msg('geolocation_testurl_internalerror', self::API_TYPE),
        );
    }

    /**
     * Action = 1: Testet Tile/Layer-URLs auf Gültigkeit.
     *
     * Die Methode endet immer mit einem sendResponseAndExit, also ohne return
     *
     * Die Rückgabe an den Client ist ein Paket mit dem RC HTTP_OK. Sofern ein Fehler
     * vorliegt, wird aus den cUrl-Angaben ReturnCode, Message und Content eine
     * geeignete Ausgabe gebaut, die der Client direkt anzeigen kann (HTML).
     *
     * $this->requestTest($url) führt die Testabfrage durch. Ergebnisse
     * werden aufbereitet und weitergeleitet. Im Falle eines HTTP_OK vom
     * Tile-Sever und einem image-Mime-Typ als Rückkage, wird der Content (ein Bild)
     * als Data-Url in einem IMG-Tag aufbereitet.
     *
     * @return never
     */
    protected function testTileUrl()
    {
        /**
         * URL muss angegeben sein.
         */
        $url = rex_request::request('url', 'string', '');
        $url = trim($url);
        if ('' === $url) {
            $this->sendResponseAndExit(
                rex_response::HTTP_BAD_REQUEST,
                rex_i18n::msg('geolocation_testurl_internalerror', 'url'),
            );
        }

        /**
         * subDomain muss nur angegeben sein, wenn URL {s} enthält
         * Und wenn angegeben: {s} mit dem ersten Zeichen ersetzen.
         */
        $subDomain = '';
        if (str_contains($url, '{s}')) {
            $subDomain = rex_request::request('subdomain', 'string', '');
            $subDomain = trim($subDomain);
            if ('' === $subDomain) {
                $this->sendResponseAndExit(
                    rex_response::HTTP_BAD_REQUEST,
                    rex_i18n::msg('geolocation_testurl_internalerror', 'subdomain'),
                );
            }
            self::$tileTestData['{s}'] = $subDomain[0];
        }

        /**
         * Werte für X, Y, Z und S einmischen.
         */
        $url = str_replace(array_keys(self::$tileTestData), self::$tileTestData, $url);

        /**
         * Testabruf.
         */
        $result = $this->requestTest($url);
        if ('' === $result['rc']) {
            $this->sendResponseAndExit(
                rex_response::HTTP_INTERNAL_ERROR,
                rex_i18n::msg('geolocation_testurl_internalerror', 'cUrl'),
            );
        } elseif ('200' === $result['rc'] && str_starts_with($result['type'], 'image/')) {
            $i18n = '' === self::$tileTestData['{s}'] ? 'geolocation_testurl_layer1ok' : 'geolocation_testurl_layer2ok';
            $this->sendResponseAndExit(
                $result['rc'],
                rex_i18n::msg($i18n, self::$tileTestData['{s}'], $subDomain),
                '<img src="data:'.$result['type'].';base64,'.base64_encode($result['content']).'" />',
            );
        } else {
            $this->sendResponseAndExit(
                $result['rc'],
                rex_i18n::msg('geolocation_testurl_providererror'),
                $result['content'],
            );
        }
    }

    /**
     * Liefert ein Array mit den Parametern für den API-Abruf.
     * Die konkreten Parameter können sich für die verschiedenen
     * Aktions-Typen unterscheiden.
     *
     * @api
     * @return string[]
     */
    public static function getApiParams(string $action): array
    {
        $params = self::getUrlParams();
        switch ($action) {
            case self::TILE_URL:
                $params[self::API_TYPE] = self::TILE_URL;
                break;
        }
        return $params;
    }

    /**
     * Führt den eigentlichen Abruf-Test per cURL aus.
     *
     * Wenn cUrl nicht initialisiert werden konnte, wird Returncoe 0 erteut.
     *
     * In allen anderen Fällen inkl. leere Antwort gibt es ein Rückgabe-Array
     * mit Content (z.B. JSon-Array oder Image), dem Content-Typ und dem Return-Code
     *
     * @return array{rc:string,content:string,type:string}
     */
    protected function requestTest(string $url): array
    {
        /**
         * Daten abrufen.
         */
        $ch = curl_init($url);
        if (is_bool($ch)) {
            return ['rc' => '', 'content' => '', 'type' => ''];
        }
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        if(($proxy = rex::getProperty('socket_proxy')) != '') {
             curl_setopt($ch, CURLOPT_PROXY, $proxy);
         }
        $content = (string) curl_exec($ch);
        $returnCode = (string) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $contentType = (string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        curl_close($ch);

        // no reply at all, keine verwertbare Antwort
        if ('0' === $returnCode) {
            return ['rc' => '204 No Content', 'content' => '', 'type' => ''];
        }

        /**
         * reguläres Ergebnis zurückmelden.
         */
        return ['rc' => $returnCode, 'content' => $content, 'type' => $contentType];
    }

    /**
     * prüft ab, ob der Zugriff mit den nötigen Rechten erfolgt und bricht ggf. ab.
     */
    protected function ensurePermission(string $permission): void
    {
        $hasPerm = false;
        try {
            $hasPerm = rex::requireUser()->hasPerm($permission);
        } catch (\Throwable $th) {
        }
        if( !$hasPerm) {
            rex_response::cleanOutputBuffers();
            rex_response::setStatus(rex_response::HTTP_FORBIDDEN);
            rex_response::sendContent(rex_response::HTTP_FORBIDDEN);
        }
    }

    /**
     * Sendet eine Antwort an den Client mit dem Ergebnis der Analyse.
     * Der ReturnCode der Antwort ist daher immer HTTP_OK.
     * Der ReturnCode bezogen auf den Inhalt der Antwort kann durchaus ein Fehlercode sein.
     * Z.B. wenn der getestete Provider einen Fehler in der URL meldet.
     *
     * @return never
     */
    protected function sendResponseAndExit(string $http_response, string $message, string $content = ''): void
    {
        $html = '<p>';
        $html .= '<strong>'.$http_response.'</strong>';
        if ('' < $message) {
            $html .= ': ' . $message;
        }
        $html .= '</p>';
        $html .= $content;
        rex_response::cleanOutputBuffers();
        rex_response::setStatus(rex_response::HTTP_OK);
        rex_response::sendContent($html, 'text/html');
        exit;
    }
}
