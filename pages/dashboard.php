<?php

/**
 * Geolocation Dashboard – Schnelleinstieg und Statusübersicht.
 */

namespace FriendsOfRedaxo\Geolocation;

use rex;
use rex_addon;
use rex_api_function;
use rex_config;
use rex_csrf_token;
use rex_fragment;
use rex_i18n;
use rex_path;
use rex_sql;
use rex_url;
use rex_view;

/** @var rex_addon $this */

// --- Status-Daten abfragen ---
$sql = rex_sql::factory();

$sql->setQuery('SELECT COUNT(*) AS cnt FROM ' . rex::getTablePrefix() . 'geolocation_layer');
$layerTotal = (int) $sql->getValue('cnt');

$sql->setQuery('SELECT COUNT(*) AS cnt FROM ' . rex::getTablePrefix() . 'geolocation_layer WHERE online = 1');
$layerOnline = (int) $sql->getValue('cnt');

$sql->setQuery('SELECT COUNT(*) AS cnt FROM ' . rex::getTablePrefix() . 'geolocation_mapset');
$mapsetCount = (int) $sql->getValue('cnt');

$defaultMapsetId = (int) rex_config::get(ADDON, 'default_map', 0);

// Cache-Größe
$cacheDir = rex_path::addonCache(ADDON);
$cacheFiles = 0;
$cacheSize = 0;
if (is_dir($cacheDir)) {
    $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($cacheDir, \FilesystemIterator::SKIP_DOTS));
    foreach ($iterator as $file) {
        if ($file->isFile()) {
            $cacheFiles++;
            $cacheSize += $file->getSize();
        }
    }
}
$cacheSizeHuman = $cacheSize > 1048576
    ? round($cacheSize / 1048576, 1) . ' MB'
    : round($cacheSize / 1024, 1) . ' KB';

// Aktuelle Layer-Liste für Proxy-Beispiel
$sql->setQuery('SELECT id, name, online FROM ' . rex::getTablePrefix() . 'geolocation_layer ORDER BY online DESC, name ASC');
$layers = $sql->getArray();

// API-Ergebnis anzeigen (nach Preset-Add)
$apiMessage = rex_api_function::getMessage();

// Proxy-Base-URL
$proxyBase = rex_url::frontendController(['rex-api-call' => 'geolocation_tiles'], false);

// Bekannte Presets: [name, url, subdomain, attribution, lang, layertype]
$presets = PresetManager::getPresets();

// Existierende Layer-URLs (für "bereits vorhanden"-Check)
$sql->setQuery('SELECT url FROM ' . rex::getTablePrefix() . 'geolocation_layer');
$existingUrls = array_column($sql->getArray(), 'url');
?>

<?php if ($apiMessage) { echo $apiMessage; } ?>

<div class="geolocation-dashboard">

    <!-- ============================================================ -->
    <!-- STATUS-KARTEN -->
    <!-- ============================================================ -->
    <div class="row" style="margin-bottom:24px">
        <div class="col-sm-4">
            <div class="panel panel-default geo-stat-card">
                <div class="panel-body text-center">
                    <div class="geo-stat-icon"><i class="fa fa-cloud-download fa-2x text-primary"></i></div>
                    <div class="geo-stat-number"><?= $layerOnline ?> / <?= $layerTotal ?></div>
                    <div class="geo-stat-label"><?= rex_i18n::msg('geolocation_dashboard_stat_layer') ?></div>
                    <a href="<?= rex_url::backendPage('geolocation/layer') ?>" class="btn btn-xs btn-default" style="margin-top:8px">
                        <?= rex_i18n::msg('geolocation_dashboard_manage') ?>
                    </a>
                </div>
            </div>
        </div>
        <div class="col-sm-4">
            <div class="panel panel-default geo-stat-card">
                <div class="panel-body text-center">
                    <div class="geo-stat-icon"><i class="fa fa-map fa-2x text-success"></i></div>
                    <div class="geo-stat-number"><?= $mapsetCount ?></div>
                    <div class="geo-stat-label"><?= rex_i18n::msg('geolocation_dashboard_stat_mapset') ?></div>
                    <?php if (!PROXY_ONLY): ?>
                    <a href="<?= rex_url::backendPage('geolocation/mapset') ?>" class="btn btn-xs btn-default" style="margin-top:8px">
                        <?= rex_i18n::msg('geolocation_dashboard_manage') ?>
                    </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="col-sm-4">
            <div class="panel panel-default geo-stat-card">
                <div class="panel-body text-center">
                    <div class="geo-stat-icon"><i class="fa fa-hdd-o fa-2x text-warning"></i></div>
                    <div class="geo-stat-number"><?= $cacheSizeHuman ?></div>
                    <div class="geo-stat-label"><?= rex_i18n::msg('geolocation_dashboard_stat_cache') ?> (<?= $cacheFiles ?> <?= rex_i18n::msg('geolocation_dashboard_stat_files') ?>)</div>
                    <a href="<?= rex_url::backendController(['page' => 'geolocation/clear_cache', 'rex-api-call' => 'geolocation_clearcache'], false) ?>" class="btn btn-xs btn-delete" style="margin-top:8px">
                        <?= rex_i18n::msg('geolocation_clear_cache') ?>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- ============================================================ -->
    <!-- SCHNELLSTART: LAYER-PRESETS -->
    <!-- ============================================================ -->
    <div class="panel panel-default">
        <div class="panel-heading">
            <h3 class="panel-title"><i class="fa fa-bolt"></i> <?= rex_i18n::msg('geolocation_dashboard_presets_title') ?></h3>
        </div>
        <div class="panel-body">
            <p class="text-muted"><?= rex_i18n::msg('geolocation_dashboard_presets_info') ?></p>
            <div class="row">
                <?php foreach ($presets as $presetKey => $preset): ?>
                <?php $alreadyExists = in_array($preset['url'], $existingUrls, true); ?>
                <div class="col-sm-6 col-md-4" style="margin-bottom:16px">
                    <div class="panel panel-<?= $alreadyExists ? 'success' : 'default' ?>" style="margin:0;height:100%">
                        <div class="panel-body">
                            <h4 style="margin-top:0">
                                <?php if ($preset['free']): ?>
                                    <span class="label label-success" style="font-size:10px;vertical-align:middle">FREE</span>
                                <?php else: ?>
                                    <span class="label label-warning" style="font-size:10px;vertical-align:middle">API-KEY</span>
                                <?php endif; ?>
                                <?= rex_escape($preset['title']) ?>
                            </h4>
                            <p class="text-muted" style="font-size:12px;min-height:36px"><?= rex_escape($preset['description']) ?></p>
                            <?php if ($alreadyExists): ?>
                                <span class="text-success"><i class="fa fa-check"></i> <?= rex_i18n::msg('geolocation_dashboard_preset_exists') ?></span>
                            <?php elseif ($preset['requires_key']): ?>
                                <form method="post" action="<?= rex_url::backendController(['page' => 'geolocation/dashboard', 'rex-api-call' => 'geolocation_add_preset']) ?>">
                                    <?= rex_csrf_token::factory('geolocation_add_preset')->getHiddenField() ?>
                                    <input type="hidden" name="preset" value="<?= rex_escape($presetKey) ?>">
                                    <div class="input-group input-group-sm" style="margin-bottom:4px">
                                        <input type="text" name="api_key" class="form-control" placeholder="<?= rex_i18n::msg('geolocation_dashboard_enter_apikey') ?>">
                                        <span class="input-group-btn">
                                            <button type="submit" class="btn btn-primary btn-sm">
                                                <i class="fa fa-plus"></i> <?= rex_i18n::msg('geolocation_dashboard_preset_add') ?>
                                            </button>
                                        </span>
                                    </div>
                                </form>
                                <a href="<?= rex_escape($preset['key_url'] ?? '#') ?>" target="_blank" rel="noopener" class="text-muted" style="font-size:11px">
                                    <i class="fa fa-external-link"></i> <?= rex_i18n::msg('geolocation_dashboard_get_apikey') ?>
                                </a>
                            <?php else: ?>
                                <form method="post" action="<?= rex_url::backendController(['page' => 'geolocation/dashboard', 'rex-api-call' => 'geolocation_add_preset']) ?>">
                                    <?= rex_csrf_token::factory('geolocation_add_preset')->getHiddenField() ?>
                                    <input type="hidden" name="preset" value="<?= rex_escape($presetKey) ?>">
                                    <button type="submit" class="btn btn-primary btn-sm">
                                        <i class="fa fa-plus"></i> <?= rex_i18n::msg('geolocation_dashboard_preset_add') ?>
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- ============================================================ -->
    <!-- TILE PROXY NUTZEN -->
    <!-- ============================================================ -->
    <div class="panel panel-default">
        <div class="panel-heading">
            <h3 class="panel-title"><i class="fa fa-route"></i> <?= rex_i18n::msg('geolocation_dashboard_proxy_title') ?></h3>
        </div>
        <div class="panel-body">
            <p><?= rex_i18n::msg('geolocation_dashboard_proxy_info') ?></p>

            <?php if (empty($layers)): ?>
                <div class="alert alert-warning"><?= rex_i18n::msg('geolocation_dashboard_no_layers') ?></div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-condensed table-striped">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th><?= rex_i18n::msg('geolocation_layer_title') ?></th>
                            <th><?= rex_i18n::msg('geolocation_dashboard_proxy_url') ?></th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($layers as $layer): ?>
                        <tr class="<?= $layer['online'] ? '' : 'text-muted' ?>">
                            <td><?= (int) $layer['id'] ?></td>
                            <td>
                                <?= rex_escape($layer['name']) ?>
                                <?php if (!$layer['online']): ?>
                                    <span class="label label-default"><?= rex_i18n::msg('geolocation_dashboard_offline') ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <code class="geo-proxy-url" style="font-size:11px;word-break:break-all">
                                    <?= rex_escape($proxyBase) ?>&amp;layer=<?= (int) $layer['id'] ?>&amp;z={z}&amp;x={x}&amp;y={y}
                                </code>
                            </td>
                            <td>
                                <button class="btn btn-xs btn-default geo-copy-btn" data-url="<?= rex_escape($proxyBase) ?>&layer=<?= (int) $layer['id'] ?>&z={z}&x={x}&y={y}">
                                    <i class="fa fa-copy"></i>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="alert alert-info" style="margin-top:8px">
                <strong><?= rex_i18n::msg('geolocation_dashboard_proxy_hint_title') ?>:</strong>
                <?= rex_i18n::msg('geolocation_dashboard_proxy_hint') ?>
                <br>
                <code>https://tile.example.org/{z}/{x}/{y}.png</code>
                &rarr;
                <code><?= rex_escape($proxyBase) ?>&amp;layer=<strong>ID</strong>&amp;z={z}&amp;x={x}&amp;y={y}</code>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ============================================================ -->
    <!-- KARTE EINBINDEN (Code-Snippets) -->
    <!-- ============================================================ -->
    <?php if (!PROXY_ONLY && $mapsetCount > 0): ?>
    <div class="panel panel-default">
        <div class="panel-heading">
            <h3 class="panel-title"><i class="fa fa-code"></i> <?= rex_i18n::msg('geolocation_dashboard_embed_title') ?></h3>
        </div>
        <div class="panel-body">
            <p><?= rex_i18n::msg('geolocation_dashboard_embed_info') ?></p>

            <?php
            // Lade Mapsets für Beispiel
            $mapsets = [];
            $sql->setQuery('SELECT id, name, title FROM ' . rex::getTablePrefix() . 'geolocation_mapset WHERE id > 0 ORDER BY id ASC LIMIT 3');
            $mapsets = $sql->getArray();
            $exampleMapset = $mapsets[0] ?? ['id' => 1, 'name' => 'osm', 'title' => 'OSM'];
            ?>

            <ul class="nav nav-tabs" role="tablist" id="geo-embed-tabs">
                <li role="presentation" class="active"><a href="#geo-tab-php" role="tab" data-toggle="tab">PHP</a></li>
                <li role="presentation"><a href="#geo-tab-html" role="tab" data-toggle="tab">HTML</a></li>
                <li role="presentation"><a href="#geo-tab-marker" role="tab" data-toggle="tab">PHP + Marker</a></li>
            </ul>
            <div class="tab-content" style="border:1px solid #ddd;border-top:0;padding:16px;background:#f9f9f9">
                <div role="tabpanel" class="tab-pane active" id="geo-tab-php">
<pre class="geo-code-block"><code>&lt;?php
use FriendsOfRedaxo\Geolocation\Mapset;

// Einfachste Variante – Kartensatz per Name
echo \FriendsOfRedaxo\Geolocation\Mapset::take('<?= rex_escape($exampleMapset['name']) ?>')->parse();

// Mit Mittelpunkt und Zoom
echo \FriendsOfRedaxo\Geolocation\Mapset::take('<?= rex_escape($exampleMapset['name']) ?>')
    ->attributes('style', 'height:400px')
    ->dataset('lat', 48.137)
    ->dataset('lng', 11.576)
    ->dataset('zoom', 12)
    ->parse();</code></pre>
                </div>
                <div role="tabpanel" class="tab-pane" id="geo-tab-html">
<pre class="geo-code-block"><code>&lt;!-- Karte via HTML-Tag (nach asset-Einbindung) --&gt;
&lt;rex-map 
    mapset="<?= (int) $exampleMapset['id'] ?>"
    style="height:400px"
    data-lat="48.137"
    data-lng="11.576"
    data-zoom="12"&gt;
&lt;/rex-map&gt;</code></pre>
                </div>
                <div role="tabpanel" class="tab-pane" id="geo-tab-marker">
<pre class="geo-code-block"><code>&lt;?php
use FriendsOfRedaxo\Geolocation\Mapset;

echo \FriendsOfRedaxo\Geolocation\Mapset::take('<?= rex_escape($exampleMapset['name']) ?>')
    ->attributes('style', 'height:400px')
    ->dataset('map', [
        'center' => [48.137, 11.576],
        'zoom'   => 12,
    ])
    ->dataset('marker', [
        [48.137, 11.576, 'München Marienplatz'],
    ])
    ->parse();</code></pre>
                </div>
            </div>

            <?php if (!empty($mapsets)): ?>
            <div style="margin-top:12px">
                <strong><?= rex_i18n::msg('geolocation_dashboard_available_mapsets') ?>:</strong>
                <?php foreach ($mapsets as $ms): ?>
                    <a href="<?= rex_url::backendPage('geolocation/mapset', ['data_id' => $ms['id'], 'func' => 'edit']) ?>" class="label label-default" style="margin-left:4px">
                        <?= rex_escape($ms['name']) ?> (ID <?= (int) $ms['id'] ?>)
                    </a>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- ============================================================ -->
    <!-- SCHNELLLINKS -->
    <!-- ============================================================ -->
    <div class="row">
        <div class="col-sm-6">
            <div class="panel panel-default">
                <div class="panel-heading"><h4 class="panel-title"><i class="fa fa-bolt"></i> <?= rex_i18n::msg('geolocation_dashboard_quicklinks') ?></h4></div>
                <div class="list-group" style="border-radius:0 0 4px 4px">
                    <a href="<?= rex_url::backendPage('geolocation/layer', ['func' => 'add']) ?>" class="list-group-item">
                        <i class="fa fa-plus text-primary"></i> <?= rex_i18n::msg('geolocation_dashboard_add_layer') ?>
                    </a>
                    <?php if (!PROXY_ONLY): ?>
                    <a href="<?= rex_url::backendPage('geolocation/mapset', ['func' => 'add']) ?>" class="list-group-item">
                        <i class="fa fa-plus text-success"></i> <?= rex_i18n::msg('geolocation_dashboard_add_mapset') ?>
                    </a>
                    <?php endif; ?>
                    <a href="<?= rex_url::backendPage('geolocation/config') ?>" class="list-group-item">
                        <i class="fa fa-cog text-muted"></i> <?= rex_i18n::msg('geolocation_config') ?>
                    </a>
                    <a href="<?= rex_url::backendPage('geolocation/manual') ?>" class="list-group-item">
                        <i class="fa fa-book text-muted"></i> <?= rex_i18n::msg('geolocation_manpage') ?>
                    </a>
                </div>
            </div>
        </div>
        <div class="col-sm-6">
            <div class="panel panel-default">
                <div class="panel-heading"><h4 class="panel-title"><i class="fa fa-info-circle"></i> <?= rex_i18n::msg('geolocation_dashboard_info_title') ?></h4></div>
                <div class="panel-body" style="font-size:13px">
                    <p><i class="fa fa-check text-success"></i> <?= rex_i18n::msg('geolocation_dashboard_info_proxy') ?></p>
                    <p><i class="fa fa-check text-success"></i> <?= rex_i18n::msg('geolocation_dashboard_info_cache') ?></p>
                    <p><i class="fa fa-check text-success"></i> <?= rex_i18n::msg('geolocation_dashboard_info_picker') ?></p>
                    <hr style="margin:8px 0">
                    <p class="text-muted" style="font-size:11px">
                        Geolocation v<?= rex_addon::get(ADDON)->getVersion() ?> &middot;
                        <a href="https://github.com/FriendsOfREDAXO/geolocation" target="_blank" rel="noopener">GitHub</a>
                    </p>
                </div>
            </div>
        </div>
    </div>

</div><!-- .geolocation-dashboard -->

<style>
.geo-stat-card .panel-body { padding: 20px 10px; }
.geo-stat-icon { margin-bottom: 8px; }
.geo-stat-number { font-size: 2em; font-weight: bold; line-height: 1; }
.geo-stat-label { color: #888; font-size: 12px; margin-top: 4px; }
.geo-code-block { margin: 0; border-radius: 0; background: #f4f4f4; border: 0; }
.geo-proxy-url { display: inline-block; max-width: 100%; }

body.rex-theme-dark .geo-code-block {
    background: #1e1e1e;
    color: #d4d4d4;
}
@media (prefers-color-scheme: dark) {
    body.rex-has-theme:not(.rex-theme-light) .geo-code-block {
        background: #1e1e1e;
        color: #d4d4d4;
    }
}
</style>
<script src="<?= rex_url::addonAssets(ADDON, 'dashboard.js') ?>"></script>
