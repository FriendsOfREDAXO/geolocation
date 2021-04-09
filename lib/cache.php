<?php
namespace Geolocation;

# Cache-Verwaltung


class cache
{

    # instanziert den Proxy und bricht ab wenn Abruf von außerhalb der Webseite
    function __construct ( )
    {
        tools::isAllowed();
    }

    # schickt die Datei raus.
    public function sendCacheFile( $filePath, $contentType, $ttl )
    {
        // Restlaufzeit der Datei
        $timestamp = filemtime( $filePath );
        $time2elapse = $timestamp + ($ttl * 60);
        // send the cached tile-file to the client
        \rex_response::cleanOutputBuffers();
        \rex_response::setHeader('Expires', gmdate("D, d M Y H:i:s", $time2elapse) ." GMT" );
        \rex_response::sendCacheControl( 'public, max-age=' . $ttl * 60 );
        \rex_response::sendFile( $filePath, $contentType );
        exit();
    }

    # Sucht die angegebene Datei im Cache
    # Kann Wildcard im Suffix enthalten (wenn der Dateityp offen ist: png, png8, jpeg,..)
    # nimmt die erste gefundene Datei, prüft ob sie zu alt ist (dann löschen = nicht vorhanden)
    # liefert den Dateinamen oder null zurück
    public function findCachedFile( $filePath, $ttl ){
        foreach( glob( $filePath, GLOB_NOSORT) as $cacheFile ) {
            if( (time() - $ttl) > fileatime($cacheFile) ) {
                // delete cache-file if time-to-live is expired; force update from tile-server
                \rex_file::delete( $cacheFile );
                return null;
            } else {
                // use cached file-file
                return $cacheFile;
            }
        }
        return null;
    }

    # Cache löschen

    # Löscht alle Dateien im Cache für den angegebenen Layer
    static public function clearLayerCache( $layer = null )
    {
        $count = 0;
        $targetDir = \rex_path::addonCache( ADDON, $layer );
        $count = count( glob($targetDir.'/*', GLOB_NOSORT) );
        $count = \rex_dir::delete( $targetDir, true ) ? $count : 0;
        return $count;
    }

    # Löscht alle Dateien im Cache für alle Layer
    static public function clearCache( )
    {
        $count = 0;
        $targetDir = \rex_path::addonCache( ADDON );
        foreach( glob($targetDir.'*', GLOB_NOSORT) as $dir ){
            $count += self::clearLayerCache( substr($dir,strrpos($dir,'/')+1) );
        }
        return $count;
    }

    # Cache aufräumen (z.B. per Cronjob)

    # Bereinigt den Cache für alle Layer, die Online sind (Offliner sind eh leer)
    # Berücksichtigt die Layer-individuellen Werte für Aufbewahrungsdauer (TTL) und
    # Verzeichnisgröße (maxFiles, threshold).
    static public function cleanupCache(){
        $msg = [];

        // Die DefaultWerte für ttl und maxFiles abrufen
        $defTTL = \rex_config::get( ADDON, 'cache_ttl', TTL_DEF );
        $defMaxFiles = \rex_config::get( ADDON, 'cache_maxfiles', CFM_DEF );

        // Schleife über die Layer
        $layers = layer::query()
            ->where('online', 1)
            ->find();

        foreach( $layers as $layer ){
            $ttl = (integer) $layer->ttl ?: $defTTL;
            $threshold = (integer) $layer->cfmax ?: $defMaxFiles;
            $msg[] = self::cleanupLayerCache( $layer->id, $threshold, $ttl );
        }

        return $msg;
    }

    # Bereinigt den Cache für den angegebenen Layer $layer
    # Öffnet den DirHandle und löscht aus der Zeit ($ttl) gelaufenen Dateien via self::cleanupDir
    # Ist das Verzeichnis danach noch zu groß ($threshold), wird die TTL halbiert und ein neuer
    # Durchlauf gestartet bis der $threshold unterschritten ist.
    static public function cleanupLayerCache( int $layer = 0, int $threshold=CFM_DEF, int $ttl=TTL_DEF )
    {
        if( !$layer ) return \rex_i18n::msg( 'geolocation_cron_error', $layer );

        $threshold = max( $threshold, CFM_MIN );
        $ttl = max( $ttl, TTL_MIN );

        $targetDir = \rex_path::addonCache( ADDON, $layer.'/' );
        $targetTime = time() - ($ttl * 60);
        $timeString = date( 'Y-m-d G-H-s',$targetTime);

        $size = 0;
        $deleted = 0;
        $counter = 0;
        if ($dh = @opendir($targetDir)) {
            do{
                rewinddir( $dh );
                $counter = self::cleanupDir( $dh, $targetDir, $targetTime, $deleted );
                if( $size == 0) $size = $counter;
                $ttl = (int) $ttl / 2;
                $targetTime = time() - ($ttl * 60);
            } while ( ($size - $deleted) > $threshold );
            closedir($dh);
        }

        return \rex_i18n::msg( 'geolocation_cron_success', $layer, $deleted, $size );
    }

    # durchläuft Verzeichnis $dh (ressource aus OpenDir), Name $targetDir
    # alle Dateien älter als $timestamp werden gelöscht.
    # &$deleted Anzahl gelöschter Dateien
    # @RETURN   liefert die Anzahl Dateien zurück
    static public function cleanupDir( $dh, $targetDir, $timestamp, &$deleted )
    {
        $counter = 0;
        while( ($file = readdir($dh)) !== false ){
            if( $file[0] == '.' ) {
                continue;
            }
            $targetFile = $targetDir . $file;
            if( filetype($targetFile) !== 'file' ) {
                continue;
            }
            $counter ++;
            if( filemtime( $targetFile ) <  $timestamp ){
                if( unlink( $targetFile ) ) {
                        $deleted ++;
                };
                continue;
            }
        }
        return $counter;
    }

}
