--
-- Daten für Tabelle `rex_geolocation_tiles`
--
TRUNCATE TABLE `rex_geolocation_tiles`;
INSERT INTO `rex_geolocation_tiles` (`id`, `name`, `url`, `subdomain`, `attribution`, `lang`, `layertype`, `ttl`, `cfmax`, `online`) VALUES
(1, 'HERE: Standardkarte', 'https://{s}.base.maps.ls.hereapi.com/maptile/2.1/maptile/newest/normal.day/{z}/{x}/{y}/256/png8?apiKey=..........', '1234', 'Map Tiles &copy; 2020 <a href=\"http://developer.here.com\">HERE</a>', '[[\"de\",\"Karte\"],[\"en\",\"Map\"]]', 'b', 10000, 1000, '1'),
(2, 'HERE: Satelit', 'https://{s}.aerial.maps.ls.hereapi.com/maptile/2.1/maptile/newest/satellite.day/{z}/{x}/{y}/256/png8?apiKey=..........', '1234', 'Map Tiles &copy; 2020 <a href=\"http://developer.here.com\">HERE</a>', '[[\"de\",\"Satelit\"],[\"en\",\"Satellite\"]]', 'b', 10000, 1000, '1'),
(3, 'HERE: Hybrid (Karte+Satelit)', 'https://{s}.aerial.maps.ls.hereapi.com/maptile/2.1/maptile/newest/hybrid.day/{z}/{x}/{y}/256/png8?apiKey=..........', '234', 'Map Tiles &copy; 2020 <a href=\"http://developer.here.com\">HERE</a>', '[[\"de\",\"Hybrid\"],[\"en\",\"Hybrid\"]]', 'b', 1440, 1000, '1'),
(4, 'Open Street Map', 'https://{s}.tile.openstreetmap.de/{z}/{x}/{y}.png', 'abc', 'Map data &copy; <a href=\"http://openstreetmap.org/copyright\">OpenStreetMap contributors</a>', '[[\"de\",\"Karte\"],[\"en\",\"Map\"]]', 'b', 10000, 1000, '1'),
(5, 'CyclOSM - Open Bicycle render', 'https://dev.{s}.tile.openstreetmap.fr/cyclosm/{z}/{x}/{y}.png', 'abc', '<a href=\"https://github.com/cyclosm/cyclosm-cartocss-style/releases\" title=\"CyclOSM - Open Bicycle render\">CyclOSM</a> | Map data: &copy; <a href=\"https://www.openstreetmap.org/copyright\">Ope', '[[\"de\",\"Radwege\"]]', 'o', 10000, 1000, '1'),
(6, 'OpenRailwayMap', 'https://{s}.tiles.openrailwaymap.org/standard/{z}/{x}/{y}.png', 'abc', 'Map data: &copy; <a href=\"https://www.openstreetmap.org/copyright\">OpenStreetMap</a> contributors | Map style: &copy; <a href=\"https://www.Map data: &copy; <a href=\"https://www.openstreetmap.', '[[\"de\",\"Bahnstrecken\"]]', 'o', 10000, 1000, '1');

--
-- Daten für Tabelle `rex_geolocation_maps`
--

TRUNCATE TABLE `rex_geolocation_maps`;
INSERT INTO `rex_geolocation_maps` (`id`, `name`, `title`, `layer`, `overlay`) VALUES
(1, 'base', 'Basiskarte', '1,2,3', ''),
(2, 'osm', 'Open Street Map', '4', '5,6');
