<?php
/**
 * Cache-Verwaltung.
 *
 * @package geolocation
 */

namespace Geolocation;

use rex_config;
use rex_dir;
use rex_file;
use rex_i18n;
use rex_path;
use rex_response;

use function count;

class Cache
{
    /**
     *   instanziert den Proxy und bricht ab wenn Abruf von außerhalb der Webseite
     *   isAllowed stirbt ggf. mit einem \rex_response::HTTP_SERVICE_UNAVAILABLE.
     */
    public function __construct()
    {
        Tools::isAllowed();
    }

    /**
     *   schickt die angegebene Datei raus.
     *
     *   Expires wird an den Browser übertragen als Zeitstempel der Datei
     *   plus Time-To-Live für diesen Cache.
     *
     *   @param string       Pfadname der Datei
     *   @param string       Content-type (MIME)
     *   @param int      Time-To-Live im Cache
     */
    public function sendCacheFile(string $filePath, string $contentType, $ttl)
    {
        // Restlaufzeit der Datei
        $timestamp = filemtime($filePath);
        $time2elapse = $timestamp + ($ttl * 60);
        // send the cached tile-file to the client
        rex_response::cleanOutputBuffers();
        rex_response::setHeader('Expires', gmdate('D, d M Y H:i:s', $time2elapse) .' GMT');
        rex_response::sendCacheControl('public, max-age=' . $ttl * 60);
        rex_response::sendFile($filePath, $contentType);
        exit;
    }

    /**
     *   Sucht die angegebene Datei im Cache.
     *
     *   Kann Wildcard im Suffix enthalten (wenn der Dateityp offen ist: png, png8, jpeg,..)
     *   nimmt die erste gefundene Datei, prüft ob sie zu alt ist (dann löschen = nicht vorhanden)
     *   liefert den Dateinamen oder null zurück
     *
     *   @param string       Pfadname der Datei
     *   @param int      Time-To-Live im Cache
     *
     *   @return ?string     Dateiname inkl. Pfad oder null
     */
    public function findCachedFile($filePath, $ttl): ?string
    {
        foreach (glob($filePath, GLOB_NOSORT) as $cacheFile) {
            if ((time() - $ttl) > fileatime($cacheFile)) {
                // delete cache-file if time-to-live is expired; forces update from tile-server
                rex_file::delete($cacheFile);
                return null;
            }
            // use cached file-file
            return $cacheFile;
        }
        return null;
    }

    // ---  Cache löschen

    /**
     *   Löscht alle Dateien im Cache für den angegebenen Layer.
     *
     *   @param int     ID des Karten-Layers oder null (default)
     *
     *   @return int     Anzahl gelöschter Dateien
     */
    public static function clearLayerCache(int $layer): int
    {
        $count = 0;
        $targetDir = rex_path::addonCache(ADDON, $layer);
        if ($layer && is_dir($targetDir)) {
            $count = count(glob($targetDir.'/*', GLOB_NOSORT));
            $count = rex_dir::delete($targetDir, true) ? $count : 0;
        }
        return $count;
    }

    /**
     *   Löscht alle Dateien im Cache für alle Layer.
     *
     *   @return int     Anzahl gelöschter Dateien
     */
    public static function clearCache(): int
    {
        $count = 0;
        $targetDir = rex_path::addonCache(ADDON);
        foreach (glob($targetDir.'*', GLOB_NOSORT) as $dir) {
            $count += self::clearLayerCache(substr($dir, strrpos($dir, '/') + 1));
        }
        return $count;
    }

    /**
     *   Cache aufräumen (z.B. per Cronjob).
     *
     *   Bereinigt den Cache für alle Layer, die Online sind (Offliner sollten eh leer sein)
     *   Berücksichtigt die Layer-individuellen Werte für Aufbewahrungsdauer (TTL) und
     *   Verzeichnisgröße (maxFiles, threshold).
     *
     *   @return array     Array mit Löschmeldungen je Layer
     */
    public static function cleanupCache(): array
    {
        $msg = [];

        // Die DefaultWerte für ttl und maxFiles abrufen
        $defTTL = rex_config::get(ADDON, 'cache_ttl', TTL_DEF);
        $defMaxFiles = rex_config::get(ADDON, 'cache_maxfiles', CFM_DEF);

        // Schleife über die Layer
        $layers = layer::query()
            ->where('online', 1)
            ->find();

        foreach ($layers as $layer) {
            $ttl = (int) $layer->ttl ?: $defTTL;
            $threshold = (int) $layer->cfmax ?: $defMaxFiles;
            $msg[] = self::cleanupLayerCache($layer->id, $threshold, $ttl);
        }

        return $msg;
    }

    /**
     *   Bereinigt den Cache für den angegebenen Layer $layer (z.B. per Cronjob).
     *
     *   Öffnet den DirHandle und löscht aus der Zeit ($ttl) gelaufenen Dateien via self::cleanupDir
     *   Ist das Verzeichnis danach noch zu groß ($threshold), wird die TTL halbiert und ein neuer
     *   Durchlauf gestartet bis der $threshold unterschritten ist.
     *
     *   @param int    ID des Karten-Layers
     *   @param int    Threshold (max. Anzahl Dateien im Cache)
     *   @param int    Tile-To-Live ( max Alter der Dateien im Cache)
     *
     *   @return string    Löschmeldungen
     */
    public static function cleanupLayerCache(int $layer = 0, int $threshold = CFM_DEF, int $ttl = TTL_DEF): string
    {
        if (!$layer) {
            return rex_i18n::msg('geolocation_cron_error', $layer);
        }

        $threshold = max($threshold, CFM_MIN);
        $ttl = max($ttl, TTL_MIN);

        $targetDir = rex_path::addonCache(ADDON, $layer.'/');
        $targetTime = time() - ($ttl * 60);
        $timeString = date('Y-m-d G-H-s', $targetTime);

        $size = 0;
        $deleted = 0;
        $counter = 0;
        if ($dh = @opendir($targetDir)) {
            do {
                rewinddir($dh);
                $counter = self::cleanupDir($dh, $targetDir, $targetTime, $deleted);
                if (0 == $size) {
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
     *   Löscht alte Dateien im Cache-Verzeichnis.
     *
     *   Eigentlich nur eine Serviceroutine für
     *   durchläuft Verzeichnis $dh (ressource aus OpenDir), Name $targetDir
     *   alle Dateien älter als $timestamp werden gelöscht.
     *   Durchlauf gestartet bis der $threshold unterschritten ist.
     *
     *   @param int    ID des Karten-Layers
     *   @param int    Threshold (max. Anzahl Dateien im Cache)
     *   @param int    Tile-To-Live ( max Alter der Dateien im Cache)
     *   @param &integer   Parameter-Rückgabe der Anzahl gelöschter Dateien
     *
     *   @return int       liefert die Anzahl Dateien im Verzeichnis zurück
     */
    public static function cleanupDir($dh, $targetDir, $timestamp, &$deleted): int
    {
        $counter = 0;
        while (($file = readdir($dh)) !== false) {
            if ('.' == $file[0]) {
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
