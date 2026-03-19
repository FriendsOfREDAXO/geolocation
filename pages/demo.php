<?php

/**
 * Geolocation Demo-Seite – Raster + Vector Tiles live.
 */

namespace FriendsOfRedaxo\Geolocation;

use rex;
use rex_i18n;
use rex_sql;
use rex_url;

/** @var rex_addon $this */

// Layerdaten aus DB
$sql = rex_sql::factory();
$sql->setQuery('SELECT id, name, url, layertype, subdomain, attribution, online FROM ' . rex::getTablePrefix() . 'geolocation_layer ORDER BY layertype, name ASC');
$allLayers = $sql->getArray();

// Proxy-Base-URL aus der Frontend-Server-URL als root-relative URL ableiten.
$proxyBase = rtrim((string) parse_url(rex::getServer(), PHP_URL_PATH), '/') . '/index.php';

// Layer-Configs für JS aufbereiten
$rasterLayers = [];
$vectorLayers = [];

foreach ($allLayers as $l) {
    if (!$l['online']) {
        continue;
    }
    $isVector = (bool) preg_match('/\.pbf|\.mvt|protobuf|vector-tile/i', $l['url']);
    $isFreeRaster = !$isVector && 1 !== preg_match('/hereapi|maptiler|apikey=|api_key=|access_token=|mapbox/i', (string) $l['url']);
    $layerConfig = [
        'id'          => (int) $l['id'],
        'name'        => $l['name'],
        'layertype'   => $l['layertype'],
        'attribution' => $l['attribution'],
        'subdomain'   => $l['subdomain'],
        'proxyUrl'    => $proxyBase . '?geolayer=' . $l['id'] . '&z={z}&x={x}&y={y}',
        'isFreeRaster' => $isFreeRaster,
    ];
    if ($isVector) {
        $vectorLayers[] = $layerConfig;
    } else {
        $rasterLayers[] = $layerConfig;
    }
}

// Für die Demos nur freie Raster-Layer ohne API-Key verwenden.
$demoRasterLayers = array_values(array_filter($rasterLayers, static fn (array $layer): bool => (bool) ($layer['isFreeRaster'] ?? false)));

// Fallback: immer mindestens ein freier Layer (direkt, ohne API-Key).
if ([] === $demoRasterLayers) {
    $demoRasterLayers[] = [
        'id'          => 0,
        'name'        => 'OpenStreetMap (frei, ohne API-Key)',
        'layertype'   => 'b',
        'attribution' => '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
        'subdomain'   => '',
        'proxyUrl'    => 'https://tile.openstreetmap.org/{z}/{x}/{y}.png',
        'isFreeRaster' => true,
    ];
}

// Hilfsflag aus der JS-Konfiguration entfernen.
$rasterLayers = array_map(static function (array $layer): array {
    unset($layer['isFreeRaster']);
    return $layer;
}, $demoRasterLayers);

// Config-Block für demo.js
$demoConfig = [
    'proxyBase'    => $proxyBase,
    'keyLayer'     => KEY_TILES,
    'rasterLayers' => $rasterLayers,
    'vectorLayers' => $vectorLayers,
    'center'       => [48.137, 11.576],  // München
    'zoom'         => 12,
];

// Proxy-URL für Beispielcode (ersten Raster-Layer nehmen, Fallback OSM)
$exampleLayer = $rasterLayers[0] ?? null;
$hasConfiguredLayers = ($exampleLayer['id'] ?? 0) > 0;
$exampleProxyUrl = $hasConfiguredLayers
    ? $proxyBase . '?geolayer=' . $exampleLayer['id'] . '&z={z}&x={x}&y={y}'
    : 'https://tile.openstreetmap.org/{z}/{x}/{y}.png';

$exampleAttribution = $exampleLayer['attribution'] ?? '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors';
$useProxy = $hasConfiguredLayers;
?>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/maplibre-gl@4.7.1/dist/maplibre-gl.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/ol@10.3.1/ol.css">
<script src="https://cdn.jsdelivr.net/npm/maplibre-gl@4.7.1/dist/maplibre-gl.js"></script>
<script src="https://cdn.jsdelivr.net/npm/ol@10.3.1/dist/ol.js"></script>

<div id="geo-demo-root" data-config="<?= rex_escape(json_encode($demoConfig, JSON_THROW_ON_ERROR)) ?>">

<!-- ===================================================================== -->
<!-- HEADER -->
<!-- ===================================================================== -->
<div class="alert alert-info" style="margin-bottom:24px">
    <strong><i class="fa fa-flask"></i> <?= rex_i18n::msg('geolocation_demo_info_title') ?></strong>
    <?= rex_i18n::msg('geolocation_demo_info_text') ?>
    <?php if (!$hasConfiguredLayers): ?>
    <br><strong class="text-warning"><i class="fa fa-warning"></i> <?= rex_i18n::msg('geolocation_demo_no_layers') ?></strong>
    <a href="<?= rex_url::backendPage('geolocation/dashboard') ?>" class="btn btn-xs btn-default" style="margin-left:8px"><?= rex_i18n::msg('geolocation_demo_add_layers') ?></a>
    <?php endif; ?>
</div>

<div class="panel panel-default" style="margin-bottom:24px">
    <div class="panel-heading">
        <h3 class="panel-title">
            <i class="fa fa-plug text-primary"></i>
            Frontend-Einbindung (gilt fuer alle Code-Beispiele)
        </h3>
    </div>
    <div class="panel-body">
        <p class="text-muted" style="margin-bottom:10px">
            Damit die Snippets im Frontend funktionieren, muessen zuerst die benoetigten Assets eingebunden werden.
            Fuer <code>&lt;rex-map&gt;</code> reicht Geolocation. Fuer die JS-Beispiele (Leaflet/MapLibre/OpenLayers) kommen die Bibliotheken dazu.
        </p>
        <ul style="margin:0 0 14px 18px;padding:0">
            <li>Pflicht fuer alle Maps (inkl. Leaflet): <code>/assets/addons/geolocation/geolocation.min.css</code> und <code>/assets/addons/geolocation/geolocation.min.js</code></li>
            
            <li>Nur fuer MapLibre-Demos: MapLibre CSS/JS</li>
            <li>Nur fuer OpenLayers-Demos: OpenLayers CSS/JS</li>
        </ul>
        <div class="alert alert-info" style="margin-bottom:14px">
            <strong>Alternative zu CDN:</strong>
            Die verwendeten Bibliotheken sind auch direkt ueber GitHub verfuegbar:
            <a href="https://github.com/Leaflet/Leaflet" target="_blank" rel="noopener">Leaflet</a>,
            <a href="https://github.com/maplibre/maplibre-gl-js" target="_blank" rel="noopener">MapLibre GL JS</a>
            und
            <a href="https://github.com/openlayers/openlayers" target="_blank" rel="noopener">OpenLayers</a>.
        </div>
        <div class="row">
            <div class="col-md-6">
<pre class="geo-demo-code"><code class="language-html">&lt;!-- 1) Geolocation-Basis (Pflicht, beinhaltet Leaflet) --&gt;
&lt;link rel="stylesheet" href="/assets/addons/geolocation/geolocation.min.css"&gt;
&lt;script src="/assets/addons/geolocation/geolocation.min.js"&gt;&lt;/script&gt;

&lt;!-- 2) MapLibre (nur fuer Vector/WebGL-Beispiele) --&gt;
&lt;link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/maplibre-gl@4.7.1/dist/maplibre-gl.css"&gt;
&lt;script src="https://cdn.jsdelivr.net/npm/maplibre-gl@4.7.1/dist/maplibre-gl.js"&gt;&lt;/script&gt;

&lt;!-- 3) OpenLayers (nur fuer OL-Beispiele) --&gt;
&lt;link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/ol@10.3.1/ol.css"&gt;
&lt;script src="https://cdn.jsdelivr.net/npm/ol@10.3.1/dist/ol.js"&gt;&lt;/script&gt;</code></pre>
<pre class="geo-demo-code" style="margin-top:12px"><code class="language-text">GitHub-Alternativen:

MapLibre GL JS: https://github.com/maplibre/maplibre-gl-js
OpenLayers: https://github.com/openlayers/openlayers</code></pre>
            </div>
            <div class="col-md-6">
<pre class="geo-demo-code"><code class="language-php">&lt;?php
use FriendsOfRedaxo\Geolocation\Mapset;

// Minimal (ohne eigenes JS):
echo Mapset::take('osm')
    ->attributes('style', 'height:400px')
    ->parse();

// Mit Marker-Daten aus PHP:
echo Mapset::take('osm')
    ->attributes('style', 'height:400px')
    ->dataset('map', ['center' =&gt; [48.137, 11.576], 'zoom' =&gt; 12])
    ->dataset('marker', [
        [48.1374, 11.5755, 'Marienplatz'],
        [48.1530, 11.5880, 'Englischer Garten'],
    ])
    ->parse();</code></pre>
            </div>
        </div>
    </div>
</div>

<!-- ===================================================================== -->
<!-- SECTION 1: RASTER TILE – EINFACH -->
<!-- ===================================================================== -->
<div class="panel panel-default geo-demo-section">
    <div class="panel-heading">
        <h3 class="panel-title">
            <i class="fa fa-picture-o text-primary"></i>
            <?= rex_i18n::msg('geolocation_demo_raster_title') ?>
            <span class="label label-primary" style="margin-left:6px;font-weight:normal;font-size:11px">Leaflet</span>
        </h3>
    </div>
    <div class="panel-body">
        <p class="text-muted"><?= rex_i18n::msg('geolocation_demo_raster_desc') ?></p>
        <div class="row">
            <!-- Karte -->
            <div class="col-md-6">
                <div id="geo-demo-raster-basic" class="geo-demo-map" data-type="raster-basic"></div>
                <p class="text-muted geo-demo-map-hint">
                    <?php if ($exampleLayer): ?>
                        Layer: <strong><?= rex_escape($exampleLayer['name']) ?></strong>
                        (ID <?= $exampleLayer['id'] ?>) via Proxy
                    <?php else: ?>
                        Kein Layer konfiguriert – direkter OSM-Zugriff
                    <?php endif; ?>
                </p>
            </div>
            <!-- Code -->
            <div class="col-md-6">
                <ul class="nav nav-tabs" role="tablist">
                    <li class="active"><a href="#geo-r1-php" data-toggle="tab">PHP (REDAXO)</a></li>
                    <li><a href="#geo-r1-html" data-toggle="tab">HTML Tag</a></li>
                    <li><a href="#geo-r1-leaflet" data-toggle="tab">Leaflet JS</a></li>
                </ul>
                <div class="tab-content geo-demo-code-tabs">
                    <div class="tab-pane active" id="geo-r1-php">
<pre class="geo-demo-code"><code class="language-php">&lt;?php
use FriendsOfRedaxo\Geolocation\Mapset;

// Kartensatz per Name ausgeben
echo Mapset::take('osm')
    ->attributes('style', 'height:400px')
    ->parse();</code></pre>
                    </div>
                    <div class="tab-pane" id="geo-r1-html">
    <pre class="geo-demo-code"><code class="language-html">&lt;!-- Assets im Template einbinden:
        /assets/addons/geolocation/geolocation.min.css
        /assets/addons/geolocation/geolocation.min.js --&gt;

&lt;rex-map
    mapset="<?= $exampleLayer ? $exampleLayer['id'] : 'ID' ?>"
    style="height:400px"&gt;
&lt;/rex-map&gt;</code></pre>
                    </div>
                    <div class="tab-pane" id="geo-r1-leaflet">
<pre class="geo-demo-code"><code class="language-javascript">// Leaflet direkt mit Geolocation-Proxy
const map = L.map('map', { gestureHandling: true }).setView([48.137, 11.576], 12);

L.tileLayer(
    '<?= rex_escape($exampleProxyUrl) ?>',
    {
        attribution: '<?= rex_escape($exampleAttribution) ?>',
        maxZoom: 19
    }
).addTo(map);</code></pre>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ===================================================================== -->
<!-- SECTION 2: MARKER + POPUP -->
<!-- ===================================================================== -->
<div class="panel panel-default geo-demo-section">
    <div class="panel-heading">
        <h3 class="panel-title">
            <i class="fa fa-map-marker text-danger"></i>
            <?= rex_i18n::msg('geolocation_demo_marker_title') ?>
        </h3>
    </div>
    <div class="panel-body">
        <p class="text-muted"><?= rex_i18n::msg('geolocation_demo_marker_desc') ?></p>
        <div class="row">
            <div class="col-md-6">
                <div id="geo-demo-marker" class="geo-demo-map" data-type="raster-marker"></div>
            </div>
            <div class="col-md-6">
                <ul class="nav nav-tabs" role="tablist">
                    <li class="active"><a href="#geo-r2-php" data-toggle="tab">PHP (REDAXO)</a></li>
                    <li><a href="#geo-r2-js" data-toggle="tab">Leaflet JS</a></li>
                </ul>
                <div class="tab-content geo-demo-code-tabs">
                    <div class="tab-pane active" id="geo-r2-php">
<pre class="geo-demo-code"><code class="language-php">&lt;?php
use FriendsOfRedaxo\Geolocation\Mapset;

echo Mapset::take('osm')
    ->attributes('style', 'height:400px')
    ->dataset('map', [
        'center' => [48.137, 11.576],
        'zoom'   => 12,
    ])
    ->dataset('marker', [
        // [lat, lng, 'Popup-Text']
        [48.1374, 11.5755, 'Marienplatz'],
        [48.1530, 11.5880, 'Englischer Garten'],
        [48.1200, 11.5600, 'Deutsches Museum'],
    ])
    ->parse();</code></pre>
                    </div>
                    <div class="tab-pane" id="geo-r2-js">
<pre class="geo-demo-code"><code class="language-javascript">const map = L.map('map', { gestureHandling: true }).setView([48.137, 11.576], 12);

// Tile-Layer via Proxy
L.tileLayer('<?= rex_escape($exampleProxyUrl) ?>', {
    attribution: '<?= rex_escape($exampleAttribution) ?>'
}).addTo(map);

// Marker hinzufügen
const locations = [
    [48.1374, 11.5755, 'Marienplatz'],
    [48.1530, 11.5880, 'Englischer Garten'],
    [48.1200, 11.5600, 'Deutsches Museum'],
];
locations.forEach(([lat, lng, label]) => {
    L.marker([lat, lng])
     .addTo(map)
     .bindPopup(label);
});</code></pre>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ===================================================================== -->
<!-- SECTION 3: MEHRERE LAYER / LAYER-SWITCHER -->
<!-- ===================================================================== -->
<?php if (count($rasterLayers) > 1): ?>
<div class="panel panel-default geo-demo-section">
    <div class="panel-heading">
        <h3 class="panel-title">
            <i class="fa fa-layers text-success"></i>
            <?= rex_i18n::msg('geolocation_demo_multi_title') ?>
        </h3>
    </div>
    <div class="panel-body">
        <p class="text-muted"><?= rex_i18n::msg('geolocation_demo_multi_desc') ?></p>
        <div class="row">
            <div class="col-md-6">
                <div id="geo-demo-multi" class="geo-demo-map" data-type="raster-multi"></div>
                <p class="text-muted geo-demo-map-hint">
                    <?= count($rasterLayers) ?> Layer konfiguriert – Umschalten oben rechts
                </p>
            </div>
            <div class="col-md-6">
<pre class="geo-demo-code"><code class="language-javascript">// Mehrere Layer mit Layer-Control
const map = L.map('map', { gestureHandling: true }).setView([48.137, 11.576], 12);

const layers = {
<?php foreach ($rasterLayers as $i => $l): ?>
    '<?= rex_escape($l['name']) ?>': L.tileLayer(
        '<?= rex_escape($proxyBase) ?>?geolayer=<?= $l['id'] ?>&z={z}&x={x}&y={y}',
        { attribution: '<?= rex_escape($l['attribution']) ?>' }
    )<?= ($i < count($rasterLayers) - 1 ? ',' : '') ?>

<?php endforeach; ?>
};

// Erste Layer als Standard
layers[Object.keys(layers)[0]].addTo(map);

// Layer-Control
L.control.layers(layers).addTo(map);</code></pre>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- ===================================================================== -->
<!-- SECTION 4: VECTOR TILES (MapLibre) -->
<!-- ===================================================================== -->
<div class="panel panel-default geo-demo-section">
    <div class="panel-heading">
        <h3 class="panel-title">
            <i class="fa fa-vector-square text-success"></i>
            <?= rex_i18n::msg('geolocation_demo_vector_title') ?>
            <span class="label label-success" style="margin-left:6px;font-weight:normal;font-size:11px">MapLibre GL JS</span>
            <span class="label label-default" style="margin-left:4px;font-weight:normal;font-size:11px">OpenFreeMap</span>
        </h3>
    </div>
    <div class="panel-body">
        <p class="text-muted"><?= rex_i18n::msg('geolocation_demo_vector_desc') ?></p>

        <div class="row" style="margin-bottom:16px">
            <div class="col-md-4">
                <div class="alert alert-success" style="margin:0">
                    <strong><?= rex_i18n::msg('geolocation_demo_vector_advantages') ?></strong>
                    <ul style="margin:8px 0 0 0;padding-left:18px;font-size:13px">
                        <li>Stufenlos zoombar ohne Pixelrauschen</li>
                        <li>Client-seitig gestylte Karten</li>
                        <li>Viel kleinere Datenmengen</li>
                        <li>Rotation &amp; 3D-Tilt möglich</li>
                    </ul>
                </div>
            </div>
            <div class="col-md-4">
                <div class="alert alert-info" style="margin:0">
                    <strong><?= rex_i18n::msg('geolocation_demo_vector_providers') ?></strong>
                    <ul style="margin:8px 0 0 0;padding-left:18px;font-size:13px">
                        <li><a href="https://openfreemap.org" target="_blank" rel="noopener">OpenFreeMap</a> (kostenlos)</li>
                        <li><a href="https://maptiler.com" target="_blank" rel="noopener">MapTiler Cloud</a> (API-Key)</li>
                        <li><a href="https://protomaps.com" target="_blank" rel="noopener">Protomaps</a> (self-host)</li>
                    </ul>
                </div>
            </div>
            <div class="col-md-4">
                <div class="alert alert-warning" style="margin:0">
                    <strong><?= rex_i18n::msg('geolocation_demo_vector_note') ?></strong>
                    <p style="margin:8px 0 0 0;font-size:13px">
                        MapLibre GL JS benötigt WebGL. Der Geolocation-Proxy
                        funktioniert auch für Vector Tiles (<code>.pbf</code>).
                    </p>
                </div>
            </div>
        </div>

        <!-- Stil-Auswahl + 3D-Toggle -->
        <div style="margin-bottom:12px;display:flex;align-items:center;gap:16px;flex-wrap:wrap">
            <div>
                <label style="font-weight:bold;margin-right:8px"><?= rex_i18n::msg('geolocation_demo_vector_style') ?>:</label>
                <div class="btn-group" role="group" id="geo-style-switcher">
                    <button class="btn btn-sm btn-primary active" data-style="liberty">Liberty</button>
                    <button class="btn btn-sm btn-default" data-style="bright">Bright</button>
                </div>
            </div>
            <div>
                <label style="font-weight:bold;margin-right:8px">3D:</label>
                <button class="btn btn-sm btn-primary active" id="geo-3d-toggle" title="3D-Gebäudeansicht ein/ausschalten">
                    <i class="fa fa-cube"></i> 3D Gebäude
                </button>
            </div>
        </div>

        <div class="row">
            <div class="col-md-6">
                <div id="geo-demo-vector" class="geo-demo-map" data-type="vector"></div>
                <p class="text-muted geo-demo-map-hint">
                    OpenFreeMap · kostenlos · kein API-Key
                    <span style="margin-left:8px;font-size:11px;color:#999" id="geo-vector-coords"></span>
                </p>
            </div>
            <div class="col-md-6">
                <ul class="nav nav-tabs" role="tablist">
                    <li class="active"><a href="#geo-v1-ofm" data-toggle="tab">OpenFreeMap (direkt)</a></li>
                    <?php if (!empty($vectorLayers)): ?>
                    <li><a href="#geo-v1-proxy" data-toggle="tab">Proxy (eigener Layer)</a></li>
                    <?php endif; ?>
                    <li><a href="#geo-v1-maptiler" data-toggle="tab">MapTiler</a></li>
                </ul>
                <p class="text-muted" style="margin:8px 0 0 0;font-size:12px">
                    Einfachste REDAXO-Variante (ohne eigenes JS) bleibt aktuell:
                    <code>Mapset::take('osm')->parse()</code>.
                    Die WebGL-Vector-Demo mit MapLibre benötigt weiterhin JavaScript.
                </p>
                <div class="tab-content geo-demo-code-tabs">
                    <div class="tab-pane active" id="geo-v1-ofm">
<pre class="geo-demo-code"><code class="language-javascript">// MapLibre GL JS – OpenFreeMap Liberty (mit 3D-Gebäuden)
// Kein API-Key, kein Limit, kostenlos
import maplibregl from 'maplibre-gl';
import 'maplibre-gl/dist/maplibre-gl.css';

const map = new maplibregl.Map({
    container: 'map',
    style: 'https://tiles.openfreemap.org/styles/liberty',
    center: [11.576, 48.137],
    zoom: 12,
    pitch: 45,    // 3D-Gebäude sichtbar machen
    bearing: -10  // leichte Rotation für Tiefenwirkung
});

// Navigation inkl. Pitch-Wheel für 3D-Steuerung
map.addControl(
    new maplibregl.NavigationControl({ visualizePitch: true })
);</code></pre>
                    </div>
                    <?php if (!empty($vectorLayers)): ?>
                    <div class="tab-pane" id="geo-v1-proxy">
<pre class="geo-demo-code"><code class="language-javascript">// Vector Tiles via Geolocation-Proxy
// Layer-ID: <?= $vectorLayers[0]['id'] ?> (<?= rex_escape($vectorLayers[0]['name']) ?>)

const map = new maplibregl.Map({
    container: 'map',
    center: [11.576, 48.137],
    zoom: 12,
    style: {
        version: 8,
        sources: {
            'vector-proxy': {
                type: 'vector',
                tiles: [
                    '<?= rex_escape($vectorLayers[0]['proxyUrl']) ?>'
                ],
                maxzoom: 14
            }
        },
        layers: [{
            id: 'background',
            type: 'background',
            paint: { 'background-color': '#f8f4f0' }
        }, {
            id: 'roads',
            type: 'line',
            source: 'vector-proxy',
            'source-layer': 'transportation',
            paint: { 'line-color': '#aaa', 'line-width': 1 }
        }]
    }
});</code></pre>
                    </div>
                    <?php endif; ?>
                    <div class="tab-pane" id="geo-v1-maptiler">
<pre class="geo-demo-code"><code class="language-javascript">// MapLibre GL JS – MapTiler Cloud
// Erst API-Key anlegen: https://cloud.maptiler.com
import maplibregl from 'maplibre-gl';
import { config } from '@maptiler/sdk';

config.apiKey = 'IHR_API_KEY';

const map = new maplibregl.Map({
    container: 'map',
    style: 'https://api.maptiler.com/maps/streets/style.json?key=IHR_API_KEY',
    center: [11.576, 48.137],
    zoom: 12
});</code></pre>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ===================================================================== -->
<!-- SECTION 5: OPENLAYERS -->
<!-- ===================================================================== -->
<div class="panel panel-default geo-demo-section">
    <div class="panel-heading">
        <h3 class="panel-title">
            <i class="fa fa-globe text-info"></i>
            <?= rex_i18n::msg('geolocation_demo_ol_title') ?>
            <span class="label label-info" style="margin-left:6px;font-weight:normal;font-size:11px">OpenLayers 10</span>
        </h3>
    </div>
    <div class="panel-body">
        <p class="text-muted"><?= rex_i18n::msg('geolocation_demo_ol_desc') ?></p>

        <!-- Tabs: Raster / Vector / WMS -->
        <ul class="nav nav-pills" id="geo-ol-mode-tabs" style="margin-bottom:16px">
            <li class="active"><a href="#geo-ol-raster" data-toggle="tab">
                <i class="fa fa-picture-o"></i> Raster (XYZ)
            </a></li>
            <li><a href="#geo-ol-vector" data-toggle="tab">
                <i class="fa fa-vector-square"></i> Vector Tiles (MVT)
            </a></li>
            <li><a href="#geo-ol-wms" data-toggle="tab">
                <i class="fa fa-server"></i> WMS
            </a></li>
        </ul>

        <div class="tab-content">

            <!-- OL: Raster -->
            <div class="tab-pane active" id="geo-ol-raster">
                <div class="row">
                    <div class="col-md-6">
                        <div id="geo-demo-ol-raster" class="geo-demo-map" data-type="ol-raster"></div>
                        <p class="text-muted geo-demo-map-hint">
                            XYZ-Raster via Geolocation-Proxy · OpenLayers
                            <?php if ($exampleLayer): ?>
                                (Layer: <?= rex_escape($exampleLayer['name']) ?>)
                            <?php endif; ?>
                        </p>
                    </div>
                    <div class="col-md-6">
<pre class="geo-demo-code"><code class="language-javascript">import Map from 'ol/Map';
import View from 'ol/View';
import TileLayer from 'ol/layer/Tile';
import XYZ from 'ol/source/XYZ';
import { fromLonLat } from 'ol/proj';

const map = new ol.Map({
    target: 'map',
    layers: [
        new ol.layer.Tile({
            source: new ol.source.XYZ({
                url: '<?= rex_escape($exampleProxyUrl) ?>',
                attributions: '<?= rex_escape($exampleAttribution) ?>',
            })
        })
    ],
    view: new ol.View({
        center: ol.proj.fromLonLat([11.576, 48.137]),
        zoom: 12
    })
});</code></pre>
                    </div>
                </div>
            </div>

            <!-- OL: Vector Tiles (MVT) -->
            <div class="tab-pane" id="geo-ol-vector">
                <div class="row">
                    <div class="col-md-6">
                        <div id="geo-demo-ol-vector" class="geo-demo-map" data-type="ol-vector"></div>
                        <p class="text-muted geo-demo-map-hint">
                            MVT Vector Tiles · OpenLayers + OpenFreeMap
                        </p>
                    </div>
                    <div class="col-md-6">
<pre class="geo-demo-code"><code class="language-javascript">import Map from 'ol/Map';
import VectorTileLayer from 'ol/layer/VectorTile';
import VectorTileSource from 'ol/source/VectorTile';
import MVT from 'ol/format/MVT';
import { applyStyle } from 'ol-mapbox-style';

// Stil aus MapLibre/Mapbox Style JSON übernehmen:
const layer = new ol.layer.VectorTile({});
applyStyle(
    layer,
    'https://tiles.openfreemap.org/styles/liberty',
    { resolutions: view.getResolutions() }
);

const map = new ol.Map({
    target: 'map',
    layers: [layer],
    view: new ol.View({
        center: ol.proj.fromLonLat([11.576, 48.137]),
        zoom: 12
    })
});</code></pre>
                    </div>
                </div>
            </div>

            <!-- OL: WMS -->
            <div class="tab-pane" id="geo-ol-wms">
                <div class="row">
                    <div class="col-md-6">
                        <div id="geo-demo-ol-wms" class="geo-demo-map" data-type="ol-wms"></div>
                        <p class="text-muted geo-demo-map-hint">
                            WMS (Web Map Service) · OpenLayers · Demo: OSM-WMS
                        </p>
                    </div>
                    <div class="col-md-6">
<pre class="geo-demo-code"><code class="language-javascript">// WMS-Layer (z.B. GeoServer, QGIS Server, GeoPortal)
const map = new ol.Map({
    target: 'map',
    layers: [
        new ol.layer.Tile({
            source: new ol.source.TileWMS({
                url: 'https://ows.terrestris.de/osm/service',
                params: {
                    LAYERS: 'OSM-WMS',
                    TILED: true,
                },
                serverType: 'geoserver',
                attributions:
                    '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
            })
        })
    ],
    view: new ol.View({
        center: ol.proj.fromLonLat([11.576, 48.137]),
        zoom: 12
    })
});</code></pre>
                    </div>
                </div>
            </div>

        </div><!-- .tab-content -->

        <!-- Vergleich der Renderer -->
        <div class="table-responsive" style="margin-top:16px">
            <table class="table table-condensed table-bordered" style="font-size:12px">
                <thead>
                    <tr class="active">
                        <th></th>
                        <th><i class="fa fa-leaf"></i> Leaflet</th>
                        <th><i class="fa fa-globe"></i> OpenLayers</th>
                        <th><i class="fa fa-vector-square"></i> MapLibre GL</th>
                    </tr>
                </thead>
                <tbody>
                    <tr><td><strong>Raster XYZ</strong></td>
                        <td class="text-success">✔ Nativ</td><td class="text-success">✔ Nativ</td><td class="text-success">✔ Nativ</td></tr>
                    <tr><td><strong>Vector MVT</strong></td>
                        <td class="text-muted">— Plugin</td><td class="text-success">✔ Nativ</td><td class="text-success">✔ Nativ (WebGL)</td></tr>
                    <tr><td><strong>WMS/WFS</strong></td>
                        <td class="text-success">✔ Nativ</td><td class="text-success">✔ Nativ</td><td class="text-muted">— Manuell</td></tr>
                    <tr><td><strong>GeoJSON</strong></td>
                        <td class="text-success">✔ Nativ</td><td class="text-success">✔ Nativ</td><td class="text-success">✔ Nativ</td></tr>
                    <tr><td><strong>WebGL / 3D-Tilt</strong></td>
                        <td class="text-muted">—</td><td class="text-muted">—</td><td class="text-success">✔ Nativ</td></tr>
                    <tr><td><strong>Bundle-Größe</strong></td>
                        <td>~144 KB</td><td>~550 KB</td><td>~320 KB</td></tr>
                    <tr><td><strong>Lernkurve</strong></td>
                        <td class="text-success">Niedrig</td><td class="text-warning">Mittel</td><td class="text-warning">Mittel</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- ===================================================================== -->
<!-- SECTION 6: PROXY ERKLÄRT -->
<!-- ===================================================================== -->
<div class="panel panel-default geo-demo-section">
    <div class="panel-heading">
        <h3 class="panel-title">
            <i class="fa fa-route text-warning"></i>
            <?= rex_i18n::msg('geolocation_demo_proxy_title') ?>
        </h3>
    </div>
    <div class="panel-body">
        <div class="row">
            <div class="col-md-6">
                <h4><?= rex_i18n::msg('geolocation_demo_proxy_how') ?></h4>
                <div class="geo-flow-diagram">
                    <div class="geo-flow-step geo-flow-browser">
                        <i class="fa fa-globe fa-lg"></i> Browser
                        <small>Leaflet / MapLibre</small>
                    </div>
                    <div class="geo-flow-arrow"><i class="fa fa-long-arrow-right"></i> Tile-Request</div>
                    <div class="geo-flow-step geo-flow-redaxo">
                        <i class="fa fa-server fa-lg"></i> REDAXO Proxy
                        <small>index.php?geolayer=…</small>
                    </div>
                    <div class="geo-flow-arrow"><i class="fa fa-long-arrow-right"></i> Fetch (cURL)</div>
                    <div class="geo-flow-step geo-flow-tileserver">
                        <i class="fa fa-map fa-lg"></i> Tile-Server
                        <small>OSM / HERE / OFM</small>
                    </div>
                </div>
                <ul class="geo-proxy-benefits">
                    <li><i class="fa fa-check text-success"></i> Kein CORS-Problem</li>
                    <li><i class="fa fa-check text-success"></i> API-Keys bleiben server-seitig</li>
                    <li><i class="fa fa-check text-success"></i> Automatisches Caching</li>
                    <li><i class="fa fa-check text-success"></i> Funktioniert für Raster <em>und</em> Vector Tiles</li>
                </ul>
            </div>
            <div class="col-md-6">
                <h4>URL-Schema</h4>
<pre class="geo-demo-code" style="font-size:12px"><code><?= rex_escape($proxyBase) ?>?geolayer=<strong>LAYER_ID</strong>&amp;z={z}&amp;x={x}&amp;y={y}

├── LAYER_ID  ID des Layers aus "Karte"-Tab
├── z         Zoom-Level
├── x         Tile-Spalte
└── y         Tile-Zeile

# Beispiele:
<?php foreach (array_slice($rasterLayers + $vectorLayers, 0, 3) as $l): ?>
<?= rex_escape($proxyBase) ?>?geolayer=<?= $l['id'] ?>&amp;z=12&amp;x=2201&amp;y=1423  → <?= rex_escape($l['name']) ?>

<?php endforeach; ?>
<?php if (empty($rasterLayers) && empty($vectorLayers)): ?>
# → Zuerst Layer anlegen (Dashboard → Schnellstart)
<?php endif; ?></code></pre>
            </div>
        </div>
    </div>
</div>

</div><!-- #geo-demo-root -->

<style>
/* Demo-spezifisches Styling */
.geo-demo-map {
    height: 400px;
    border: 1px solid #ddd;
    border-radius: 4px;
    background: #f4f4f4;
    position: relative;
}
.geo-demo-map::before {
    content: '\f041';
    font-family: FontAwesome;
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    font-size: 32px;
    color: #ccc;
    z-index: 1;
}
.geo-demo-map.geo-map-loaded::before {
    display: none;
}
.geo-demo-map-hint {
    font-size: 11px;
    margin: 4px 0 0 0;
    color: #888;
}
.geo-demo-code {
    margin: 0;
    border-radius: 0 0 4px 4px;
    background: #2b2b2b;
    color: #f8f8f2;
    font-size: 12px;
    max-height: 340px;
    overflow: auto;
}
.geo-demo-code-tabs {
    border: 1px solid #ddd;
    border-top: 0;
}
.geo-demo-code code {
    background: transparent;
    color: inherit;
    padding: 0;
    white-space: pre;
}
.geo-demo-section {
    margin-bottom: 24px;
}

/* Proxy-Flow */
.geo-flow-diagram {
    display: flex;
    align-items: center;
    gap: 8px;
    margin: 16px 0;
    flex-wrap: wrap;
}
.geo-flow-step {
    border: 2px solid #ddd;
    border-radius: 8px;
    padding: 10px 14px;
    text-align: center;
    flex: 1;
    min-width: 90px;
    display: flex;
    flex-direction: column;
    gap: 4px;
}
.geo-flow-step small { font-size: 10px; color: #888; }
.geo-flow-browser { border-color: #5bc0de; background: #f0faff; }
.geo-flow-redaxo { border-color: #d9534f; background: #fff5f5; }
.geo-flow-tileserver { border-color: #5cb85c; background: #f5fff5; }
.geo-flow-arrow { color: #888; font-size: 11px; text-align: center; white-space: nowrap; }
.geo-proxy-benefits { list-style: none; padding: 0; margin-top: 12px; }
.geo-proxy-benefits li { padding: 4px 0; }

/* Dark Mode */
body.rex-theme-dark .geo-demo-map { background: #1a1a1a; border-color: #444; }
body.rex-theme-dark .geo-demo-map::before { color: #444; }
body.rex-theme-dark .geo-flow-browser { background: #1a2833; }
body.rex-theme-dark .geo-flow-redaxo { background: #2b1a1a; }
body.rex-theme-dark .geo-flow-tileserver { background: #1a2b1a; }

body.rex-theme-dark .geo-demo-code-tabs {
    border-color: #333;
}
body.rex-theme-dark #geo-demo-root .nav-tabs {
    border-bottom-color: #333;
}
body.rex-theme-dark #geo-demo-root .nav-tabs > li.active > a,
body.rex-theme-dark #geo-demo-root .nav-tabs > li.active > a:focus,
body.rex-theme-dark #geo-demo-root .nav-tabs > li.active > a:hover {
    background-color: #2b2b2b;
    border-color: #333 #333 transparent;
    color: #fff;
}
body.rex-theme-dark #geo-demo-root .nav-tabs > li > a:not(.active):hover {
    border-color: #333;
    background-color: #202020;
    color: #fff;
}
body.rex-theme-dark #geo-demo-root .nav-tabs > li > a {
    color: #999;
}

@media (prefers-color-scheme: dark) {
    body.rex-has-theme:not(.rex-theme-light) .geo-demo-map { background: #1a1a1a; border-color: #444; }
    body.rex-has-theme:not(.rex-theme-light) .geo-demo-map::before { color: #444; }
    body.rex-has-theme:not(.rex-theme-light) .geo-flow-browser { background: #1a2833; }
    body.rex-has-theme:not(.rex-theme-light) .geo-flow-redaxo { background: #2b1a1a; }
    body.rex-has-theme:not(.rex-theme-light) .geo-flow-tileserver { background: #1a2b1a; }

    body.rex-has-theme:not(.rex-theme-light) .geo-demo-code-tabs {
        border-color: #333;
    }
    body.rex-has-theme:not(.rex-theme-light) #geo-demo-root .nav-tabs {
        border-bottom-color: #333;
    }
    body.rex-has-theme:not(.rex-theme-light) #geo-demo-root .nav-tabs > li.active > a,
    body.rex-has-theme:not(.rex-theme-light) #geo-demo-root .nav-tabs > li.active > a:focus,
    body.rex-has-theme:not(.rex-theme-light) #geo-demo-root .nav-tabs > li.active > a:hover {
        background-color: #2b2b2b;
        border-color: #333 #333 transparent;
        color: #fff;
    }
    body.rex-has-theme:not(.rex-theme-light) #geo-demo-root .nav-tabs > li > a:not(.active):hover {
        border-color: #333;
        background-color: #202020;
        color: #fff;
    }
    body.rex-has-theme:not(.rex-theme-light) #geo-demo-root .nav-tabs > li > a {
        color: #999;
    }
}
</style>
