/**
 * Geolocation Demo – Leaflet (Raster) + MapLibre GL JS (Vector) Maps.
 *
 * Liest Konfiguration aus dem data-config Attribut von #geo-demo-root.
 * Kein inline PHP/JS – reine Progressive Enhancement.
 */
(function () {
    'use strict';

    // ----------------------------------------------------------------
    // Config aus DOM lesen (erst zur Init-Zeit)
    // ----------------------------------------------------------------
    var cfg = {};
    var rasterLayers = [];
    var vectorLayers = [];
    var center = [48.137, 11.576];
    var defaultZoom = 12;

    function readConfig() {
        var root = document.getElementById('geo-demo-root');
        if (!root) {
            return false;
        }

        try {
            cfg = JSON.parse(root.getAttribute('data-config') || '{}');
        } catch (e) {
            console.error('Geolocation Demo: Config parse error', e);
            return false;
        }

        rasterLayers = cfg.rasterLayers || [];
        vectorLayers = cfg.vectorLayers || [];
        center = cfg.center || [48.137, 11.576];
        defaultZoom = cfg.zoom || 12;

        return true;
    }

    function showDiagnostics() {
        var root = document.getElementById('geo-demo-root');
        if (!root) return;

        var hasLeaflet = typeof L !== 'undefined';
        var hasMapLibre = typeof maplibregl !== 'undefined';
        var hasOl = typeof ol !== 'undefined';

        if (hasLeaflet && hasMapLibre && hasOl) {
            return;
        }

        var box = document.createElement('div');
        box.className = 'alert alert-warning';
        box.style.marginBottom = '16px';
        box.innerHTML =
            '<strong>Demo-Assets unvollstaendig geladen:</strong> ' +
            'Leaflet: <code>' + (hasLeaflet ? 'ok' : 'fehlt') + '</code>, ' +
            'MapLibre: <code>' + (hasMapLibre ? 'ok' : 'fehlt') + '</code>, ' +
            'OpenLayers: <code>' + (hasOl ? 'ok' : 'fehlt') + '</code>.' +
            ' <span class="text-muted">Bitte Browser-Cache leeren und Seite neu laden.</span>';

        root.parentNode.insertBefore(box, root);
    }

    function safeRun(fn, mapElementId) {
        try {
            fn();
        } catch (e) {
            console.error('Geolocation Demo init failed:', mapElementId, e);
            var el = document.getElementById(mapElementId);
            if (el) {
                showNoLayerWarning(el, 'Initialisierung fehlgeschlagen.', 'Bitte Seite neu laden oder CSP/CDN pruefen');
            }
        }
    }

    // ----------------------------------------------------------------
    // Hilfsfunktionen
    // ----------------------------------------------------------------
    function markLoaded(el) {
        if (el) el.classList.add('geo-map-loaded');
    }

    function buildLeafletTileLayer(layerConfig, L) {
        var opts = {
            attribution: layerConfig.attribution || '',
            maxZoom: 19,
        };
        if (layerConfig.subdomain) {
            // Leaflet braucht subdomains als Array
            opts.subdomains = layerConfig.subdomain.split('');
        }
        return L.tileLayer(layerConfig.proxyUrl, opts);
    }

    function showNoLayerWarning(el, message, hint) {
        if (!el) return;
        el.style.display = 'flex';
        el.style.alignItems = 'center';
        el.style.justifyContent = 'center';
        var msg = message || 'Kein Layer konfiguriert.';
        var sub = hint || 'Dashboard -> Schnellstart nutzen';
        el.innerHTML = '<div style="color:#888;text-align:center;padding:20px">' +
            '<i class="fa fa-warning fa-2x"></i><br>' +
            msg + '<br>' +
            '<small>' + sub + '</small></div>';
        el.classList.add('geo-map-loaded');
    }

    // ----------------------------------------------------------------
    // DEMO 1: Raster – Einfach (Leaflet)
    // ----------------------------------------------------------------
    function initRasterBasic() {
        var el = document.getElementById('geo-demo-raster-basic');
        if (!el) return;
        if (typeof L === 'undefined') {
            showNoLayerWarning(el, 'Leaflet konnte nicht geladen werden.', 'CDN/CSP oder Internetverbindung pruefen');
            return;
        }

        if (rasterLayers.length === 0) {
            showNoLayerWarning(el);
            return;
        }

        var layer = rasterLayers[0];
        var map = L.map(el).setView(center, defaultZoom);
        buildLeafletTileLayer(layer, L).addTo(map);
        markLoaded(el);
    }

    // ----------------------------------------------------------------
    // DEMO 2: Raster – Marker & Popups (Leaflet)
    // ----------------------------------------------------------------
    function initRasterMarker() {
        var el = document.getElementById('geo-demo-marker');
        if (!el) return;
        if (typeof L === 'undefined') {
            showNoLayerWarning(el, 'Leaflet konnte nicht geladen werden.', 'CDN/CSP oder Internetverbindung pruefen');
            return;
        }

        var map = L.map(el).setView(center, defaultZoom);

        if (rasterLayers.length > 0) {
            buildLeafletTileLayer(rasterLayers[0], L).addTo(map);
        }

        var locations = [
            [48.1374, 11.5755, '<strong>Marienplatz</strong><br>Zentrum der Altstadt'],
            [48.1530, 11.5880, '<strong>Englischer Garten</strong><br>Stadtpark'],
            [48.1200, 11.5600, '<strong>Deutsches Museum</strong><br>Technikmuseum'],
            [48.1550, 11.5200, '<strong>Olympiapark</strong><br>1972 Olympiastätte'],
        ];

        locations.forEach(function (loc) {
            L.marker([loc[0], loc[1]])
                .addTo(map)
                .bindPopup(loc[2]);
        });

        // Bounds aus Markern
        var points = locations.map(function (l) { return [l[0], l[1]]; });
        map.fitBounds(points, { padding: [40, 40] });

        markLoaded(el);
    }

    // ----------------------------------------------------------------
    // DEMO 3: Raster – Mehrere Layer / Layer Switcher
    // ----------------------------------------------------------------
    function initRasterMulti() {
        var el = document.getElementById('geo-demo-multi');
        if (!el) return;
        if (typeof L === 'undefined') {
            showNoLayerWarning(el, 'Leaflet konnte nicht geladen werden.', 'CDN/CSP oder Internetverbindung pruefen');
            return;
        }
        if (rasterLayers.length < 2) return;

        var map = L.map(el).setView(center, defaultZoom);
        var baseLayers = {};

        rasterLayers.forEach(function (layer, i) {
            var tileLayer = buildLeafletTileLayer(layer, L);
            baseLayers[layer.name] = tileLayer;
            if (i === 0) tileLayer.addTo(map);
        });

        L.control.layers(baseLayers).addTo(map);
        markLoaded(el);
    }

    // ----------------------------------------------------------------
    // DEMO 4: Vector – MapLibre GL JS (OpenFreeMap)
    // ----------------------------------------------------------------
    var STYLES = {
        liberty:     'https://tiles.openfreemap.org/styles/liberty',
        bright:      'https://tiles.openfreemap.org/styles/bright',
    };

    var vectorMapInstance = null;
    var olMaps = { raster: null, vector: null, wms: null };

    function refreshOlMap(map) {
        if (!map || typeof map.updateSize !== 'function') return;
        setTimeout(function () { map.updateSize(); }, 0);
        setTimeout(function () { map.updateSize(); }, 200);
        setTimeout(function () { map.updateSize(); }, 600);
    }

    function initVectorMap() {
        var el = document.getElementById('geo-demo-vector');
        if (!el) return;
        if (typeof maplibregl === 'undefined') {
            el.innerHTML = '<div style="color:#888;text-align:center;padding:40px">' +
                '<i class="fa fa-exclamation-triangle fa-2x"></i><br>' +
                'MapLibre GL JS nicht geladen.<br>' +
                '<small>Internetverbindung prüfen</small></div>';
            el.classList.add('geo-map-loaded');
            return;
        }

        vectorMapInstance = new maplibregl.Map({
            container: el,
            style: STYLES.liberty,
            center: [center[1], center[0]], // MapLibre: [lng, lat]
            zoom: defaultZoom - 1,
            pitch: 45,      // 3D-Gebäudeansicht (OpenFreeMap Liberty hat Extrusion)
            bearing: -10,   // leichte Rotation für Tiefenwirkung
            attributionControl: true,
        });

        vectorMapInstance.addControl(new maplibregl.NavigationControl({ visualizePitch: true }));
        vectorMapInstance.addControl(new maplibregl.ScaleControl());

        vectorMapInstance.on('load', function () {
            markLoaded(el);

            // Marker mit Popups
            var locations = [
                [48.1374, 11.5755, '<strong>Marienplatz</strong><br>Zentrum der Altstadt'],
                [48.1530, 11.5880, '<strong>Englischer Garten</strong><br>Stadtpark'],
                [48.1200, 11.5600, '<strong>Deutsches Museum</strong><br>Technikmuseum'],
                [48.1550, 11.5200, '<strong>Olympiapark</strong><br>1972 Olympiastätte'],
            ];

            var bounds = new maplibregl.LngLatBounds();
            locations.forEach(function (loc) {
                var popup = new maplibregl.Popup({ offset: 25 })
                    .setHTML(loc[2]);
                new maplibregl.Marker({ color: '#e74c3c' })
                    .setLngLat([loc[1], loc[0]])
                    .setPopup(popup)
                    .addTo(vectorMapInstance);
                bounds.extend([loc[1], loc[0]]);
            });

            vectorMapInstance.fitBounds(bounds, { padding: 60, pitch: 45, bearing: -10, duration: 800 });

            // Koordinaten-Anzeige
            var coordEl = document.getElementById('geo-vector-coords');
            vectorMapInstance.on('mousemove', function (e) {
                if (coordEl) {
                    coordEl.textContent =
                        'Lat: ' + e.lngLat.lat.toFixed(5) +
                        ', Lng: ' + e.lngLat.lng.toFixed(5);
                }
            });
        });
    }

    // Stil-Switcher + 3D-Toggle für Vektorkarte
    function initStyleSwitcher() {
        var switcher = document.getElementById('geo-style-switcher');
        if (!switcher) return;

        switcher.addEventListener('click', function (e) {
            var btn = e.target.closest('button[data-style]');
            if (!btn) return;

            var styleName = btn.getAttribute('data-style');
            var styleUrl  = STYLES[styleName];
            if (!styleUrl || !vectorMapInstance) return;

            // Aktiven Button setzen
            switcher.querySelectorAll('button').forEach(function (b) {
                b.className = 'btn btn-sm btn-default';
            });
            btn.className = 'btn btn-sm btn-primary active';

            // Stil wechseln (Pitch-Zustand nach setStyle wiederherstellen)
            var currentPitch   = vectorMapInstance.getPitch();
            var currentBearing = vectorMapInstance.getBearing();
            vectorMapInstance.setStyle(styleUrl);
            vectorMapInstance.once('styledata', function () {
                vectorMapInstance.easeTo({ pitch: currentPitch, bearing: currentBearing, duration: 0 });
            });
        });

        // 3D-Toggle: Gebäudeextrusion / Draufsicht umschalten
        var toggle3dBtn = document.getElementById('geo-3d-toggle');
        if (toggle3dBtn) {
            // Initialer Zustand: 3D aktiv (pitch = 45)
            toggle3dBtn.className = 'btn btn-sm btn-primary active';

            toggle3dBtn.addEventListener('click', function () {
                if (!vectorMapInstance) return;
                var is3d = vectorMapInstance.getPitch() > 0;
                if (is3d) {
                    vectorMapInstance.easeTo({ pitch: 0, bearing: 0, duration: 600 });
                    toggle3dBtn.className = 'btn btn-sm btn-default';
                } else {
                    vectorMapInstance.easeTo({ pitch: 45, bearing: -10, duration: 600 });
                    toggle3dBtn.className = 'btn btn-sm btn-primary active';
                }
            });
        }
    }

    // ----------------------------------------------------------------
    // DEMO 5a: OpenLayers – Raster XYZ via Proxy
    // ----------------------------------------------------------------
    function initOlRaster() {
        var el = document.getElementById('geo-demo-ol-raster');
        if (!el) return;
        if (typeof ol === 'undefined') {
            showNoLayerWarning(el);
            return;
        }

        var url = rasterLayers.length > 0
            ? rasterLayers[0].proxyUrl.replace('{z}', '{z}').replace('{x}', '{x}').replace('{y}', '{y}')
            : 'https://tile.openstreetmap.org/{z}/{x}/{y}.png';

        // OpenLayers erwartet {z}/{x}/{y} – passt bereits
        olMaps.raster = new ol.Map({
            target: el,
            layers: [
                new ol.layer.Tile({
                    source: new ol.source.XYZ({
                        url: url,
                        attributions: rasterLayers.length > 0
                            ? rasterLayers[0].attribution
                            : '&copy; OpenStreetMap contributors',
                        tileSize: 256,
                        crossOrigin: 'anonymous',
                    }),
                }),
            ],
            view: new ol.View({
                center: ol.proj.fromLonLat([center[1], center[0]]),
                zoom: defaultZoom,
                minZoom: 3,
                maxZoom: 19,
            }),
            controls: ol.control.defaults.defaults(),
        });

        markLoaded(el);
        refreshOlMap(olMaps.raster);
    }

    // ----------------------------------------------------------------
    // DEMO 5b: OpenLayers – Vector Tiles (MVT) + OpenFreeMap-Stil
    // ----------------------------------------------------------------
    function initOlVector() {
        var el = document.getElementById('geo-demo-ol-vector');
        if (!el) return;
        if (typeof ol === 'undefined') {
            showNoLayerWarning(el);
            return;
        }

        // OpenFreeMap liefert MVT-Tiles über öffentliche Tile-Endpunkte
        var vtSource = new ol.source.VectorTile({
            format: new ol.format.MVT(),
            url: 'https://tiles.openfreemap.org/planet/{z}/{x}/{y}.pbf',
            maxZoom: 14,
            attributions: '&copy; <a href="https://openfreemap.org" target="_blank">OpenFreeMap</a>',
        });

        var vtLayer = new ol.layer.VectorTile({
            source: vtSource,
            declutter: true,
        });

        // Einfaches Standard-Styling (Straßen sichtbar machen)
        vtLayer.setStyle(function (feature) {
            var type = feature.getGeometry() ? feature.getGeometry().getType() : '';
            var kind  = feature.get('class') || feature.get('kind') || '';

            if (type === 'LineString' || type === 'MultiLineString') {
                var color = '#bbb';
                var width = 1;
                if (kind === 'motorway' || kind === 'trunk') { color = '#f88'; width = 2; }
                else if (kind === 'primary') { color = '#fbb'; width = 1.5; }
                else if (kind === 'secondary') { color = '#fdd'; width = 1; }
                return new ol.style.Style({
                    stroke: new ol.style.Stroke({ color: color, width: width }),
                });
            }
            if (type === 'Polygon' || type === 'MultiPolygon') {
                var layerName = feature.get('layer') || '';
                if (layerName === 'water') {
                    return new ol.style.Style({ fill: new ol.style.Fill({ color: '#a8d8ea' }) });
                }
                if (layerName === 'landuse') {
                    return new ol.style.Style({ fill: new ol.style.Fill({ color: '#e8f5e9' }) });
                }
                return new ol.style.Style({ fill: new ol.style.Fill({ color: '#f2efe9' }) });
            }
            return null;
        });

        // Hintergrund
        var bgLayer = new ol.layer.Tile({
            source: new ol.source.XYZ({
                url: 'https://tile.openstreetmap.org/{z}/{x}/{y}.png',
                attributions: '&copy; OpenStreetMap',
                opacity: 0.2,
            }),
        });

        olMaps.vector = new ol.Map({
            target: el,
            layers: [bgLayer, vtLayer],
            view: new ol.View({
                center: ol.proj.fromLonLat([center[1], center[0]]),
                zoom: defaultZoom,
                minZoom: 3,
                maxZoom: 18,
            }),
            controls: ol.control.defaults.defaults(),
        });

        markLoaded(el);
        refreshOlMap(olMaps.vector);
    }

    // ----------------------------------------------------------------
    // DEMO 5c: OpenLayers – WMS (terrestris OSM-WMS als Demo)
    // ----------------------------------------------------------------
    function initOlWms() {
        var el = document.getElementById('geo-demo-ol-wms');
        if (!el) return;
        if (typeof ol === 'undefined') {
            showNoLayerWarning(el);
            return;
        }

        olMaps.wms = new ol.Map({
            target: el,
            layers: [
                new ol.layer.Tile({
                    source: new ol.source.TileWMS({
                        url: 'https://ows.terrestris.de/osm/service',
                        params: { LAYERS: 'OSM-WMS', VERSION: '1.1.1' },
                        crossOrigin: 'anonymous',
                        attributions:
                            '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
                    }),
                }),
            ],
            view: new ol.View({
                center: ol.proj.fromLonLat([center[1], center[0]]),
                zoom: defaultZoom,
                minZoom: 3,
                maxZoom: 18,
            }),
            controls: ol.control.defaults.defaults(),
        });

        markLoaded(el);
        refreshOlMap(olMaps.wms);

    }

    // OpenLayers-Tabs: erst beim Tab-Klick initialisieren (Lazy)
    var olInited = { raster: false, vector: false, wms: false };
    function initOlLazy() {
        var tabLinks = document.querySelectorAll('#geo-ol-mode-tabs a[data-toggle="tab"]');
        tabLinks.forEach(function (link) {
            link.addEventListener('shown.bs.tab', function (e) {
                var href = (e.target || link).getAttribute('href');
                if (href === '#geo-ol-raster' && !olInited.raster) {
                    olInited.raster = true;
                    initOlRaster();
                } else if (href === '#geo-ol-raster') {
                    refreshOlMap(olMaps.raster);
                } else if (href === '#geo-ol-vector' && !olInited.vector) {
                    olInited.vector = true;
                    initOlVector();
                } else if (href === '#geo-ol-vector') {
                    refreshOlMap(olMaps.vector);
                } else if (href === '#geo-ol-wms' && !olInited.wms) {
                    olInited.wms = true;
                    initOlWms();
                } else if (href === '#geo-ol-wms') {
                    refreshOlMap(olMaps.wms);
                }
            });
            // Bootstrap 3 verwendet 'shown.bs.tab' – auch direkt binden
            link.addEventListener('click', function () {
                setTimeout(function () {
                    var href = link.getAttribute('href');
                    if (href === '#geo-ol-raster' && !olInited.raster) {
                        olInited.raster = true; initOlRaster();
                    } else if (href === '#geo-ol-vector' && !olInited.vector) {
                        olInited.vector = true; initOlVector();
                    } else if (href === '#geo-ol-wms' && !olInited.wms) {
                        olInited.wms = true; initOlWms();
                    }
                }, 50);
            });
        });
        // Ersten Tab (Raster) sofort initialisieren
        initOlRaster();
        olInited.raster = true;

        window.addEventListener('resize', function () {
            refreshOlMap(olMaps.raster);
            refreshOlMap(olMaps.vector);
            refreshOlMap(olMaps.wms);
        });
    }

    // ----------------------------------------------------------------
    // Copy-Buttons (Proxy-URLs)
    // ----------------------------------------------------------------
    function initCopyButtons() {
        document.addEventListener('click', function (e) {
            var btn = e.target.closest('.geo-copy-btn');
            if (!btn) return;
            var text = btn.getAttribute('data-url') || '';
            if (!text) {
                var td = btn.closest('td');
                var urlEl = td ? td.querySelector('.geo-proxy-url') : null;
                text = urlEl ? urlEl.textContent : '';
            }
            if (!text) return;
            navigator.clipboard.writeText(text.trim()).then(function () {
                var icon = btn.querySelector('i');
                if (icon) {
                    icon.className = 'fa fa-check';
                    setTimeout(function () { icon.className = 'fa fa-copy'; }, 1500);
                }
            }).catch(function (err) {
                console.error('Copy failed', err);
            });
        });
    }

    // ----------------------------------------------------------------
    // Bootstrap – alle Demos initialisieren wenn DOM bereit
    // ----------------------------------------------------------------
    function init() {
        if (!readConfig()) {
            return;
        }

        showDiagnostics();

        safeRun(initRasterBasic, 'geo-demo-raster-basic');
        safeRun(initRasterMarker, 'geo-demo-marker');
        safeRun(initRasterMulti, 'geo-demo-multi');
        safeRun(initVectorMap, 'geo-demo-vector');
        safeRun(initStyleSwitcher, 'geo-demo-vector');
        safeRun(initOlLazy, 'geo-demo-ol-raster');
        safeRun(initCopyButtons, 'geo-demo-root');

        // Scroll-Overlay (Click to activate) - ausschliesslich fuer Non-Leaflet Karten
        window.setTimeout(function() {
            var msg = cfg.i18n && cfg.i18n.clickToActivate ? cfg.i18n.clickToActivate : 'Klicken zum Aktivieren';
            if (typeof Geolocation !== 'undefined' && typeof Geolocation.initScrollOverlay === 'function') {
                ['geo-demo-vector', 'geo-demo-ol-raster', 'geo-demo-ol-vector', 'geo-demo-ol-wms'].forEach(function(id) {
                    var mapEl = document.getElementById(id);
                    if(mapEl) Geolocation.initScrollOverlay(mapEl, msg);
                });
            }
        }, 500);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

})();
