var Geolocation = {

    ressources: 'assets/addons/geolocation/ressources/',

    Classes: {},    // Klassen

    Tools: {},      // Die Klassen für Map-Tools
    tools: {},      // Factory-Funktionen für Map-Tools
};

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
}

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
            if( marker ) this.marker.push( marker );
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
}
Geolocation.tools.marker = function(...args) { return new Geolocation.Tools.Marker(...args); };

// Tool: position
// Einzelner Positionsmarker (rot)
// [lat,lng]
Geolocation.Tools.Position = class extends Geolocation.Tools.Template{
    setValue( latLng ){
        super.setValue( latLng );
        let pos = L.latLng( latLng );
        if( !pos ) return;
        if( this.mapdata instanceof L.Marker ) {
            this.mapdata.setLatLng( pos );
            return;
        }
        this.marker = L.marker( pos );
        this.marker.setIcon(L.icon( {
            iconUrl: Geolocation.ressources + 'marker/marker-icon-red.png',
            iconRetinaUrl:Geolocation.ressources + 'marker/marker-icon-2x-red.png',
            iconAnchor:[12,41],
            iconSize:[25,41],
            popupAnchor:[1,-34],
            tooltipAnchor:[16,-28],
            shadowSize: [41,41],
            shadowUrl: 'assets/addons/geolocation/vendor/leaflet/images/marker-shadow.png',
        } ) );
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
}
Geolocation.tools.position = function(data) { return new Geolocation.Tools.Position(data); };

// Tool: bounds
// Anzuzeigender Bereich, der auf jeden Fall im Viewport liegen muss
// [ [latNW,lngNW],[latSO,lngSO] ]
Geolocation.Tools.Bounds = class extends Geolocation.Tools.Template{
    setValue( data ){
        let self = super.setValue( data );
        let rect = L.latLngBounds( data );
        if( !rect.isValid() ) rect = L.latLngBounds( this.__rmParams.bounds );
        this.rect = rect;
        if( this.map ) this.map.fitBounds( rect );
        return this;
    }
    show( map ){
        super.show( map );
        if( this.rect && this.rect.isValid() ) map.fitBounds( this.rect );
        return this;
    }
}
Geolocation.tools.bounds = function(data) { return new Geolocation.Tools.Bounds(data); };

// Webcomponente <rex-map>
Geolocation.Classes.RexMap = class extends HTMLElement{

    static get observedAttributes() { return ['dataset','mapset'] };

    constructor(...args) {
        const self = super(...args);
        this.__rmParams = {
            mapOptions:
                {
                    minZoom:2,
                    maxZoom:18,
                    scrollWheelZoom:true,
                },
            bounds: [[45,6],[55,8]],
        };
        this.__rmMap = null;
        this.__rmLayers = [];
        this.__rmMarkers = [];
        this.__rmLayerControl = L.control.layers( );
        this.__rmTools = [];
        return self;
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
        if( !L ) console.error( 'Leaflet ist nicht installiert' );
        this.style.display = 'block'; // besser anderes regeln

        let mapOptions, dataset, mapset;

        // Kartenparameter zusammensuchen; Karte erstellen
        try {
            mapOptions = JSON.parse( this.getAttribute('map') ) || {};
        } catch (e) {
            mapOptions = {};
        } finally {
            mapOptions = L.extend( this.__rmParams.mapOptions,mapOptions );
        }
        this.__rmMap = L.map( this, mapOptions );
        if( !this.__rmMap ) return;

        // Karte über den Dataset parametrisieren
        this._rmSetAttrDataset( this.getAttribute('dataset') )
        // Layer der Karte hinzufügen
        this._rmSetAttrMapset( this.getAttribute('mapset') );
    }

    _rmSetAttrDataset( newVal, clearDataset=true ){
        if( !this.__rmMap ) return;
        if( true === clearDataset ){
            this.__rmTools.forEach( (tool) => tool.remove() );
            this.__rmTools = [];
        }
        let dataset, tools;
        try {
            dataset = JSON.parse( newVal ) || {};
        } catch (e) {
            dataset = {};
        } finally {
            tools = Object.keys(dataset);
            if( 0 == tools.length ) return;
        }
        tools.forEach( (tool) => {
            if( !(typeof Geolocation.tools[tool] == 'function') ) return;
            this.__rmTools.push( Geolocation.tools[tool]().setValue( dataset[tool] ) );
        })
        this.__rmTools.forEach( (tool) => tool.show( this.__rmMap ) );
    }

    _rmSetAttrMapset( newVal, clearLayers=true ){
        if( !this.__rmMap ) return;
        if( true === clearLayers ){
            this.__rmLayers.forEach( (layer) => {
                layer.remove();
                this.__rmLayerControl.removeLayer(layer)
            } );
            this.__rmLayers = [];
            this.__rmLayerControl.remove();
        }
        let mapset, layerNames;
        try {
            mapset = JSON.parse( newVal ) || {};
        } catch (e) {
            mapset = {};
        } finally {
            // wenn es keine Layer gibt; ende
            layerNames = Object.keys(mapset);
            if( 0 == layerNames.length ) return;
        }
        let layer, label, layertype, tile, geotile, defaultLayer = 'default';
        // Wenn es keinen Layer "default" gibt: dann es ersten nehmen
        if( layerNames.indexOf(defaultLayer) == -1 ) defaultLayer = layerNames[0];
        if( layerNames.length > 1 ) this.__rmLayerControl.addTo( this.__rmMap );
        // Layer einfügen
        for( tile in mapset ){
            label = mapset[tile].label || tile;
            delete mapset[tile].label;
            geotile = mapset[tile].tile || 0;
            delete mapset[tile].tile;
            layertype = mapset[tile].type || 'b';
            delete mapset[tile].type;
            layer = L.tileLayer( 'index.php?geotile='+geotile+'&z={z}&x={x}&y={y}',mapset[tile] );
            if( tile == defaultLayer ) layer.addTo( this.__rmMap );
            if( 'b' == layertype ){
                this.__rmLayerControl.addBaseLayer( layer, label );
            } else {
                this.__rmLayerControl.addOverlay( layer, label );
            }
            this.__rmLayers.push( layer );
        }

    }

    disconnectedCallback() {
    }

}
customElements.define('rex-map', Geolocation.Classes.RexMap);
