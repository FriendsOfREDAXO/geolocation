<?php

/**
 * Helferlein, einige Hlfsfunktionen, gekapselt als  statische Klassenmethoden.
 */

namespace FriendsOfRedaxo\Geolocation;

use rex;
use rex_clang;
use rex_i18n;
use rex_path;
use rex_request;
use rex_response;
use rex_url;
use rex_view;

use const PHP_URL_HOST;

class Tools
{
    /**
     * Array mit allen implementierten Sprachversionen (BE und FE)
     * Zusammenfassung aus BE (rex_i18n::getLocales) und FE (rex_clang::getAll())
     * Je Eintrag der Sprachcode (2-Zeichen) und die Information ob BE, FE oder beide.
     *
     * @api
     * @return array<string,string>   [ 'de' => 'de [FE/BE]', 'en' => 'en [BE]', ... ]
     */
    public static function getLocales(): array
    {
        $locales = [];
        foreach (rex_i18n::getLocales() as $v) {
            $locales[substr($v, 0, 2)][] = 'BE';
        }
        foreach (rex_clang::getAll() as $v) {
            $locales[$v->getCode()][] = 'FE';
        }
        foreach ($locales as $k => &$v) {
            $v = '"' . $k . ' [ ' . implode('/', $v) . ' ]":"' . $k . '"';
        }
        ksort($locales);
        /**
         * STAN: Method Geolocation\Tools::getLocales() should return array<string, string> but returns array<string, array<int, string>>.
         * da irrt PhpStan.
         * @phpstan-ignore-next-line
         */
        return $locales;
    }

    /**
     * Prüft "same origin" und bricht ggf. HTTP-Status HTTP_SERVICE_UNAVAILABLE hart ab.
     *
     * @api
     */
    public static function isAllowed(): void
    {
        $HTTP_REFERER = rex_request::server('HTTP_REFERER', 'string', '');
        $HTTP_HOST = rex_request::server('HTTP_HOST', 'string', '');
        if (parse_url($HTTP_REFERER, PHP_URL_HOST) !== $HTTP_HOST) {
            rex_response::cleanOutputBuffers();
            rex_response::setStatus(rex_response::HTTP_SERVICE_UNAVAILABLE);
            rex_response::sendContent(rex_response::HTTP_SERVICE_UNAVAILABLE);
            exit;
        }
    }

    /**
     * schickt HTTP_NOT_FOUND.
     *
     * ... und bricht dann mit HTTP_NOT_FOUND hart ab.
     *
     * @api
     * @return never
     */
    public static function sendNotFound(): void
    {
        rex_response::cleanOutputBuffers();
        rex_response::setStatus(rex_response::HTTP_NOT_FOUND);
        rex_response::sendContent(rex_response::HTTP_NOT_FOUND);
        exit;
    }

    /**
     * schickt HTTP_INTERNAL_ERROR.
     *
     * ... und bricht dann mit HTTP_INTERNAL_ERROR hart ab.
     *
     * @api
     * @return never
     */
    public static function sendInternalError(): void
    {
        rex_response::cleanOutputBuffers();
        rex_response::setStatus(rex_response::HTTP_INTERNAL_ERROR);
        rex_response::sendContent(rex_response::HTTP_INTERNAL_ERROR);
        exit;
    }

    /**
     * schickt eine Kartenkachel Tile an den Client.
     *
     * aus $timestamp und $ttl wird deren Header-Daten (Expires, Cache-Control)
     * errechnet
     * ... und bricht dann nach Versand hart ab.
     *
     * @api
     * @return never
     */
    public static function sendTile(string $tileFileName, string $contentType, int $timestamp, int $ttl): void
    {
        $time2elapse = $timestamp + ($ttl * 60);
        rex_response::cleanOutputBuffers();
        rex_response::setHeader('Expires', gmdate('D, d M Y H:i:s', $time2elapse) . ' GMT');
        rex_response::sendCacheControl('public, max-age=' . $ttl * 60);
        rex_response::sendContent($tileFileName, $contentType, $timestamp);
        exit;
    }

    /**
     * schickt ein JSON-Objekt an den Client.
     *
     * @api
     * @return never
     */
    public static function sendJson(array $data, int $timestamp, int $ttl): void
    {
        $time2elapse = $timestamp + ($ttl * 60);
        rex_response::cleanOutputBuffers();
        rex_response::setHeader('Expires', gmdate('D, d M Y H:i:s', $time2elapse) . ' GMT');
        rex_response::sendCacheControl('public, max-age=' . $ttl * 60);
        rex_response::sendJson($data);
        exit;
    }

    /**
     * Liefert (für das FE) die Asset-Tags gem. Konfiguration;
     * Direkte Ausgabe mit ECHO.
     *
     * @api
     */
    public static function echoAssetTags(): void
    {
        /**
         * STAN: If condition is always true.
         * Ist ein false positive. Hängt nämlich von der Konfiguration ab.
         * @phpstan-ignore-next-line
         */
        if (LOAD) {
            if (rex::isBackend()) {
                rex_view::addCssFile(rex_url::addonAssets(ADDON, 'geolocation.min.css'));
                rex_view::addJsFile(rex_url::addonAssets(ADDON, 'geolocation.min.js'));
            } else {
                echo AssetPacker\AssetPacker::target(rex_path::addonAssets(ADDON, 'geolocation.min.css'))
                    ->getTag();
                echo AssetPacker\AssetPacker::target(rex_path::addonAssets(ADDON, 'geolocation.min.js'))
                    ->getTag();
            }
        }
    }
}
