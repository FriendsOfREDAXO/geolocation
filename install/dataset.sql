--
-- Daten für Tabelle `rex_geolocation_layer`
--
TRUNCATE TABLE `%TABLE_PREFIX%geolocation_layer`;
INSERT INTO `%TABLE_PREFIX%geolocation_layer` (`id`, `name`, `url`, `retinaurl`, `subdomain`, `attribution`, `lang`, `layertype`, `ttl`, `cfmax`, `online`) VALUES
(1, 'HERE: Standardkarte', 'https://{s}.base.maps.ls.hereapi.com/maptile/2.1/maptile/newest/normal.day/{z}/{x}/{y}/256/png8?apiKey=..........', '', '1234', 'Map Tiles &copy; 2020 <a href=\"http://developer.here.com\">HERE</a>', '[[\"de\",\"Karte\"],[\"en\",\"Map\"]]', 'b', 10000, 1000, 1),
(2, 'HERE: Satelit', 'https://{s}.aerial.maps.ls.hereapi.com/maptile/2.1/maptile/newest/satellite.day/{z}/{x}/{y}/256/png8?apiKey=..........', '', '1234', 'Map Tiles &copy; 2020 <a href=\"http://developer.here.com\">HERE</a>', '[[\"de\",\"Satelit\"],[\"en\",\"Satellite\"]]', 'b', 10000, 1000, 1),
(3, 'HERE: Hybrid (Karte+Satelit)', 'https://{s}.aerial.maps.ls.hereapi.com/maptile/2.1/maptile/newest/hybrid.day/{z}/{x}/{y}/256/png8?apiKey=..........', '', '1234', 'Map Tiles &copy; 2020 <a href=\"http://developer.here.com\">HERE</a>', '[[\"de\",\"Hybrid\"],[\"en\",\"Hybrid\"]]', 'b', 1440, 1000, 1),
(4, 'Open Street Map', 'https://{s}.tile.openstreetmap.de/{z}/{x}/{y}.png', '', 'abc', 'Map data: &copy; <a href=\"https://www.openstreetmap.org/\">OpenStreetMap</a> under <a href=\"https://www.openstreetmap.org/copyright\">ODdL</a>', '[[\"de\",\"Karte\"],[\"en\",\"Map\"]]', 'b', 10000, 1000, '1'),
(5, 'Waymarked Trails: Radwege', 'https://tile.waymarkedtrails.org/cycling/{z}/{x}/{y}.png', '', '', '<a href=\"https://cycling.waymarkedtrails.org/">Waymarked Trails</a>', '[[\"de\",\"Radwege\"]]', 'o', 10000, 1000, '1'),
(6, 'OpenRailwayMap', 'https://{s}.tiles.openrailwaymap.org/standard/{z}/{x}/{y}.png', '', 'abc', '&copy; <a href=\"https://openrailwaymap.org/\">OpenRailwayMap</a>', '[[\"de\",\"Bahnstrecken\"]]', 'o', 10000, 1000, '1');

--
-- Daten für Tabelle `rex_geolocation_mapset`
--

TRUNCATE TABLE `%TABLE_PREFIX%geolocation_mapset`;
INSERT INTO `%TABLE_PREFIX%geolocation_mapset` (`id`, `name`, `title`, `layer`, `layer_selected`, `overlay`, `overlay_selected`, `mapoptions`, `outfragment`) VALUES
(1, 'osm', 'Open Street Map', '4', '4', '5,6', '5', 'default', ''),
(2, 'base', 'Basiskarte', '1,2,3', '1', '', '', 'default', '');
