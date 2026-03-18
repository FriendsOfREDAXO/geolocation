<?php

/**
 * PresetManager – Vordefinierte Layer-Konfigurationen für den Schnellstart.
 *
 * @package geolocation
 */

namespace FriendsOfRedaxo\Geolocation;

use rex;
use rex_sql;

class PresetManager
{
    /**
     * Cache der vorhandenen Spalten in rex_geolocation_layer.
     *
     * @var array<string, bool>|null
     */
    private static ?array $layerColumns = null;

    /**
     * Alle verfügbaren Presets.
     * Format:
     *   title        Anzeigename
     *   description  Kurzbeschreibung
     *   free         Kostenlos ohne API-Key
     *   requires_key API-Key nötig
     *   key_url      URL zum Key-Erstellen
     *   url          Tile-URL (Platzhalter: {z},{x},{y},{s},{apikey})
     *   retinaurl    Retina-URL (optional)
     *   subdomain    Subdomain-Zeichen
     *   attribution  Copyright-HTML
     *   lang         JSON-Label-Array
     *   layertype    b=Base, o=Overlay
     *   ttl          Cache-TTL in Minuten
     *   cfmax        Max. Cache-Dateien
     *
     * @return array<string, array<string, mixed>>
     */
    public static function getPresets(): array
    {
        return [
            'openfreemap_liberty' => [
                'title'        => 'OpenFreeMap: Liberty',
                'description'  => 'Kostenlos, kein API-Key, kein Limit. MapLibre-Vektorstil.',
                'free'         => true,
                'requires_key' => false,
                'key_url'      => '',
                'url'          => 'https://tiles.openfreemap.org/planet/{z}/{x}/{y}.pbf',
                'retinaurl'    => '',
                'subdomain'    => '',
                'attribution'  => '&copy; <a href="https://openfreemap.org" target="_blank">OpenFreeMap</a> contributors',
                'lang'         => '[["de","Karte"],["en","Map"]]',
                'layertype'    => 'b',
                'ttl'          => 10080,
                'cfmax'        => 5000,
            ],
            'osm_standard' => [
                'title'        => 'OpenStreetMap: Standard',
                'description'  => 'Der klassische OSM-Raster-Tile-Server. Kostenlos für moderaten Gebrauch.',
                'free'         => true,
                'requires_key' => false,
                'key_url'      => '',
                'url'          => 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
                'retinaurl'    => '',
                'subdomain'    => 'abc',
                'attribution'  => '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
                'lang'         => '[["de","Karte"],["en","Map"]]',
                'layertype'    => 'b',
                'ttl'          => 10080,
                'cfmax'        => 5000,
            ],
            'osm_de' => [
                'title'        => 'OpenStreetMap: Deutschland',
                'description'  => 'Deutschsprachiger OSM-Tile-Server vom OSM-Verein Deutschland.',
                'free'         => true,
                'requires_key' => false,
                'key_url'      => '',
                'url'          => 'https://{s}.tile.openstreetmap.de/{z}/{x}/{y}.png',
                'retinaurl'    => '',
                'subdomain'    => 'abc',
                'attribution'  => 'Map data: &copy; <a href="https://www.openstreetmap.org/">OpenStreetMap</a> under <a href="https://www.openstreetmap.org/copyright">ODdL</a>',
                'lang'         => '[["de","Karte"],["en","Map"]]',
                'layertype'    => 'b',
                'ttl'          => 10080,
                'cfmax'        => 5000,
            ],
            'stamen_toner' => [
                'title'        => 'Stadia Maps: Toner',
                'description'  => 'Schwarz-Weiß-Karte im Druckstil. Kostenlos mit optionaler Registrierung.',
                'free'         => true,
                'requires_key' => false,
                'key_url'      => 'https://client.stadiamaps.com/signup/',
                'url'          => 'https://tiles.stadiamaps.com/tiles/stamen_toner/{z}/{x}/{y}.png',
                'retinaurl'    => 'https://tiles.stadiamaps.com/tiles/stamen_toner/{z}/{x}/{y}@2x.png',
                'subdomain'    => '',
                'attribution'  => '&copy; <a href="https://stadiamaps.com/">Stadia Maps</a> &copy; <a href="https://stamen.com">Stamen Design</a> &copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
                'lang'         => '[["de","S/W-Karte"],["en","Map"]]',
                'layertype'    => 'b',
                'ttl'          => 43200,
                'cfmax'        => 5000,
            ],
            'maptiler_streets' => [
                'title'        => 'MapTiler: Streets',
                'description'  => 'Hochwertige Vektorkarten. Kostenloser Free-Tier (API-Key erforderlich).',
                'free'         => false,
                'requires_key' => true,
                'key_url'      => 'https://cloud.maptiler.com/account/keys/',
                'url'          => 'https://api.maptiler.com/tiles/v3/{z}/{x}/{y}.pbf?key={apikey}',
                'retinaurl'    => '',
                'subdomain'    => '',
                'attribution'  => '&copy; <a href="https://www.maptiler.com/copyright/">MapTiler</a> &copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
                'lang'         => '[["de","Karte"],["en","Map"]]',
                'layertype'    => 'b',
                'ttl'          => 43200,
                'cfmax'        => 5000,
            ],
            'opentopomap' => [
                'title'        => 'OpenTopoMap',
                'description'  => 'Topografische Karte auf OSM-Basis. Kostenlos.',
                'free'         => true,
                'requires_key' => false,
                'key_url'      => '',
                'url'          => 'https://{s}.tile.opentopomap.org/{z}/{x}/{y}.png',
                'retinaurl'    => '',
                'subdomain'    => 'abc',
                'attribution'  => 'Map data: &copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors, <a href="http://viewfinderpanoramas.org">SRTM</a> | Map style: &copy; <a href="https://opentopomap.org">OpenTopoMap</a>',
                'lang'         => '[["de","Topografie"],["en","Topography"]]',
                'layertype'    => 'b',
                'ttl'          => 43200,
                'cfmax'        => 3000,
            ],
        ];
    }

    /**
     * Einen einzelnen Preset per Schlüssel abrufen.
     *
     * @return array<string, mixed>|null
     */
    public static function getPreset(string $key): ?array
    {
        return self::getPresets()[$key] ?? null;
    }

    /**
     * Preset als neuen Layer in die Datenbank einfügen.
     * API-Key wird in der URL ersetzt.
     *
     * @return array{ok: bool, message: string}
     */
    public static function addPreset(string $presetKey, string $apiKey = ''): array
    {
        $preset = self::getPreset($presetKey);
        if (null === $preset) {
            return ['ok' => false, 'message' => 'Unbekannter Preset: ' . $presetKey];
        }

        $url = $preset['url'];
        if ($preset['requires_key']) {
            if ('' === trim($apiKey)) {
                return ['ok' => false, 'message' => 'API-Key fehlt für Preset "' . $preset['title'] . '"'];
            }
            $url = str_replace('{apikey}', trim($apiKey), $url);
        }

        // Prüfen ob URL schon vorhanden
        $sql = rex_sql::factory();
        $existing = $sql->getArray(
            'SELECT id FROM ' . rex::getTablePrefix() . 'geolocation_layer WHERE url = :url LIMIT 1',
            ['url' => $url]
        );
        if (!empty($existing)) {
            return ['ok' => false, 'message' => 'Layer mit dieser URL existiert bereits (ID ' . $existing[0]['id'] . ')'];
        }

        // Eindeutigen Namen sicherstellen
        $baseName = $preset['title'];
        $name = $baseName;
        $i = 2;
        while (true) {
            $check = $sql->getArray(
                'SELECT id FROM ' . rex::getTablePrefix() . 'geolocation_layer WHERE name = :name LIMIT 1',
                ['name' => $name]
            );
            if (empty($check)) {
                break;
            }
            $name = $baseName . ' ' . $i++;
        }

        $sql->setTable(rex::getTablePrefix() . 'geolocation_layer');
        self::setIfColumnExists($sql, 'name', $name);
        self::setIfColumnExists($sql, 'url', $url);
        self::setIfColumnExists($sql, 'retinaurl', $preset['retinaurl']);
        self::setIfColumnExists($sql, 'subdomain', $preset['subdomain']);
        self::setIfColumnExists($sql, 'attribution', $preset['attribution']);
        self::setIfColumnExists($sql, 'lang', $preset['lang']);
        self::setIfColumnExists($sql, 'layertype', $preset['layertype']);
        self::setIfColumnExists($sql, 'ttl', $preset['ttl']);
        self::setIfColumnExists($sql, 'cfmax', $preset['cfmax']);
        self::setIfColumnExists($sql, 'online', 1);

        // Globale Felder nur setzen, wenn sie im aktuellen Schema existieren.
        if (self::hasLayerColumn('updatedate') && self::hasLayerColumn('updateuser')) {
            $sql->addGlobalUpdateFields();
        }
        if (self::hasLayerColumn('createdate') && self::hasLayerColumn('createuser')) {
            $sql->addGlobalCreateFields();
        }
        $sql->insert();

        return ['ok' => true, 'message' => 'Layer "' . $name . '" wurde hinzugefügt.'];
    }

    private static function setIfColumnExists(rex_sql $sql, string $column, mixed $value): void
    {
        if (self::hasLayerColumn($column)) {
            $sql->setValue($column, $value);
        }
    }

    private static function hasLayerColumn(string $column): bool
    {
        if (null === self::$layerColumns) {
            self::$layerColumns = [];
            $sql = rex_sql::factory();
            $rows = $sql->getArray('SHOW COLUMNS FROM ' . rex::getTablePrefix() . 'geolocation_layer');
            foreach ($rows as $row) {
                $name = (string) ($row['Field'] ?? '');
                if ('' !== $name) {
                    self::$layerColumns[$name] = true;
                }
            }
        }

        return self::$layerColumns[$column] ?? false;
    }
}
