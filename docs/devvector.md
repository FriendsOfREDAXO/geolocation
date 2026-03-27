> **Hauptmenü**
> - [Installation und Einstellungen](install.md)
>   - [Installation](install.md)
>   - [Einstellungen](settings.md)
> - [Kartensätze verwalten](mapset.md)
> - [Karten/Layer verwalten](layer.md)
> - [Proxy und -Cache](proxy_cache.md)
> - [Für Entwickler](devphp.md)
>   - [PHP Basis](devphp.md)
>   - [PHP LocationPicker](devphp1.md)
>   - [Javascript](devjs.md)
>   - [JS-Tools](devtools.md)
>   - [geoJSON](devgeojson.md)
>   - [Rechnen (PHP)](devmath.md)
>   - __Vektorkarten__

# Vektorkarten (MapLibre / OpenFreeMap)

Das Addon *Geolocation* baut im Frontend standardmäßig auf der Bibliothek **Leaflet** auf, die primär für Rasterkarten (`.png`, `.webp`) ausgelegt ist. 

Die Proxy-Funktion des Addons wurde jedoch so erweitert, dass sie auch moderne **Vektorkarten** (`.mvt`, `.pbf`) vollständig und DSGVO-konform bereitstellen kann. Hierbei werden nicht nur die Map-Kacheln, sondern auch alle Abhängigkeiten wie Styles (`.json`), Fonts (`.pbf`) und Symbole/Sprites (`.png`/`.json`) über den lokalen REDAXO-Proxy geleitet.

## Was sind Vector Tiles und OpenFreeMap?

[OpenFreeMap](https://openfreemap.org/) ist ein kostenloser Anbieter für OpenStreetMap-basierte Vektorkarten. In Kombination mit [MapLibre GL JS](https://maplibre.org/) lassen sich hochauflösende, stufenlos zoombare Karten im Browser einbinden.

Da externe API-Aufrufe an `tiles.openfreemap.org` ohne Proxy die IP-Adressen der Nutzer ins Ausland senden würden, bietet Geolocation die Möglichkeit, alle Aufrufe lokal über REDAXO abzufangen und zu cachen.

## Konfiguration eines Vektor-Layers im Addon

Damit REDAXO als **vollständiger Proxy** fungieren kann, nutzt der Layer einen dynamischen `{req}`-Parameter anstelle der klassischen `{z}/{x}/{y}` Struktur.

1. **Neuen Layer anlegen**
2. **Titel:** `OpenFreeMap (Liberty)`
3. **URL-Struktur:** `https://tiles.openfreemap.org/{req}`
4. Karte online schalten.

> ⚠️ **Wichtiger Hinweis zu Updates:** 
> Bei einer komplett frischen Installation oder Re-Installation wird dieser Layer ab der Version 2.6.0 automatisch als **Layer ID 7** in der Datenbank angelegt. Wenn du das Addon in einem bestehenden System jedoch per Update aktualisierst, wird deine bestehende Datenbank nicht angetastet (um Konflikte zu vermeiden) und du musst den Layer manuell exakt nach dem obigen Schema aufsetzen.

## MapLibre Integration im eigenen Frontend

MapLibre bringt eine nützliche Hook-Funktion namens `transformRequest` mit. Mit dieser lassen sich URLs direkt im Browser überschreiben, *bevor* MapLibre sie aufruft. 

Wir weisen damit MapLibre an, jeden Aufruf an `tiles.openfreemap.org` durch unseren Geolocation Proxy zu leiten (hierbei greifen wir auf Layer ID `7` zurück):

```html
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <title>Geolocation Vector Tile Proxy</title>
    <!-- MapLibre JS & CSS -->
    <script src="https://cdn.jsdelivr.net/npm/maplibre-gl@3.6.2/dist/maplibre-gl.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/maplibre-gl@3.6.2/dist/maplibre-gl.css" rel="stylesheet" />
    
    <style>
        body { margin: 0; padding: 0; }
        #map { position: absolute; top: 0; bottom: 0; width: 100%; }
    </style>
</head>
<body>
    <div id="map"></div>

    <script>
        // REDAXO Geolocation Vektor-Proxy Modus!
        // Wir fangen alle Anfragen an "tiles.openfreemap.org" (Styles, Fonts, Sprites, Tiles) ab
        // und leiten diese durch den REDAXO Geolocation Proxy (Layer ID 7).
        
        function redirectOpenFreeMap(url) {
            if (url.includes('tiles.openfreemap.org/')) {
                // Den Teil nach der Domain extrahieren (z.B. "planet/...", "styles/...", "fonts/...")
                const reqPath = url.split('tiles.openfreemap.org/')[1];
                
                return { 
                    // Weiterleitung über den eigenen REDAXO Server!
                    url: window.location.origin + '/index.php?geolayer=7&req=' + encodeURIComponent(reqPath) 
                };
            }
            return { url: url };
        }

        const map = new maplibregl.Map({
            container: 'map',
            // style-URL kann originär belassen werden, transformRequest greift sofort!
            style: 'https://tiles.openfreemap.org/styles/liberty', 
            center: [8.682127, 50.110924], // Frankfurt
            zoom: 12,
            transformRequest: (url) => redirectOpenFreeMap(url)
        });
        
        // Optionale Controls (Beispiel Zoom, Rotation)
        map.addControl(new maplibregl.NavigationControl());
    </script>
</body>
</html>
```

### Wie funktioniert das Vektor-Caching?

Die Raster-Proxy-Verwaltung (`lib/Layer.php`) erkennt bei der Abfrage den speziellen `&req=` Parameter.
Um Ordner-Strukturproblemen (Vorbeugen von zu tiefen Pfaden wie `fonts/Noto Sans Italic/0-255.pbf`) vorzubeugen, speichert REDAXO diese Requests im Cache-Order einfach als **MD5-Hashes**. 
Somit ist das Proxying vollkommen flexibel und speichert die Ressourcen performant als flache Dateien neben den klassischen Kacheln.