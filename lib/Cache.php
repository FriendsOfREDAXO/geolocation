<?php

/**
 * Cache-Verwaltung.
 */

namespace FriendsOfRedaxo\Geolocation;

use rex_config;
use rex_dir;
use rex_file;
use rex_i18n;
use rex_path;
use rex_response;

use function count;
use function is_array;
use function is_bool;

use const GLOB_NOSORT;

class Cache
{
    /**
     * instanziert den Proxy und bricht ab wenn Abruf von außerhalb der Webseite
     * isAllowed stirbt ggf. mit einem \rex_response::HTTP_SERVICE_UNAVAILABLE.
     */
    public function __construct()
    {
        Tools::isAllowed();
    }

    /**
     * schickt die angegebene Datei raus.
     *
     * Expires wird an den Browser übertragen als Zeitstempel der Datei
     * plus Time-To-Live für diesen Cache.
     *
     * @api
     * @return never
     */
    public function sendCacheFile(string $filePath, string $contentType, int $ttl): void
    {
        $timestamp = filemtime($filePath);
        if (is_bool($timestamp)) {
            Tools::sendNotFound();
        }

        $time2elapse = $timestamp + ($ttl * 60);

        rex_response::cleanOutputBuffers();
        rex_response::setHeader('Expires', gmdate('D, d M Y H:i:s', $time2elapse) . ' GMT');
        rex_response::sendCacheControl('public, max-age=' . $ttl * 60);
        rex_response::sendFile($filePath, $contentType);
        exit;
    }

    /**
     * Sucht die angegebene Datei im Cache.
     *
     * Kann Wildcard im Suffix enthalten (wenn der Dateityp offen ist: png, png8, jpeg,..)
     * nimmt die erste gefundene Datei, prüft ob sie zu alt ist (dann löschen = nicht vorhanden)
     * liefert den Dateinamen oder null zurück
     *
     * @api
     */
    public function findCachedFile(string $filePath, int $ttl): ?string
    {
        $files = glob($filePath, GLOB_NOSORT);
        if (!is_array($files)) {
            $files = [];
        }
        foreach ($files as $cacheFile) {
            if ((time() - $ttl) > fileatime($cacheFile)) {
                // delete cache-file if time-to-live is expired; forces update from tile-server
                rex_file::delete($cacheFile);
                return null;
            }
            // use cached file
            return $cacheFile;
        }
        return null;
    }

    // ---  Cache löschen

    /**
     * Löscht alle Dateien im Cache für den angegebenen Layer.
     * (ID des Karten-Layers oder null für default).
     *
     * @api
     */
    public static function clearLayerCache(int $layer): int
    {
        $deletedFiles = 0;
        if (0 < $layer) {
            $targetDir = rex_path::addonCache(ADDON, (string) $layer);
            if (is_dir($targetDir)) {
                $files = glob($targetDir . '/*', GLOB_NOSORT);
                if (is_array($files)) {
                    $deletedFiles = count($files);
                    $deletedFiles = rex_dir::delete($targetDir, true) ? $deletedFiles : 0;
                }
            }
        }
        return $deletedFiles;
    }

    /**
     * Löscht alle Dateien im Cache für alle Layer.
     *
     * Eigentlich reicht das rex_file::delete, aber vorher noch die
     * Dateien in den Verzeichnissen zählen
     *
     * @api
     */
    public static function clearCache(): int
    {
        /**
         * relevante Dateien zählen. Dazu alle direkten Unterverzeichnisse
         * durchlaufen, deren Name eine Zahl ist (also verm. eine LayerId).
         */
        $deletedFiles = 0;
        $targetDir = rex_path::addonCache(ADDON);
        $layerCaches = scandir($targetDir);
        if (is_array($layerCaches)) {
            $layerCaches = preg_grep('/\d+/', $layerCaches);
            if (is_array($layerCaches)) {
                foreach ($layerCaches as $layer) {
                    $liste = scandir($targetDir . $layer);
                    if (is_array($liste)) {
                        $deletedFiles += (count($liste) - 2); // ohne . und ..
                    }
                }
            }
        }
        /**
         * Das gesamte Verzeichnis löschen und im Erfolgsfall $deletedFiles
         * zurückmelden, sonst 0.
         */
        $deletedFiles = rex_dir::delete($targetDir, true) ? $deletedFiles : 0;
        return $deletedFiles;
    }

    /**
     * Cache aufräumen (z.B. per Cronjob).
     *
     * Bereinigt den Cache für alle Layer, die Online sind (Offliner sollten eh leer sein)
     * Berücksichtigt die Layer-individuellen Werte für Aufbewahrungsdauer (TTL) und
     * Verzeichnisgröße (maxFiles, threshold).
     *
     * Rückgabe ist ein Array mit Löschmeldungen je Layer
     *
     * @api
     * @return list<string>
     */
    public static function cleanupCache(): array
    {
        $msg = [];

        // Die DefaultWerte für ttl und maxFiles abrufen
        $defTTL = rex_config::get(ADDON, 'cache_ttl', TTL_DEF);
        $defMaxFiles = rex_config::get(ADDON, 'cache_maxfiles', CFM_DEF);

        // Schleife über die Layer
        $layers = Layer::query()
            ->where('online', 1)
            ->find();
        /**
         * Damit phpstan akzeptiert, dass $layer nicht null sein kann.
         * @var Layer $layer
         */
        foreach ($layers as $layer) {
            $ttl = 0 < $layer->ttl ? $defTTL : $layer->ttl;
            $threshold = 0 < $layer->cfmax ? $defMaxFiles : $layer->cfmax;
            $msg[] = self::cleanupLayerCache($layer->getId(), $threshold, $ttl);
        }

        return $msg;
    }

    /**
     * Bereinigt den Cache für den angegebenen Layer $layer (z.B. per Cronjob).
     *
     * Öffnet den DirHandle und löscht aus der Zeit ($ttl) gelaufenen Dateien via self::cleanupDir
     * Ist das Verzeichnis danach noch zu groß ($threshold), wird die TTL halbiert und ein neuer
     * Durchlauf gestartet bis der $threshold unterschritten ist.
     *
     * ID des Karten-Layers, Threshold (max. Anzahl Dateien im Cache), Time-To-Live (max Alter der Dateien im Cache)
     *
     * Rückgabe: Ergebnismeldung
     *
     * @api
     */
    public static function cleanupLayerCache(int $layer = 0, int $threshold = CFM_DEF, int $ttl = TTL_DEF): string
    {
        if (0 < $layer) {
            return rex_i18n::msg('geolocation_cron_error', $layer);
        }

        $threshold = max($threshold, CFM_MIN);
        $ttl = max($ttl, TTL_MIN);

        $targetDir = rex_path::addonCache(ADDON, $layer . '/');
        $targetTime = time() - ($ttl * 60);
        $timeString = date('Y-m-d G-H-s', $targetTime);

        $size = 0;
        $deleted = 0;
        $counter = 0;
        $dh = @opendir($targetDir);
        if (false !== $dh) {
            do {
                rewinddir($dh);
                $counter = self::cleanupDir($dh, $targetDir, $targetTime, $deleted);
                if (0 === $size) {
                    $size = $counter;
                }
                $ttl = (int) $ttl / 2;
                $targetTime = time() - ($ttl * 60);
            } while (($size - $deleted) > $threshold);
            closedir($dh);
        }

        return rex_i18n::msg('geolocation_cron_success', $layer, $deleted, $size);
    }

    /**
     * Löscht alte Dateien im Cache-Verzeichnis.
     *
     * Eigentlich nur eine Serviceroutine für cleanupLayerCache
     * durchläuft Verzeichnis $dh (ressource aus OpenDir), Name $targetDir
     * alle Dateien älter als $timestamp werden gelöscht.
     * Durchlauf gestartet bis der $threshold unterschritten ist.
     *
     * Rückgabewert ist die Anzahl verbliebener Dateien im Verzeichnis
     * Über den Parameter $delete wird die Anzahl gelöschter Dateien
     * mitgeteilt (hochzählen des übergebenen Wertes)
     *
     * @param resource $dh          Directory-Handle
     */
    private static function cleanupDir($dh, string $targetDir, float $timestamp, int &$deleted): int
    {
        $counter = 0;
        while (($file = readdir($dh)) !== false) {
            if ('.' === $file[0]) {
                continue;
            }
            $targetFile = $targetDir . $file;
            if ('file' !== filetype($targetFile)) {
                continue;
            }
            ++$counter;
            if (filemtime($targetFile) < $timestamp) {
                if (unlink($targetFile)) {
                    ++$deleted;
                }
                continue;
            }
        }
        return $counter;
    }
}
