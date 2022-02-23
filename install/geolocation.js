/* Geolocation-Addon V 1.0 for use with REDAXO 5.13 and above
*/

// Namespace "Geolocation"
var Geolocation = {

    default: {  // Default-Werte
        keyMapset: %keyMapset%,
        keyLayer: %keyLayer%,
        mapOptions:
            {
                minZoom:%zoomMin%,
                maxZoom:%zoomMax%,
                scrollWheelZoom:true,
                gestureHandling:%defaultGestureHandling%,
                locateControl: %defaultLocateControl%,
                fullscreen:%defaultFullscreen%
            },
        bounds: [%defaultBounds%],
        boundsRect: {fill:false,stroke:false},
        markerColor: 'cornflowerblue',
        positionColor: 'red',
        zoom: %defaultZoom%,
        locationMarker:
            {
                className:   'geolocation-locate-location',
                weight:      3,
                radius:      9
            },
        locationMarkerCircle:
            {
                className:   'geolocation-locate-accuracy',
            },
    },

    icon: {},       // Icons (SVG)

    Classes: {},    // Klassen
    classes: {},    // Factory-Funktionen für Klassen

    Tools: {},      // Die Klassen für Map-Tools
    tools: {},      // Factory-Funktionen für Map-Tools

    cLang: (window.navigator.userLanguage || window.navigator.language).substr(0,2),
    lang: {%i18n%}, // Sprachen / Textübersetzung
    i18n: function( t, data ) {
        let db = this.lang[this.cLang];
        if( db ) {
            let lct = t.toLowerCase();
            if( db[lct] ) t = db[lct];
        }
        if( data instanceof Object ) {
            try {
                t = t.replace(/\{ *([\w_-]+) *\}/g, function (t,key) {return data[key] || '{'+key+'}';});
            } catch (e) {
                // void
            }
        }
        return t;
    },

    sPath: document.currentScript.src.substr(0,document.currentScript.src.lastIndexOf('/')+1),

};

Geolocation.icon.fullscreenOn = '<svg viewBox="0 0 13 13" stroke="currentColor" fill="transparent" xmlns="http://www.w3.org/2000/svg"><path d="M5,1h-4v4m0,3v4h4m3,0h4v-4m0,-3v-4h-4" stroke-width="2"></svg>';
Geolocation.icon.fullscreenOff = '<svg viewBox="0 0 13 13" stroke="currentColor" fill="transparent" xmlns="http://www.w3.org/2000/svg"><path d="M5,1h-4v4m0,3v4h4m3,0h4v-4m0,-3v-4h-4M5.5,5.5h2v2h-2v-3" stroke-width="2"></svg>';
Geolocation.icon.focusR = '<svg viewBox="0 0 51 51" stroke="currentColor"fill="transparent" xmlns="http://www.w3.org/2000/svg"><path d="M7,7h37v37h-37zM0,25.5h15M25.5,0v15M51,25.5h-15M25.5,51v-15"stroke-width="3"/></svg>';
Geolocation.icon.locate = '<svg viewBox="0 0 50 50" stroke="currentColor"fill="transparent" xmlns="http://www.w3.org/2000/svg"><circle cx="25"cy="25"r="5"fill="currentColor"/><path d="M7,25a18,18 0 1,0 36,0a-18,18 0 1,0 -36,0M0,25h15M35,25h15M25,0v15M25,35v15"stroke-width="3"/></svg>';

//-- Hilfsfunktionen -------------------------------------------------------------------------------

// sichere Umwandeln eines (hoffentlich) JSON-String in ein Objekt
// data         String mit JSON-Content

Geolocation.fromJSON = function( data, def={} ){
    let result;
    try {
        result = JSON.parse( data ) || def;
    } catch (e) {
        result = def;
    }
    return result;
}

// Hier wird ein SVG-Pin zusammengebaut
// data         String mit JSON-Content

Geolocation.svgIconPin = function( color, nr, nrcolor ){
    color = color || 'cornflowerblue';
    nr = nr || '';
    nrcolor = nrcolor || 'red';

    // Basic-SVG
    let svg =
    'data:image/svg+xml,%3Csvg%20viewBox=%220%200%2062%20101%22%20fill=%22transparent%22%20xmlns=%22http://www.w3.org/2000/svg%22%3E%3Cdefs%3E%3ClinearGradient%20id=%22gradient%22%20x1=%2255%25%22%20y1=%22126%25%22%20x2=%220%25%22%20y2=%220%25%22%3E%3Cstop%20offset=%220%25%22%20style=%22stop-color:DarkSlateGray;stop-opacity:1;%22/%3E%3Cstop%20offset=%2250%25%22%20style=%22stop-color:'
    + color +
    ';stop-opacity:1;%22/%3E%3C/linearGradient%3E%3C/defs%3E%3Cpath%20fill=%22url(%23gradient)%22%20stroke=%22black%22%20stroke-width=%220.5%22%20d=%22M31,1a30,30,0,0,1,27.5,42.4L31,101L3.4,43.4a30,30,0,0,1,27.6,-42.4%22/%3E';

    // Falls nr angegeben: größere Öffnung mit Text
    // sonst kleine Öffnung
    if( nr ) {
        svg = svg +
        '%3Cpath%20fill=%22white%22%20stroke=%22black%22%20stroke-width=%220.5%22%20d=%22M31,12a19,19,0,1,0,0,38a19,19,0,1,0,0,-38%22/%3E%3Ctext%20font-size=%2230px%22%20font-family=%22sans-serif%22%20font-weight=%22bolder%22%20fill=%22'
        + nrcolor +
        '%22%20text-anchor=%22middle%22%20alignment-baseline=%22central%22%3E%3Ctspan%20x=%2231%22%20y=%2241%22%3E'
        + nr +
        '%3C/tspan%3E%3C/text%3E';
    } else {
        svg = svg +
        '%3Cpath%20fill=%22white%22%20stroke=%22black%22%20stroke-width=%220.5%22%20d=%22M31,18a10,10,0,1,0,0,20a10,10,0,1,0,0,-20%22/%3E'
    }
    svg = svg + '%3C/svg%3E';
    return L.icon( {
        iconUrl: svg,
        iconAnchor:[12,41],
        iconSize:[25,41],
        popupAnchor:[1,-34],
        tooltipAnchor:[16,-28],
        shadowSize: [41,41],
        shadowUrl: Geolocation.sPath+'images/marker-shadow.png',
    });
}


// DIV analog zu <rex-map> initialisieren
//
//      <div class="..." map="«JSON-MapOptions»" dataset="«JSON-Dataset/Content/Tools»" mapset="«JSOM-Mapset/Layer»">
//      </div>
//
// Die Leaflet-Karte selbst ist als container._map verfügbar
// Die Geolocation-Wrapper-Klasse ist als container.__rmMap verfügbar
//      container.__rmMap.setDataset( ... )        -> Inhalt ändern
//      container.__rmMap.setMapset( ... )         -> Layer ändern
//
// container    DOM-Element, auf dem die Leaflet-Karte eingerichtet wird

Geolocation.initMap = function( container ){

    // Den Container gibt es nicht -> Abbruch
    if( !( container instanceof HTMLElement) ) return;

    // Container hat bereits eine Karte -> Abbruch
    if( container.__rmMap ) return;

    // Karteninstanz anlegen
    container.__rmMap = Geolocation.classes.map(
        container,
        Geolocation.fromJSON( container.getAttribute('dataset') ),
        Geolocation.fromJSON( container.getAttribute('mapset') ),
        Geolocation.fromJSON( container.getAttribute('map') )
    );
}


//-- allgemeine Klassen ----------------------------------------------------------------------------

// baut den Wrapper für eine Karte auf.
// Methoden um die Karte mit dataset und mapset zu bestücken

Geolocation.Classes.Map = class {

    constructor( ) {
        this.layers = [];
        this.layerControl = null;
        this.zoomControl = null;
        this.locationControl = null;
        this.currentLocation = null;
        this.tools = [];
		this.map = null;
        return this;
    }

	initializeMap( container, dataset={}, mapset={}, mapOptions={} ){
		// ist schon initialisiert
		if( this.map ) return this.map;

		// Initialisieren aussichtslos; Leaflet fehlt
        if( !L ) {
			console.error( 'Leaflet is not installed' );
			return null;
		}

        // Kartenparameter zusammensuchen; Karte erstellen
        mapOptions = L.extend( Geolocation.default.mapOptions,mapOptions );
        mapOptions.zoomControl = false;
        this.map = L.map( container, mapOptions );
        if( !this.map ) return null;

        // Zusatzbuttons für "Zoom" und "Home" einbauen, indem das neue Zoom-Control aktiviert wird.
        this.map.on( 'load', function(e){
            this.zoomControl = this.zoomControl || new L.Control.GeolocationZoom().addTo(this.map);
            if( true === Geolocation.default.mapOptions.locateControl ) {
                this.locationTool = Geolocation.tools._currentlocation();
            }
            container.dispatchEvent(new CustomEvent('geolocation:map.ready', { 'detail':{'container':container, 'map':this.map},bubbles:true }));
        }, this);

        this.layerControl = L.control.layers( );

        // Karte über den Dataset parametrisieren
        this.setDataset( dataset );
        // Layer der Karte hinzufügen
        // Falls noch nicht einjustiert (z.B. weil kein bounds-Tool oder Äquivalent angegeben)
        // auf die Default-Bounds setzen.
        this.setMapset( mapset );
        if( !this.map.getZoom() ) {
            this.map.fitBounds( Geolocation.default.bounds );
        }

		return this.map;
	}

	distroyMap() {
		this.clearData();
		this.clearLayers();
		this.map.remove();
		this.map = null;
		this.layerControl = null;
        this.currentLocation = null;
        this.zoomControl= null;
	}

    setDataset( dataset={}, clearDataset=true ){
        if( !this.map ) return;
        if( true === clearDataset ) this.clearData();

        // wenn es keine Tools gibt: Ende
        let tools;
        tools = Object.keys(dataset);
//        if( 0 == tools.length ) return;

        tools.forEach( (tool) => {
            if( '_' == tool.charAt(0) ) return;  // _tool ist nur für interne Zwecke
            if( !(typeof Geolocation.tools[tool] == 'function') ) return;
            this.tools.push( Geolocation.tools[tool]().setValue( dataset[tool] ) );
        })
        this.tools.forEach( (t) => t.show( this.map ) );
        this.map._container.dispatchEvent(new CustomEvent('geolocation:dataset.ready', { 'detail':{'container':this.map._container, 'map':this.map},bubbles:true }));
    }

	setMapset( mapset={}, clearLayers=true ){
        if( !this.map ) return;
        if( true === clearLayers ) this.clearLayers();

        // wenn es keine Layer gibt: Ende
        let layerNames;
        layerNames = Object.keys(mapset);
        if( 0 == layerNames.length ) return;

        let layer, label, layertype, tile, geolayer, defaultLayer = 'default';
        // Wenn es keinen Layer "default" gibt: dann es ersten nehmen
        if( layerNames.indexOf(defaultLayer) == -1 ) defaultLayer = layerNames[0];
        if( layerNames.length > 1 ) this.layerControl.addTo( this.map );
        // Layer einfügen
        for( tile in mapset ){
            label = mapset[tile].label || tile;
            delete mapset[tile].label;
            geolayer = mapset[tile].layer || 0;
            delete mapset[tile].layer;
            layertype = mapset[tile].type || 'b';
            delete mapset[tile].type;
            layer = L.tileLayer( 'index.php?'+Geolocation.default.keyLayer+'='+geolayer+'&z={z}&x={x}&y={y}',mapset[tile] );
            if( tile == defaultLayer ) layer.addTo( this.map );
            if( 'b' == layertype ){
                this.layerControl.addBaseLayer( layer, label );
            } else {
                this.layerControl.addOverlay( layer, label );
            }
            this.layers.push( layer );
        }
	}

	clearLayers() {
		if( !this.map ) return;
		this.layers.forEach( (layer) => {
                layer.remove();
                this.layerControl.removeLayer(layer)
            } );
        this.layers = [];
        this.layerControl.remove();
	}

	clearData() {
		if( !this.map ) return;
        this.tools.forEach( (tool) => tool.remove() );
		this.tools = [];
	}

    fitBounds( bounds ){
        if( !(bounds instanceof L.LatLngBounds) ){
            bounds = L.latLngBounds();
        }
        // LocationControl-Marker auf die aktuelle Position des Devices gesetzt?
        if( this.locationTool && this.locationTool.isActive() ) {
            bounds.extend( this.locationTool.getCurrentBounds() );
        }
        this.tools.forEach( (tool) => {
            let position = tool.getCurrentBounds();
            if( position ) bounds.extend( position );
        });
        this.map.fitBounds( bounds );
        return bounds;
    }
}

// Factory-Funktion für Geolocation.Classes.Map;
Geolocation.classes.map = function( container, dataset, mapset, mapoptions ){
    let map = new Geolocation.Classes.Map();
    if( container instanceof HTMLElement) map.initializeMap( container, dataset, mapset, mapoptions );
    return map;
}


// ein neues Zoom-Control mit zusätzlichen Buttons für Fullscreen und Home.
// Es handelt sich um das Original-Zoom aus Leaflet;
// onAdd und onRemove sind verändert
L.Control.GeolocationZoom = L.Control.Zoom.extend({

    onAdd: function (map) {
        var zoomName = 'leaflet-control-zoom',
            container = L.DomUtil.create('div', zoomName + ' leaflet-bar geolocation-control'),
            options = this.options;

        if( true === Geolocation.default.mapOptions.fullscreen ) {
            this._glFullscreen  = this._createButton(Geolocation.icon.fullscreenOn, Geolocation.i18n('Fullscreen On'),
                    'fullscreen',  container, this.glOnFullscreen.bind(this) );
        }

        this._zoomInButton  = this._createButton(options.zoomInText, Geolocation.i18n('Zoom In'),
                zoomName + '-in',  container, this._zoomIn);
        this._zoomOutButton = this._createButton(options.zoomOutText, Geolocation.i18n('Zoom Out'),
                zoomName + '-out', container, this._zoomOut);

        this._glHome  = this._createButton(Geolocation.icon.focusR, Geolocation.i18n('Home'),
                'home',  container, this.glOnHome.bind(this) );

        if( true === Geolocation.default.mapOptions.locateControl ) {
            this._glLocate  = this._createButton(Geolocation.icon.locate, Geolocation.i18n('Locate'),
                    'home',  container, this.glOnLocate.bind(this) );
        }

        this._updateDisabled();

        map.on('zoomend zoomlevelschange', this._updateDisabled, this);
        map._container.addEventListener('fullscreenchange', this.glSetFullscreenButton.bind(this), false);
        map.on('locationfound', this.glOnMapLocationFound, this);


        return container;
    },

    onRemove: function( map ) {
        map._container.removeEventListener('fullscreenchange', this.glSetFullscreenButton.bind(this), false);
        map.off('zoomend zoomlevelschange', this._updateDisabled, this);
    },

    glIsFullscreen: function( ){
        return this._map._container == document.fullscreenElement;
    },

    glSetFullscreenButton: function( e ){
        if( this.glIsFullscreen() ) {
            this._glFullscreen.firstChild.innerHTML = Geolocation.icon.fullscreenOff;
            let label = Geolocation.i18n('Fullscreen Off');
            this._glFullscreen.setAttribute('title',label);
            this._glFullscreen.setAttribute('aria-label',label);
        } else {
            this._glFullscreen.firstChild.innerHTML = Geolocation.icon.fullscreenOn;
            let label = Geolocation.i18n('Fullscreen On');
            this._glFullscreen.setAttribute('title',label);
            this._glFullscreen.setAttribute('aria-label',label);
        }
    },

    glOnFullscreen: function( e ){
        e.preventDefault();
        e.stopPropagation();
        if( this.glIsFullscreen() ){
            document.exitFullscreen();
            return;
        }
        this._map._container.requestFullscreen();
    },

    glOnHome: function( e ){
        this._map._container.__rmMap.fitBounds();
    },

    glOnLocate: function( e ){
        let currentLocation = this._map._container.__rmMap.locationTool;
        if( currentLocation.isActive() ) {
            this._map.stopLocate();
            this._glLocate.classList.remove('geolocation-location-active');
            currentLocation.remove(this._map);
            return;
        }
        this._map.locate({setView: true, maxZoom: 16});
    },

    glOnMapLocationFound: function(e) {
        let radius = e.accuracy;
        let currentLocation = this._map._container.__rmMap.locationTool;
        currentLocation.setValue(e.latlng, radius);
        currentLocation.show(this._map);
        this._glLocate.classList.add('geolocation-location-active');
    }

});

// neuer Marker für die Location, Original überarbeitet
// Quelle: leaflet-locatecontrol, Copyright (c) 2016 Dominik Moritz
/**
 * Compatible with L.Circle but a true marker instead of a path
 */
L.LocationMarker = L.Marker.extend({
    initialize: function (latlng, options) {
        L.Util.setOptions(this, options);
        this._latlng = latlng;
        this.createIcon();
    },

    /**
     * Create a styled circle location marker
     */
    createIcon: function() {
        let icon = this._getIconSVG(this.options);

        this._locationIcon = L.divIcon({
            className: icon.className,
            html: icon.svg,
            iconSize: [icon.w,icon.h],
        });

        this.setIcon(this._locationIcon);
    },

    /**
     * Return the raw svg for the shape
     *
     * Split so can be easily overridden
     */
    _getIconSVG: function(options) {
        let r = options.radius;
        let s = r + options.weight;
        let s2 = s * 2;
        let svg = '<svg xmlns="http://www.w3.org/2000/svg" width="'+s2+'" height="'+s2+'" version="1.1" viewBox="-'+s+' -'+s+' '+s2+' '+s2+'">' +
        '<circle r="'+r+'" /></svg>';
        return {
            className: options.className || 'geolocation-locate-location',
            svg: svg,
            w: s2,
            h: s2
        };
    },

    setStyle: function(style) {
        L.Util.setOptions(this, style);
        this.createIcon();
    }
})




//-- Tools für den Karten-Content ------------------------------------------------------------------

// Basisklasse für Map-Tools
// args         an den Constructor übergebene Parameter
// rawdata      erstes Argument mit den Daten für das Tool
// mapdata      aufbereitete rawdata, i.d.R. Leaflet-Kartenobjekte
// setValue     setze die Werte, Umsetzung der Rohdaten in Kartenobjekte
// show         Objekte in die Karte bringen
// remove       Objekte aus der Karte entfernen

Geolocation.Tools.Template = class {

    constructor ( ...args){
        this.rawdata = null;
        this.map = null;
        this.args = args;
        return this;
    }
    setValue( data ){
        this.rawdata = data;
        return this;
    }
    show( map ){
        this.map = map;
        return this;
    }
    remove(){
        this.map = null;
        return this;
    }
    getCurrentBounds(){
        return null;
    }

}

Geolocation.Tools._CurrentLocation = class extends Geolocation.Tools.Template {

    setValue( latLng, radius ){
        super.setValue( latLng );
        let pos = L.latLng( latLng );
        if( !pos ) return this;
        if( this.marker instanceof L.Marker ) {
            this.marker.setLatLng( pos );
            this.circle.setLatLng( pos );
            this.circle.setRadius( radius );
            return this;
        }
        this.marker = new L.LocationMarker( pos, Geolocation.default.locationMarker );
        this.circle = L.circle( pos, radius, Geolocation.default.locationMarkerCircle );
        return this;
    }
    show( map ){
        super.show( map );
        if( this.marker instanceof L.Marker ) this.marker.addTo( map ).openPopup();
        if( this.circle instanceof L.Circle ) this.circle.addTo( map );
        return this;
    }
    remove(){
        if( this.marker instanceof L.Marker ) this.marker.remove();
        if( this.circle instanceof L.Circle ) this.circle.remove();
        super.remove();
        return this;
    }
    getCurrentBounds(){
        return this.isActive() ? this.marker.getLatLng() : super.getCurrentBounds();
    }
    isActive(){
        return null !== this.map && this.marker instanceof L.Marker;
    }
}
Geolocation.tools._currentlocation = function(...args) { return new Geolocation.Tools._CurrentLocation(args); };

// Tool: marker
// Liste von Standard-Markern
// [ [lat,lng], ...]
Geolocation.Tools.Marker = class extends Geolocation.Tools.Template{
    setValue( latLngArray ){
        super.setValue( latLngArray );
        if( this.map ) {
            let map = this.map;
            this.remove();
            this.map = map;
        }
        this.marker = [];
        latLngArray.forEach( (latLng) => {
            let pos = L.latLng( latLng );
            if( !pos ) return;
            let marker = L.marker( pos );
            if( marker ) {
                this.marker.push( marker );
                marker.setIcon( Geolocation.svgIconPin( Geolocation.default.markerColor ) );
            }
        } );
        if( this.map ) this.show( this.map );
        return this;
    }
    show( map ){
        super.show( map );
        this.marker.forEach( (marker) => {
            if( marker instanceof L.Marker ) marker.addTo( map );
        });
        return this;
    }
    remove(){
        this.marker.forEach( (marker) => {
            if( marker instanceof L.Marker ) marker.removeFrom(this.map);
        });
        super.remove();
        return this;
    }
    getCurrentBounds(){
        let rect = L.latLngBounds();
        this.marker.forEach( (marker) => {
            if( marker instanceof L.Marker ) rect.extend( marker.getLatLng() );
        });
        return rect;
    }
}
Geolocation.tools.marker = function(...args) { return new Geolocation.Tools.Marker(args); };

// Tool: position
// Einzelner Positionsmarker (rot)
// [lat,lng]
Geolocation.Tools.Position = class extends Geolocation.Tools.Template{
    setValue( latLng ){
        super.setValue( latLng );
        let pos = L.latLng( latLng );
        if( !pos ) return this;
        if( this.marker instanceof L.Marker ) {
            this.marker.setLatLng( pos );
            return this;
        }
        this.marker = L.marker( pos );
        this.marker.setIcon( Geolocation.svgIconPin(Geolocation.default.positionColor) );
        return this;
    }
    show( map ){
        super.show( map );
        if( this.marker instanceof L.Marker ) this.marker.addTo( map );
        return this;
    }
    remove(){
        if( this.marker instanceof L.Marker ) this.marker.remove();
        super.remove();
        return this;
    }
    getCurrentBounds(){
        return (this.marker instanceof L.Marker) ? this.marker.getLatLng() : null;
    }
}
Geolocation.tools.position = function(...args) { return new Geolocation.Tools.Position(args); };

// Tool: bounds
// Anzuzeigender Bereich, der auf jeden Fall im Viewport liegen muss
// [ [latNW,lngNW],[latSO,lngSO] ]
Geolocation.Tools.Bounds = class extends Geolocation.Tools.Template{
    setValue( bounds ){
        super.setValue( bounds );
        let rect = L.latLngBounds( bounds );
        if( !rect.isValid() ) rect = L.latLngBounds( Geolocation.default.bounds );
        this.bounds = rect;
        if( this.rect instanceof L.Rectangle ){
            this.rect.setBounds( rect );
        } else {
            this.rect = L.rectangle(this.bounds, Geolocation.default.boundsRect);
        }
        if( this.map ) this.show( this.map );
        return this;
    }
    show( map ){
        super.show( map );
        this.map.fitBounds( this.bounds );
        this.rect.setBounds( this.bounds ).addTo( map );
        return this;
    }
    remove(){
        if( this.rect instanceof L.Rectangle ) this.rect.remove();
        super.remove();
        return this;
    }
    getCurrentBounds(){
        return this.bounds;
    }
}
Geolocation.tools.bounds = function(...args) { return new Geolocation.Tools.Bounds(args); };

// Tool geojson
// packt einfach einen geojson-Datensatz auf die Karte
Geolocation.Tools.GeoJSON = class extends Geolocation.Tools.Template
{
    setValue( geojsonData ){
        super.setValue( geojsonData );
        if( this.map ) {
            let map = this.map;
            this.remove();
            this.map = map;
        }
        this.geojsonLayer = L.geoJSON(
            geojsonData,
            this.setOptions( {
                style: this._style,
                onEachFeature: this._eachFeature.bind(this),
                pointToLayer: this._pointToLayer.bind(this)
            } )
        );
        if( this.map ) this.show( this.map );
        return this;
    }
    show( map ){
        super.show( map );
        this.geojsonLayer.addTo( map );
        return this;
    }
    remove(){
        this.geojsonLayer.removeFrom(this.map);
        super.remove();
        return this;
    }
    getCurrentBounds(){
        return this.geojsonLayer.getBounds(); //rect;
    }
    //--- Sonderfunktionen
    setOptions( options ) {
        return options;
    }
    _style(feature) {
        return feature.properties && feature.properties.style;
    }
    _eachFeature(feature, layer) {
        if( !feature.properties ) return;
        let popupContent = feature.properties.popupContent || null;
        if( popupContent ) {
    		layer.bindPopup(popupContent);
        }
	}
    _pointToLayer(feature, latlng) {
        return L.marker(latlng);
	}

}
Geolocation.tools.geojson = function(...args) { return new Geolocation.Tools.GeoJSON(args); };

//-- Custom HTML-Element ---------------------------------------------------------------------------

Geolocation.Classes.RexMap = class extends HTMLElement{

    static get observedAttributes() { return ['dataset','mapset'] };

    constructor(...args) {
        super(...args);
        this.__rmMap = null;
        return this;
    }

    attributeChangedCallback(attrName, oldVal, newVal) {
        if( !this.__rmMap ) return;
        if( attrName == 'dataset' ) {
            this._rmSetAttrDataset( newVal );
            return;
        }
        if( attrName == 'mapset' ) {
            this._rmSetAttrMapset( newVal );
            return;
        }
    }

    connectedCallback() {
		if( !this.__rmMap ) {
			// Kartenparameter zusammensuchen; Karte erstellen
			this.__rmMap = Geolocation.classes.map(
                this,
                Geolocation.fromJSON( this.getAttribute('dataset') ),
                Geolocation.fromJSON( this.getAttribute('mapset') ),
                Geolocation.fromJSON( this.getAttribute('map') )
            );
			return;
		}

        // Karte über den Dataset parametrisieren
		this._rmSetAttrDataset( this.getAttribute('dataset') );

		// Layer der Karte hinzufügen
		this._rmSetAttrMapset( this.getAttribute('mapset') );
    }

    _rmSetAttrDataset( newVal, clearDataset=true ){
        if( !this.__rmMap ) return;
 		this.__rmMap.setDataset( Geolocation.fromJSON( newVal ), clearDataset );
	}

    _rmSetAttrMapset( newVal, clearLayers=true ){
        if( !this.__rmMap ) return;
		this.__rmMap.setMapset( Geolocation.fromJSON( newVal ), clearLayers );
    }

}

customElements.define('rex-map', Geolocation.Classes.RexMap);
